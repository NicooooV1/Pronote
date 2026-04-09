# Deployment Guide

## Prerequisites

| Software | Version | Required |
|----------|---------|----------|
| PHP | 8.1+ | Yes |
| MySQL | 5.7+ / MariaDB 10.3+ | Yes |
| Apache | 2.4+ with `mod_rewrite` | Yes |
| Node.js | 18+ | Optional (WebSocket) |

### Required PHP Extensions

`pdo_mysql`, `mbstring`, `json`, `openssl`, `intl`, `gd`, `curl`, `fileinfo`, `zip`

Check with:
```bash
php -m | grep -E "pdo_mysql|mbstring|json|openssl|intl|gd|curl|fileinfo|zip"
```

## Installation

### Option A: Web Installer

1. Upload the project files to your web server
2. Navigate to `http://your-domain/install.php`
3. Follow the wizard (database creation, admin account, `.env` configuration)

### Option B: Manual

```bash
# 1. Clone or extract the project
git clone https://github.com/your-org/fronote.git /var/www/fronote

# 2. Import the database
mysql -u root -p < pronote.sql

# 3. Configure environment
cp .env.example .env
# Edit .env with your database credentials and settings

# 4. Set permissions
chmod -R 755 /var/www/fronote
chmod -R 775 storage/ uploads/ logs/
chown -R www-data:www-data storage/ uploads/ logs/

# 5. Create the install lock
echo $(date +%Y-%m-%d) > install.lock
```

## Environment Configuration (.env)

### Database

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=fronote
DB_USERNAME=fronote_user
DB_PASSWORD=secure_password
DB_CHARSET=utf8mb4
```

### Application

```env
APP_ENV=production          # production | staging | development
APP_DEBUG=false             # true shows stack traces (NEVER in production)
APP_URL=https://fronote.example.com
APP_TIMEZONE=Europe/Paris
```

### Security

```env
SESSION_LIFETIME=1800       # 30 minutes
CSRF_LIFETIME=3600          # 1 hour
RATE_LIMIT_LOGIN=5          # Max login attempts before lockout
AUDIT_RETENTION_DAYS=90     # How long to keep audit logs
```

### WebSocket

```env
WS_ENABLED=true
WS_HOST=0.0.0.0
WS_PORT=3000
WS_JWT_SECRET=your-secret-key-here
WSS_CERT_PATH=/etc/ssl/certs/fronote.pem    # For WSS (production)
WSS_KEY_PATH=/etc/ssl/private/fronote.key
```

### Backups

```env
BACKUP_RETENTION=5          # Number of backups to keep
BACKUP_PATH=storage/backups
```

## Apache Configuration

```apache
<VirtualHost *:443>
    ServerName fronote.example.com
    DocumentRoot /var/www/fronote

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/fronote.pem
    SSLCertificateKeyFile /etc/ssl/private/fronote.key

    <Directory /var/www/fronote>
        AllowOverride All
        Require all granted
    </Directory>

    # Block direct access to sensitive directories
    <DirectoryMatch "^/var/www/fronote/(API|storage|logs|migrations|cron)">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

Ensure `mod_rewrite` is enabled:
```bash
sudo a2enmod rewrite ssl
sudo systemctl restart apache2
```

## WebSocket Server

### Using PM2 (recommended)

```bash
cd websocket/
npm install

# Start with PM2
pm2 start server.js --name fronote-ws
pm2 save
pm2 startup
```

### Using systemd

Create `/etc/systemd/system/fronote-ws.service`:

```ini
[Unit]
Description=Fronote WebSocket Server
After=network.target

[Service]
ExecStart=/usr/bin/node /var/www/fronote/websocket/server.js
WorkingDirectory=/var/www/fronote/websocket
Restart=always
User=www-data
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable fronote-ws
sudo systemctl start fronote-ws
```

## Cron Jobs

Add to crontab (`crontab -e`):

```cron
# Daily maintenance at 2:00 AM
0 2 * * * php /var/www/fronote/cron/daily_maintenance.php >> /var/www/fronote/logs/cron.log 2>&1

# Hourly maintenance
0 * * * * php /var/www/fronote/cron/hourly_maintenance.php >> /var/www/fronote/logs/cron_hourly.log 2>&1
```

### What the crons do

**Daily** (02:00):
- Audit log cleanup (respects `AUDIT_RETENTION_DAYS`)
- Database backup + rotation
- Cache garbage collection
- API token purge
- Rate limit cleanup
- Temp file cleanup (> 24h)
- Expired session purge
- Old notification purge (> 90 days read)
- Orphan upload cleanup
- Translation coverage report

**Hourly**:
- Cache GC
- Health check refresh
- Disk space monitoring (warns at > 90%)
- Rate limit cleanup

## Maintenance Mode

### Via Admin Panel

Navigate to `admin/systeme/maintenance.php` to toggle maintenance mode, set a custom message, and whitelist IP addresses.

### Via CLI

Create `storage/maintenance.json`:

```json
{
    "active": true,
    "message": "Mise a jour en cours. Retour prevu dans 30 minutes.",
    "allowed_ips": ["192.168.1.100"],
    "eta_minutes": 30
}
```

Remove the file or set `"active": false` to disable.

## Backups

### Automatic

The daily cron runs `BackupService::createDatabaseBackup()` which creates a SQL dump in `storage/backups/`. Old backups are rotated based on `BACKUP_RETENTION`.

### Manual

```bash
# Database backup
mysqldump -u fronote_user -p fronote > backup_$(date +%Y%m%d).sql

# Full file backup
tar -czf fronote_files_$(date +%Y%m%d).tar.gz \
    --exclude='storage/backups' \
    --exclude='storage/tmp' \
    --exclude='uploads/tmp' \
    /var/www/fronote
```

## Monitoring

### Health Endpoint

```
GET /API/endpoints/health.php
```

Returns JSON with status of: database, disk, cache, SMTP, WebSocket, PHP version.

### Admin Dashboard

`admin/systeme/monitoring.php` provides:
- Global health status
- Active sessions count
- Database size and table count
- Disk usage with visual indicator
- PHP extensions status
- Feature flags overview

### Log Files

```
logs/
├── cron.log            ← Daily maintenance output
├── cron_hourly.log     ← Hourly maintenance output
├── error.log           ← PHP errors (production)
└── audit.log           ← Security audit events
```

## HTTPS Configuration

In production, Fronote enforces:
- HSTS header (`Strict-Transport-Security`)
- Secure cookie flag on sessions
- WSS for WebSocket connections

Ensure your SSL certificate is valid and auto-renewed (e.g., via Let's Encrypt / certbot).

## Performance Tuning

### PHP

```ini
; php.ini recommendations
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0    ; Set to 1 in development
memory_limit=256M
upload_max_filesize=10M
post_max_size=12M
```

### MySQL

```ini
# my.cnf recommendations
innodb_buffer_pool_size=256M
innodb_log_file_size=64M
query_cache_type=1
query_cache_size=32M
max_connections=100
```

### Apache

Enable compression and caching for static assets:

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/css application/javascript
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>
```

## Updating Fronote

1. Enable maintenance mode
2. Backup database and files
3. Pull/extract new version
4. Run migrations: `php API/Commands/migrate.php up`
5. Clear cache
6. Disable maintenance mode
7. Verify health check: `GET /API/endpoints/health.php`
