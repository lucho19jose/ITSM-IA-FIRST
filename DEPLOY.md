# Chuyma ITSM — Deployment Guide (Ubuntu 24.04 VPS)

Server: Ubuntu 24.04 LTS + Nginx + PHP 8.2-FPM + MySQL 8.0 (native, already installed)

> **Important:** This guide installs PHP 8.2 **alongside** your existing PHP 8.1.
> Your other Laravel 10 project keeps using `php8.1-fpm` — no conflicts.

## 1. Server Preparation

```bash
sudo apt update && sudo apt upgrade -y
```

### Install PHP 8.2 alongside PHP 8.1

PHP 8.2 installs as a separate package. Your existing PHP 8.1 stays untouched.

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
  php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl php8.2-redis
```

After install, both FPM services run independently:
- `php8.1-fpm` → your existing Laravel 10 project (unchanged)
- `php8.2-fpm` → Chuyma (new)

```bash
# Verify both are running
sudo systemctl status php8.1-fpm   # existing project
sudo systemctl status php8.2-fpm   # Chuyma
```

> **Do NOT change the system default PHP CLI.** If you need to run artisan
> commands for Chuyma specifically, use: `php8.2 artisan ...`

### Install Node.js 22

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

### Verify Versions

```bash
php8.2 -v      # Should show 8.2.x
php8.1 -v      # Should still show 8.1.x (existing)
node -v         # Should show v22.x
composer -V     # Should show 2.x
mysql -V        # Should show 8.0.x (already installed)
nginx -v        # Should show 1.24+
```

## 2. Create MySQL Database

```bash
sudo mysql -u root
```

```sql
CREATE DATABASE autoservice_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'autoservice'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON autoservice_db.* TO 'autoservice'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Create Application User

```bash
sudo useradd -m -s /bin/bash autoservice
sudo mkdir -p /var/www/autoservice
sudo chown autoservice:autoservice /var/www/autoservice
```

## 4. Clone the Repository

```bash
sudo su - autoservice
cd /var/www/autoservice
git clone https://github.com/lucho19jose/ITSM-IA-FIRST.git .
```

## 5. Backend Setup

```bash
cd /var/www/autoservice/backend

# Install dependencies (no dev)
# Use php8.2 explicitly (your system default may still be 8.1)
composer install --no-dev --optimize-autoloader --ignore-platform-req=php

# Copy and configure .env
cp .env.example .env
```

### Edit `.env` — Replace these values:

```env
APP_NAME=Chuyma
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Lima
APP_URL=https://yourdomain.com
FRONTEND_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=autoservice_db
DB_USERNAME=autoservice
DB_PASSWORD=YOUR_STRONG_PASSWORD_HERE

QUEUE_CONNECTION=database
CACHE_STORE=file

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Chuyma"

ANTHROPIC_API_KEY=sk-ant-xxxxx
```

### Generate Key + Run Migrations

```bash
php8.2 artisan key:generate
php8.2 artisan migrate --force
php8.2 artisan db:seed --force
php8.2 artisan passport:install --force

# Optimize for production
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache

# Storage link
php8.2 artisan storage:link

# Set permissions
chmod -R 775 storage bootstrap/cache
```

## 6. Frontend Build

```bash
cd /var/www/autoservice/frontend
npm ci
```

### Create/edit `.env.production`:

```env
VITE_API_URL=https://yourdomain.com
VITE_APP_NAME=Chuyma
```

```bash
npm run build
```

The build output goes to `frontend/dist/`.

## 7. Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/autoservice
```

Paste this config:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    # SSL (Certbot will fill these)
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Frontend (Vue SPA)
    root /var/www/autoservice/frontend/dist;
    index index.html;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 1000;

    # API requests → Laravel
    location /api {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # OAuth requests → Laravel
    location /oauth {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Storage (uploaded files)
    location /storage {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
    }

    # Health check
    location /up {
        proxy_pass http://127.0.0.1:8080;
    }

    # SPA fallback — all other routes serve index.html
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Static assets caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Max upload size
    client_max_body_size 20M;
}
```

### Laravel PHP-FPM vhost (internal only):

```bash
sudo nano /etc/nginx/sites-available/autoservice-api
```

```nginx
server {
    listen 8080;
    server_name 127.0.0.1;

    root /var/www/autoservice/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
```

### Enable sites:

```bash
sudo ln -s /etc/nginx/sites-available/autoservice /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/autoservice-api /etc/nginx/sites-enabled/

# NOTE: Do NOT remove /etc/nginx/sites-enabled/default if your other
# Laravel project uses it. Only remove if no other site depends on it.

sudo nginx -t
sudo systemctl reload nginx
```

## 8. SSL Certificate (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

## 9. Queue Worker (systemd)

```bash
sudo nano /etc/systemd/system/autoservice-worker.service
```

```ini
[Unit]
Description=Chuyma Queue Worker
After=network.target mysql.service

[Service]
User=autoservice
Group=autoservice
WorkingDirectory=/var/www/autoservice/backend
ExecStart=/usr/bin/php8.2 artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable autoservice-worker
sudo systemctl start autoservice-worker
```

## 10. Scheduled Tasks (Cron)

```bash
sudo crontab -u autoservice -e
```

Add:

```
* * * * * cd /var/www/autoservice/backend && php8.2 artisan schedule:run >> /dev/null 2>&1
```

## 11. Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## 12. Post-Deploy Verification

```bash
# Check services
sudo systemctl status php8.2-fpm
sudo systemctl status nginx
sudo systemctl status mysql
sudo systemctl status autoservice-worker

# Check health endpoint
curl -s https://yourdomain.com/up

# Check API
curl -s https://yourdomain.com/api/v1/auth/login \
  -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.com","password":"password"}'

# Check rate limiting headers
curl -sI https://yourdomain.com/api/v1/auth/login \
  -X POST -H "Content-Type: application/json" \
  -d '{"email":"x@x.com","password":"x"}' | grep X-RateLimit
```

## Updating (after git push)

```bash
cd /var/www/autoservice

# Pull changes
git pull origin main

# Backend
cd backend
composer install --no-dev --optimize-autoloader
php8.2 artisan migrate --force
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache
sudo systemctl restart autoservice-worker

# Frontend
cd ../frontend
npm ci
npm run build

# Reload
sudo systemctl reload nginx
```

## Coexistence with Other Laravel Projects

Your existing Laravel 10 project on this VPS keeps working as-is. Just make sure its Nginx config uses `php8.1-fpm`:

```nginx
# In your OTHER project's Nginx config, verify this line:
fastcgi_pass unix:/run/php/php8.1-fpm.sock;
```

Chuyma uses `php8.2-fpm.sock` in its own config — no overlap.

## Troubleshooting

| Issue | Fix |
|-------|-----|
| 502 Bad Gateway | `sudo systemctl restart php8.2-fpm` |
| Permission denied (storage) | `chmod -R 775 backend/storage backend/bootstrap/cache` |
| Queue not processing | `sudo systemctl restart autoservice-worker` |
| Check Laravel logs | `tail -f backend/storage/logs/laravel.log` |
| Check Nginx logs | `sudo tail -f /var/log/nginx/error.log` |
