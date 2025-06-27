# Laravel Forge Deployment Guide

This guide helps you deploy the Credit Notes Generator to Laravel Forge.

## Prerequisites

- Laravel Forge account
- GitHub repository (will be created)
- Domain name (optional)

## Step 1: Create Server

1. **In Laravel Forge:**
   - Create new server (Digital Ocean, AWS, etc.)
   - Choose PHP 8.2+
   - Select database: SQLite (recommended) or MySQL
   - Enable Node.js

## Step 2: Create Site

1. **Create new site:**
   - Domain: `your-domain.com` or use Forge's temporary domain
   - Project type: General PHP/Laravel
   - Web directory: `/public`

2. **Connect GitHub repository:**
   - Repository: `benhughes22/credit-notes`
   - Branch: `main`
   - Enable auto-deployment

## Step 3: Environment Configuration

1. **Copy `.env.production` to `.env`:**
   ```bash
   cp .env.production .env
   ```

2. **Update `.env` values:**
   ```env
   APP_URL=https://your-domain.com
   REVERB_HOST=your-domain.com
   REVERB_APP_KEY=generate-random-key
   REVERB_APP_SECRET=generate-random-secret
   ```

3. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

## Step 4: SSL Certificate

1. **In Forge site settings:**
   - Go to SSL tab
   - Generate Let's Encrypt certificate
   - Or upload custom certificate

## Step 5: Deployment Script

1. **In Forge site deployment:**
   - Replace default script with contents of `deploy.sh`
   - Test deployment by clicking "Deploy Now"

## Step 6: Queue Configuration

1. **Create queue worker:**
   ```bash
   # In Forge site "Queue" tab, add:
   Connection: database
   Queue: default
   Timeout: 90
   Sleep: 3
   Processes: 1
   ```

## Step 7: WebSocket Configuration (Laravel Reverb)

### Option A: Run Reverb as a separate service

1. **Create daemon in Forge:**
   ```bash
   # Command:
   /usr/bin/php8.2 /home/forge/your-site/artisan reverb:start --host=0.0.0.0 --port=8080
   
   # Directory:
   /home/forge/your-site
   
   # User: forge
   # Auto-restart: Yes
   ```

2. **Configure Nginx for WebSocket proxying:**
   ```nginx
   # Add to your site's Nginx configuration:
   location /app/ {
       proxy_pass http://127.0.0.1:8080;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "Upgrade";
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
       proxy_cache_bypass $http_upgrade;
   }
   ```

### Option B: Use external WebSocket service

If you prefer not to manage WebSockets on your server:

1. **Use Pusher:**
   ```env
   BROADCAST_CONNECTION=pusher
   PUSHER_APP_ID=your-app-id
   PUSHER_APP_KEY=your-app-key
   PUSHER_APP_SECRET=your-app-secret
   PUSHER_HOST=
   PUSHER_PORT=443
   PUSHER_SCHEME=https
   PUSHER_APP_CLUSTER=mt1
   ```

2. **Install Pusher PHP SDK:**
   ```bash
   composer require pusher/pusher-php-server
   ```

## Step 8: File Permissions

Ensure proper permissions for storage directories:

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R forge:www-data storage
chown -R forge:www-data bootstrap/cache
```

## Step 9: Testing

1. **Test basic functionality:**
   - Visit your site
   - Login/register
   - Upload a small CSV file
   - Verify PDF generation

2. **Test real-time updates:**
   - Open browser developer tools
   - Check for WebSocket connection
   - Verify progress updates during processing

## Step 10: Monitoring

1. **Set up monitoring:**
   - Enable Forge monitoring
   - Monitor queue workers
   - Monitor Reverb process (if using)

2. **Log monitoring:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Troubleshooting

### Common Issues

1. **WebSocket connection fails:**
   - Check Nginx configuration
   - Verify Reverb daemon is running
   - Check firewall settings for port 8080

2. **Queue jobs not processing:**
   - Restart queue workers in Forge
   - Check database queue table
   - Verify queue configuration

3. **File permissions:**
   ```bash
   sudo chown -R forge:www-data storage
   sudo chmod -R 775 storage
   ```

4. **Missing Node.js:**
   ```bash
   # Install Node.js on server
   curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
   sudo apt-get install -y nodejs
   ```

### Useful Commands

```bash
# Check queue status
php artisan queue:work --once

# Check Reverb status
php artisan reverb:start --debug

# Clear all caches
php artisan optimize:clear

# View application logs
tail -f storage/logs/laravel.log

# Check process status
sudo supervisorctl status
```

## Security Considerations

1. **Environment variables:**
   - Never commit `.env` files
   - Use strong, unique keys
   - Rotate secrets regularly

2. **File uploads:**
   - Current limit: 10MB CSV files
   - Adjust in production if needed

3. **Rate limiting:**
   - Consider adding rate limiting for uploads
   - Monitor for abuse

## Performance Optimization

1. **For high volume:**
   - Consider Redis for queues
   - Implement queue batching
   - Add file size limits

2. **Database optimization:**
   - Regular cleanup of old jobs
   - Consider MySQL for larger datasets

3. **Asset optimization:**
   - CDN for static assets
   - Enable gzip compression