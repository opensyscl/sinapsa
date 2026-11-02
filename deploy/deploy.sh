#!/usr/bin/env bash
# Despliega Sinapsa al VPS (vault-vps → /opt/sinapsa).
# Las imágenes se compilan en local (el VPS va justo de RAM) y se suben con docker save.
# Requiere: REVERB_APP_KEY en el entorno (la misma que backend/.env del VPS).
set -euo pipefail
cd "$(dirname "$0")/.."

HOST=vault-vps
DIR=/opt/sinapsa
: "${REVERB_APP_KEY:?Exporta REVERB_APP_KEY (ver backend/.env del VPS)}"

docker build -q -t sinapsa-php:latest docker/php
docker build -q -t sinapsa-frontend:latest \
  --build-arg NEXT_PUBLIC_API_URL=https://api.sinapsahq.com \
  --build-arg NEXT_PUBLIC_REVERB_HOST=api.sinapsahq.com \
  --build-arg NEXT_PUBLIC_REVERB_KEY="$REVERB_APP_KEY" \
  frontend

rsync -az --delete \
  --exclude 'vendor/' --exclude '.env' --exclude '.env.*' \
  --exclude 'storage/logs/*' --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' \
  --exclude 'storage/app/*' --exclude 'database/database.sqlite' \
  backend docker docker-compose.prod.yml deploy "$HOST:$DIR/"
rsync -az --delete --exclude '.env*' demo/saas-x/ "$HOST:$DIR/demo/saas-x/"

docker save sinapsa-php:latest sinapsa-frontend:latest | gzip -1 | ssh "$HOST" 'gunzip | docker load'

ssh "$HOST" "set -e; cd $DIR
  C='docker compose -f docker-compose.prod.yml --env-file .env.prod'
  chown -R 1000:1000 backend
  \$C up -d postgres redis php
  \$C exec -T php composer install --no-dev --optimize-autoloader --no-interaction
  \$C exec -T php php artisan migrate --force
  \$C exec -T php php artisan optimize
  \$C up -d --force-recreate
  \$C exec -T php php artisan horizon:terminate || true"

echo "OK → https://app.sinapsahq.com"
