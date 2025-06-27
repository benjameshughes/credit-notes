#!/bin/bash

# Laravel Forge Deployment Script for Credit Notes Generator
# This script should be added to your Laravel Forge site deployment script

set -e

echo "🚀 Starting deployment..."

# Navigate to the application directory
cd $FORGE_SITE_PATH

# Enable maintenance mode
php artisan down || true

echo "📦 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Check if .env exists, if not copy from example
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

echo "🗄️ Running database migrations..."
php artisan migrate --force

echo "📂 Creating storage directories..."
# Create necessary storage directories
mkdir -p storage/app/temp-csv
mkdir -p storage/app/pdfs
mkdir -p storage/app/downloads

# Set proper permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo "🎨 Installing NPM dependencies and building assets..."
npm ci
npm run build

echo "⚡ Optimizing application..."
# Clear and cache configurations
php artisan config:clear
php artisan config:cache

php artisan route:clear
php artisan route:cache

php artisan view:clear
php artisan view:cache

# Cache events for better performance
php artisan event:cache

echo "🔄 Restarting services..."
# Restart PHP-FPM
sudo service php8.2-fpm reload

# Restart queue workers
php artisan queue:restart

# If using Supervisor, restart queue workers
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl restart all
fi

echo "🌐 Disabling maintenance mode..."
php artisan up

echo "✅ Deployment completed successfully!"

# Optional: Send deployment notification
# You can add Slack/Discord webhook notifications here
# curl -X POST -H 'Content-type: application/json' \
#   --data '{"text":"✅ Credit Notes app deployed successfully!"}' \
#   YOUR_WEBHOOK_URL