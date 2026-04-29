<?php

namespace App\Http\Controllers\Api\V1;

use App\Channels\Enums\ChannelType;
use App\Channels\Enums\MessageDirection;
use App\Channels\Enums\MessageType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Jobs\SendOutboundMessage;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoint público `POST /api/v1/messages` — el corazón comercial de Sinapsa.
 *
 * Body discriminado por `type`:
 *   - text:        { type:"text", text:{ body:"..." } }
 *   - template:    { type:"template", template:{ name, language, components[] } }
 *   - image/document/audio/video: { type:"image", image:{ link?|id?, caption? } }
 *   - interactive: { type:"interactive", interactive:{...payload Meta...} }
 *   - location:    { type:"location", location:{ lat, lng, name?, address? } }
 *
 * Targeting:
 *   - by phone: { to:{ phone:"+34..." } }
 *   - by contact: { to:{ contact_id:"ct_xx" } }  (id numérico también vale)
 *
 * Respuesta 202: { id, status:"queued", ... } — el envío real ocurre en cola.
 *
 * Errores tipados Stripe-like via ApiException.
 */
class PublicMessageController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $workspaceId = $request->user()->workspace_id;
        $channel = $this->resolveChannel($payload['channel_id'], $workspaceId);
        $contact = $this->resolveContact($payload['to'], $workspaceId);
        $conversation = $this->upsertConversation($channel, $contact);

        $type = MessageType::from($payload['type']);
        $this->enforceWindowOrTemplate($conversation, $type);

        $message = Message::create([
            'workspace_id' => $workspaceId,
            'conversation_id' => $conversation->id,
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'direction' => MessageDirection::Outbound->value,
            'status' => Message::STATUS_QUEUED,
            'type' => $type->value,
            'client_idempotency_key' => $request->header('Idempotency-Key'),
            'body' => data_get($payload, 'text.body')
                ?? data_get($payload, 'image.caption')
                ?? data_get($payload, 'document.caption'),
            'media_url' => data_get($payload, 'image.link')
                ?? data_get($payload, 'document.link')
                ?? data_get($payload, 'audio.link')
                ?? data_get($payload, 'video.link'),
            'media_mime' => null,
            'template_name' => data_get($payload, 'template.name'),
            'template_payload' => $type === MessageType::Template ? [
                'language' => data_get($payload, 'template.language', 'es'),
                'components' => data_get($payload, 'template.components', []),
            ] : null,
        ]);

        SendOutboundMessage::dispatch($message->id)
            ->onQueue(config('sinapsa.queues.outbound'));

        $conversation->forceFill(['last_message_at' => now()])->save();

        return response()->json($this->messageShape($message->fresh()), 202);
    }

    public function show(Request $request, Message $message): JsonResponse
    {
        if ($message->workspace_id !== $request->user()->workspace_id) {
            throw ApiException::permission('not_found', 'Message not found in this workspace.');
        }

        return response()->json($this->messageShape($message));
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->workspace_id;

        $q = Message::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($cid = $request->integer('conversation_id')) {
            $q->where('conversation_id', $cid);
        }
        if ($contactId = $request->integer('contact_id')) {
            $q->where('contact_id', $contactId);
        }
        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        if ($direction = $request->string('direction')->toString()) {
            $q->where('direction', $direction);
        }

        $limit = min((int) $request->integer('limit', 50), 200);
        $messages = $q->cursorPaginate($limit);

        return response()->json([
            'data' => collect($messages->items())->map(fn ($m) => $this->messageShape($m))->all(),
            'meta' => [
                'next_cursor' => $messages->nextCursor()?->encode(),
                'prev_cursor' => $messages->previousCursor()?->encode(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Validation
    // ─────────────────────────────────────────────────────────────

    protected function validatePayload(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'channel_id' => ['required', 'integer'],
            'to' => ['required', 'array'],
            'to.phone' => ['nullable', 'string'],
            'to.contact_id' => ['nullable'],
            'type' => ['required', 'in:text,image,audio,video,document,template,interactive,location'],

            'text.body' => ['required_if:type,text', 'string', 'max:4096'],
            'template.name' => ['required_if:type,template', 'string'],
            'template.language' => ['nullable', 'string'],
            'template.components' => ['nullable', 'array'],
            'image.link' => ['nullable', 'url'],
            'image.id' => ['nullable', 'string'],
            'image.caption' => ['nullable', 'string', 'max:1024'],
            'document.link' => ['nullable', 'url'],
            'document.id' => ['nullable', 'string'],
            'document.caption' => ['nullable', 'string', 'max:1024'],
            'document.filename' => ['nullable', 'string', 'max:255'],
            'audio.link' => ['nullable', 'url'],
            'audio.id' => ['nullable', 'string'],
            'video.link' => ['nullable', 'url'],
            'video.id' => ['nullable', 'string'],
            'video.caption' => ['nullable', 'string', 'max:1024'],
            'interactive' => ['nullable', 'array'],
            'location.lat' => ['required_if:type,location', 'numeric'],
            'location.lng' => ['required_if:type,location', 'numeric'],
            'location.name' => ['nullable', 'string'],
            'location.address' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $first = $validator->errors()->keys()[0] ?? 'unknown';
            throw ApiException::invalidRequest(
                'invalid_request',
                $validator->errors()->first(),
                $first,
            );
        }

        $data = $validator->validated();

        if (empty($data['to']['phone']) && empty($data['to']['contact_id'])) {
            throw ApiException::invalidRequest(
                'missing_recipient',
                'Either to.phone or to.contact_id is required.',
                'to',
            );
        }

        return $data;
    }

    protected function resolveChannel(int $channelId, int $workspaceId): Channel
    {
        $channel = Channel::withoutGlobalScopes()
            ->where('id', $channelId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if (! $channel) {
            throw ApiException::invalidRequest('channel_not_found', 'Channel not found.', 'channel_id');
        }
        if (! $channel->isConnected()) {
            throw ApiException::invalidRequest(
                'channel_not_connected',
                'Channel is not connected — reconnect before sending.',
                'channel_id',
            );
        }

        return $channel;
    }

    protected function resolveContact(array $to, int $workspaceId): Contact
    {
        if (! empty($to['contact_id'])) {
            $contact = Contact::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('id', (int) $to['contact_id'])
                ->first();
            if (! $contact) {
                throw ApiException::invalidRequest('contact_not_found', 'Contact not found.', 'to.contact_id');
            }

            return $contact;
        }

        $phone = $to['phone'];
        $contact = Contact::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('phone', $phone)
            ->first();

        if (! $contact) {
            $contact = new Contact([
                'phone' => $phone,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'identifiers' => ['whatsapp' => ltrim($phone, '+')],
            ]);
            $contact->workspace_id = $workspaceId;
            $contact->save();
        }

        return $contact;
    }

    protected function upsertConversation(Channel $channel, Contact $contact): Conversation
    {
        $threadId = $channel->type === ChannelType::WhatsApp->value && $contact->phone
            ? ltrim($contact->phone, '+')
            : (data_get($contact->identifiers, $channel->type) ?? (string) $contact->id);

        $conv = Conversation::withoutGlobalScopes()
            ->where('workspace_id', $channel->workspace_id)
            ->where('channel_id', $channel->id)
            ->where('external_thread_id', $threadId)
            ->first();

        if ($conv) {
            return $conv;
        }

        $conv = new Conversation([
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'external_thread_id' => $threadId,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
            'unread_count' => 0,
            'metadata' => ['opened_via' => 'public_api'],
        ]);
        $conv->workspace_id = $channel->workspace_id;
        $conv->save();

        return $conv;
    }

    protected function enforceWindowOrTemplate(Conversation $conversation, MessageType $type): void
    {
        // Plantillas siempre se permiten — Meta valida si está APPROVED.
        if ($type === MessageType::Template) {
            return;
        }

        $hours = (int) config('sinapsa.whatsapp.customer_service_window_hours', 24);
        $lastInbound = $conversation->lastInboundAt();

        if (! $lastInbound || $lastInbound->lessThan(now()->subHours($hours))) {
            throw ApiException::invalidRequest(
                'outside_24h_window',
                "Outside {$hours}h customer service window — send a template instead.",
                'type',
            );
        }
    }

    protected function messageShape(Message $m): array
    {
        return [
            'id' => $m->id,
            'object' => 'message',
            'workspace_id' => $m->workspace_id,
            'conversation_id' => $m->conversation_id,
            'channel_id' => $m->channel_id,
            'contact_id' => $m->contact_id,
            'direction' => $m->direction,
            'status' => $m->status,
            'type' => $m->type,
            'external_id' => $m->external_id,
            'idempotency_key' => $m->client_idempotency_key,
            'body' => $m->body,
            'media' => $m->media_url ? [
                'url' => $m->media_url,
                'mime_type' => $m->media_mime,
            ] : null,
            'template' => $m->template_name ? [
                'name' => $m->template_name,
                'language' => data_get($m->template_payload, 'language'),
                'components' => data_get($m->template_payload, 'components'),
            ] : null,
            'error' => $m->error_code ? [
                'code' => $m->error_code,
                'message' => $m->error_message,
            ] : null,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'delivered_at' => $m->delivered_at?->toIso8601String(),
            'read_at' => $m->read_at?->toIso8601String(),
            'failed_at' => $m->failed_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
