#!/bin/bash
# Deploy script for ERP Konstruksi
set -e

echo "=== ERP Konstruksi Deployment ==="

# 1. Pull latest code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Run migrations
php artisan migrate --force

# 4. Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize

# 5. Restart queue worker
sudo systemctl restart erp-queue-worker

echo "✅ Deployment complete!"
