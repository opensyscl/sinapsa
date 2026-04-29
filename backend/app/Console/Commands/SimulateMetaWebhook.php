<?php

namespace App\Console\Commands;

use App\Http\Controllers\Webhooks\MetaWebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SimulateMetaWebhook extends Command
{
    protected $signature = 'messages:simulate-webhook
                            {fixture : Ruta absoluta o relativa (busca primero ./, luego tests/Fixtures/meta/<channel>/)}
                            {--channel=whatsapp : Tipo de canal: whatsapp | instagram | messenger}
                            {--queue : Procesar el job inmediatamente (queue:work --once)}';

    protected $description = 'Inyecta un payload Meta como si fuese un webhook real (dev/test)';

    public function handle(MetaWebhookController $controller): int
    {
        $channel = (string) $this->option('channel');

        $path = (string) $this->argument('fixture');
        if (! is_file($path)) {
            $alt = base_path("tests/Fixtures/meta/{$channel}/{$path}");
            if (is_file($alt)) {
                $path = $alt;
            } else {
                // Fallback: busca en whatsapp por si el usuario solo pasó el filename común
                $waAlt = base_path("tests/Fixtures/meta/whatsapp/{$path}");
                if (is_file($waAlt)) {
                    $path = $waAlt;
                } else {
                    $this->error("Fixture no encontrado: {$path}");

                    return self::FAILURE;
                }
            }
        }

        $rawBody = (string) file_get_contents($path);
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            $this->error('JSON inválido en el fixture.');

            return self::FAILURE;
        }

        // Reemplazamos timestamps + ids para que cada run sea idempotency-distinct.
        // Soporta los 3 shapes:
        //  - WhatsApp: entry[].changes[].value.{messages|statuses}[].{timestamp,id}
        //  - IG/Messenger: entry[].messaging[].{timestamp, message.mid}
        $now = now();
        $nowSec = (string) $now->timestamp;
        $nowMs = (string) (int) ($now->timestamp * 1000);
        $randSuffix = strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));

        foreach ($payload['entry'] ?? [] as $ei => $entry) {
            // WA shape
            foreach ($entry['changes'] ?? [] as $ci => $change) {
                $msgs = $change['value']['messages'] ?? [];
                foreach ($msgs as $mi => $m) {
                    if (isset($m['timestamp'])) {
                        $payload['entry'][$ei]['changes'][$ci]['value']['messages'][$mi]['timestamp'] = $nowSec;
                    }
                    if (isset($m['id'])) {
                        $payload['entry'][$ei]['changes'][$ci]['value']['messages'][$mi]['id'] = "wamid.SIM{$randSuffix}{$mi}";
                    }
                }
                $sts = $change['value']['statuses'] ?? [];
                foreach ($sts as $si => $s) {
                    if (isset($s['timestamp'])) {
                        $payload['entry'][$ei]['changes'][$ci]['value']['statuses'][$si]['timestamp'] = $nowSec;
                    }
                }
            }

            // IG / Messenger shape
            foreach ($entry['messaging'] ?? [] as $mi => $event) {
                if (isset($event['timestamp'])) {
                    $payload['entry'][$ei]['messaging'][$mi]['timestamp'] = $nowMs;
                }
                if (isset($event['message']['mid'])) {
                    $payload['entry'][$ei]['messaging'][$mi]['message']['mid'] = "mid.SIM{$randSuffix}{$mi}";
                }
            }
        }
        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $request = Request::create("/webhooks/meta/{$channel}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawBody);

        $response = $controller->receive($request, $channel);

        $this->info("[{$channel}] Respuesta: HTTP {$response->getStatusCode()} — {$response->getContent()}");

        if ($this->option('queue')) {
            $this->call('queue:work', [
                '--queue' => config('sinapsa.queues.inbound'),
                '--once' => true,
                '--stop-when-empty' => true,
            ]);
        }

        return self::SUCCESS;
    }
}
