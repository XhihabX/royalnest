#!/bin/bash

# ==============================================================================
# Royal Nest - cPanel 1-Click Production Deployment & Synchronization Script
# ==============================================================================

set -e

echo "🚀 Starting Royal Nest Production Deployment..."

# 1. Environment check
if [ ! -f ".env" ]; then
    echo "📄 Creating .env from .env.example..."
    cp .env.example .env
    echo "⚠️  Remember to update your DB_DATABASE, DB_USERNAME, and DB_PASSWORD in .env!"
fi

# 2. Install Composer dependencies (production only, no dev bloat)
echo "📦 Installing PHP dependencies via Composer..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 3. Generate APP_KEY if missing
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating Application Encryption Key..."
    php artisan key:generate --force
fi

# 4. Storage Symlink
echo "🔗 Creating public storage symlink..."
php artisan storage:link || true

# 5. Database Migrations & Seeds
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Seed system roles, accounts, and defaults if fresh database
echo "🌱 Seeding system defaults..."
php artisan db:seed --class=RoyalNestV1Seeder --force || true

# 6. Set correct permissions
echo "🔒 Adjusting file permissions for storage & cache..."
chmod -R 775 storage bootstrap/cache || true

# 7. Optimize & Cache Configurations
echo "⚡ Caching routes, configurations, and views for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "✅ ============================================================="
echo "🎉 Royal Nest is now LIVE and fully optimized on your server!"
echo "🌐 URL: https://xhihab.com"
echo "============================================================="
