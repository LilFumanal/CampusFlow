#!/usr/bin/env bash
set -euo pipefail

# Vérifie qu'un fichier local de configuration existe.
if [ ! -f .env.local ]; then
  echo "Crée .env.local à partir de .env.local.example avant de continuer."
  exit 1
fi

# Construit et démarre l'application et sa base locale.
docker compose up -d --build

# Attend que la base soit prête si le service a un healthcheck.
# Sinon, relance la migration manuellement après le démarrage.
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction

echo "CampusFlow est lancé. Ouvre http://localhost:8090"