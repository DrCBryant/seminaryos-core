# Shared Hosting Deployment Guide

## ICDSoft Hosting Environment

### Available Resources
- **PHP**: 8.4 with common extensions
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **SSH Access**: For deployment and maintenance
- **Cron Jobs**: For scheduled tasks
- **Composer**: For dependency management
- **Apache**: With mod_rewrite enabled
- **Memory Limit**: Typically 512MB-1GB per request
- **Execution Time**: 30-60 seconds per request

### Limitations
- No persistent processes (no daemons)
- No Redis or Memcached
- No Supervisor or process managers
- No Docker containers
- No root access
- Limited to Apache configuration via `.htaccess`

## Directory Structure on Shared Hosting

### Typical ICDSoft Layout

```
/home/username/
├── public_html/              # Web root (Apache DocumentRoot)
│   ├── .htaccess            # Laravel public/.htaccess
│   ├── index.php            # Laravel public/index.php
│   ├── favicon.ico
│   ├── robots.txt
│   └── storage/             # Symlink to ../seminaryos/storage/app/public
├── seminaryos/              # Laravel application (outside web root)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/              # Original Laravel public directory
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   ├── artisan
│   └── composer.json
├── logs/                     # Optional: Custom log directory
└── backups/                  # Optional: Database backups
```

### Security Benefits
- Application code outside web root
- `.env` file not web-accessible
- Only `public/` contents exposed to web
- Storage directory protected

## Initial Deployment Steps

### 1. Prepare Local Repository

```bash
# Ensure .gitignore is properly configured
cat > .gitignore << 'EOF'
/node_modules
/public/hot
/public/storage
/storage/*.key
/vendor
.env
.env.backup
.phpunit.result.cache
Homestead.json
Homestead.yaml
npm-debug.log
yarn-error.log
/.idea
/.vscode
EOF

# Commit all code
git add .
git commit -m "Initial SeminaryOS setup"
git push origin main
```

### 2. SSH into ICDSoft Server

```bash
ssh username@yourdomain.com
```

### 3. Clone Repository

```bash
cd ~
git clone https://github.com/yourusername/seminaryos.git seminaryos
cd seminaryos
```

### 4. Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Note: Node dependencies built locally, assets uploaded via git
```

### 5. Configure Environment

```bash
# Copy and edit environment file
cp .env.example .env
nano .env
```

**Key `.env` Settings for Shared Hosting:**

```env
APP_NAME=SeminaryOS
APP_ENV=production
APP_KEY=base64:GENERATE_THIS_WITH_ARTISAN
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DAILY_DAYS=7

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=username_seminaryos
DB_USERNAME=username_dbuser
DB_PASSWORD=secure_password

BROADCAST_DRIVER=log
CACHE_DRIVER=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 6. Generate Application Key

```bash
php artisan key:generate
```

### 7. Setup Database

```bash
# Run migrations
php artisan migrate --force

# Seed initial data
php artisan db:seed --force
```

### 8. Configure Public Directory

```bash
# Move to public_html
cd ~/public_html

# Backup existing files if any
mkdir -p ~/backup_public_html
mv * ~/backup_public_html/ 2>/dev/null || true

# Copy Laravel public files
cp -r ~/seminaryos/public/* .
cp ~/seminaryos/public/.htaccess .

# Update index.php paths
nano index.php
```

**Modified `index.php`:**

```php
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Adjust paths to point to application directory
require __DIR__.'/../seminaryos/vendor/autoload.php';

$app = require_once __DIR__.'/../seminaryos/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
```

### 9. Create Storage Symlink

```bash
cd ~/public_html
ln -s ../seminaryos/storage/app/public storage
```

### 10. Set Permissions

```bash
cd ~/seminaryos
chmod -R 755 storage bootstrap/cache
```

### 11. Optimize for Production

```bash
cd ~/seminaryos

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Cache events
php artisan event:cache
```

## Apache Configuration

### .htaccess in public_html

Laravel's default `.htaccess` works well:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Additional Security Headers

Add to `.htaccess`:

```apache
<IfModule mod_headers.c>
    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # HSTS (uncomment after testing HTTPS)
    # Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# Disable directory browsing
Options -Indexes

# Protect sensitive files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>
```

## Cron Job Configuration

### Access Cron Jobs in ICDSoft cPanel

1. Log into cPanel
2. Navigate to "Cron Jobs"
3. Add the following cron jobs

### Laravel Scheduler (Required)

Run every minute:

```cron
* * * * * cd /home/username/seminaryos && php artisan schedule:run >> /dev/null 2>&1
```

### Queue Worker (Required for Background Jobs)

Run every minute:

```cron
* * * * * cd /home/username/seminaryos && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

**Explanation:**
- `--stop-when-empty`: Exits when queue is empty (required for cron-based workers)
- `--max-time=50`: Stops after 50 seconds (before cron starts next job)
- Runs every minute to process queued jobs

### Database Backup (Optional)

Daily at 2 AM:

```cron
0 2 * * * cd /home/username/seminaryos && php artisan backup:run >> /home/username/logs/backup.log 2>&1
```

### Log Cleanup (Optional)

Weekly on Sunday at 3 AM:

```cron
0 3 * * 0 find /home/username/seminaryos/storage/logs -name "*.log" -mtime +30 -delete
```

## Queue Configuration

### Database Queue Driver

**config/queue.php** (already configured for database):

```php
'default' => env('QUEUE_CONNECTION', 'database'),

'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ],
],
```

### Queue Migration

Already included in Laravel, but verify:

```bash
php artisan queue:table
php artisan migrate
```

### Dispatching Jobs

```php
use App\Jobs\SendWelcomeEmail;

// Dispatch to queue
SendWelcomeEmail::dispatch($user);

// Dispatch with delay
SendWelcomeEmail::dispatch($user)->delay(now()->addMinutes(5));

// Dispatch to specific queue
SendWelcomeEmail::dispatch($user)->onQueue('emails');
```

## Cache Configuration

### Database Cache Driver

**config/cache.php**:

```php
'default' => env('CACHE_DRIVER', 'database'),

'stores' => [
    'database' => [
        'driver' => 'database',
        'table' => 'cache',
        'connection' => null,
        'lock_connection' => null,
    ],
],
```

### Cache Migration

```bash
php artisan cache:table
php artisan migrate
```

### Using Cache

```php
// Store in cache
Cache::put('key', 'value', now()->addHours(1));

// Retrieve from cache
$value = Cache::get('key');

// Remember pattern
$users = Cache::remember('users.all', 3600, function () {
    return User::all();
});
```

## Session Configuration

### Database Session Driver

**config/session.php**:

```php
'driver' => env('SESSION_DRIVER', 'database'),
'lifetime' => 120,
'expire_on_close' => false,
```

### Session Migration

```bash
php artisan session:table
php artisan migrate
```

## File Storage

### Local Disk Configuration

**config/filesystems.php**:

```php
'default' => env('FILESYSTEM_DISK', 'local'),

'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
        'throw' => false,
    ],

    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
        'throw' => false,
    ],
],
```

### Storage Usage

```php
use Illuminate\Support\Facades\Storage;

// Store file
Storage::disk('public')->put('avatars/user1.jpg', $fileContents);

// Get URL
$url = Storage::disk('public')->url('avatars/user1.jpg');
// Returns: https://yourdomain.com/storage/avatars/user1.jpg

// Delete file
Storage::disk('public')->delete('avatars/user1.jpg');
```

## Deployment Workflow

### Update Deployment Script

Create `deploy.sh` in repository root:

```bash
#!/bin/bash

echo "🚀 Deploying SeminaryOS..."

# Navigate to application directory
cd ~/seminaryos

# Enable maintenance mode
php artisan down

# Pull latest code
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and rebuild cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers (they'll restart on next cron run)
php artisan queue:restart

# Disable maintenance mode
php artisan up

echo "✅ Deployment complete!"
```

### Make Script Executable

```bash
chmod +x deploy.sh
```

### Deploy Updates

```bash
ssh username@yourdomain.com
cd ~/seminaryos
./deploy.sh
```

## Monitoring and Maintenance

### Log Monitoring

```bash
# View latest logs
tail -f ~/seminaryos/storage/logs/laravel.log

# View specific date
cat ~/seminaryos/storage/logs/laravel-2026-06-02.log
```

### Database Maintenance

```bash
# Optimize tables
php artisan db:optimize

# Clear old queue jobs
php artisan queue:prune-batches --hours=48
php artisan queue:prune-failed --hours=168
```

### Cache Maintenance

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Storage Cleanup

```bash
# Clear old temporary files
find ~/seminaryos/storage/app/temp -type f -mtime +7 -delete

# Clear old logs
find ~/seminaryos/storage/logs -name "*.log" -mtime +30 -delete
```

## Troubleshooting

### Common Issues

#### 1. 500 Internal Server Error

**Check:**
- `.env` file exists and is configured
- `APP_KEY` is set
- Database credentials are correct
- Storage and cache directories are writable

**Fix:**
```bash
chmod -R 755 storage bootstrap/cache
php artisan config:clear
php artisan cache:clear
```

#### 2. Routes Not Working

**Check:**
- `.htaccess` file exists in `public_html`
- `mod_rewrite` is enabled
- Route cache is cleared

**Fix:**
```bash
php artisan route:clear
php artisan route:cache
```

#### 3. Assets Not Loading

**Check:**
- Storage symlink exists
- Asset paths in `.env` are correct
- File permissions

**Fix:**
```bash
cd ~/public_html
rm storage
ln -s ../seminaryos/storage/app/public storage
```

#### 4. Queue Jobs Not Processing

**Check:**
- Cron job is configured
- Queue connection is `database`
- Jobs table exists

**Fix:**
```bash
php artisan queue:table
php artisan migrate
# Verify cron job in cPanel
```

#### 5. Memory Limit Exceeded

**Check:**
- PHP memory limit in cPanel
- Large file uploads
- Inefficient queries

**Fix:**
- Optimize queries
- Use chunking for large datasets
- Request memory limit increase from host

### Debug Mode (Temporarily)

```bash
# Enable debug mode
nano .env
# Set APP_DEBUG=true

# View detailed errors in browser

# IMPORTANT: Disable after debugging
# Set APP_DEBUG=false
```

## Performance Optimization

### OPcache Configuration

Request ICDSoft to enable OPcache with these settings:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### Database Optimization

```sql
-- Add indexes to frequently queried columns
CREATE INDEX idx_institution_status ON table_name(institution_id, status);

-- Optimize tables regularly
OPTIMIZE TABLE users, institutions, jobs, cache;
```

### Application Optimization

```php
// Use eager loading
$programs = AcademicProgram::with('institution', 'courses')->get();

// Use select to limit columns
$programs = AcademicProgram::select('id', 'name', 'code')->get();

// Cache expensive queries
$stats = Cache::remember('dashboard.stats', 3600, function () {
    return [
        'users' => User::count(),
        'students' => Student::count(),
        'programs' => AcademicProgram::count(),
    ];
});
```

## Security Checklist

- [ ] `.env` file outside web root
- [ ] `APP_DEBUG=false` in production
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secured
- [ ] HTTPS enabled with valid SSL certificate
- [ ] Security headers configured in `.htaccess`
- [ ] File upload validation implemented
- [ ] CSRF protection enabled
- [ ] SQL injection prevention via Eloquent
- [ ] XSS protection via Blade escaping
- [ ] Regular backups configured
- [ ] Error logs monitored
- [ ] Failed login attempts tracked

## Backup Strategy

### Database Backup

```bash
# Manual backup
mysqldump -u username -p database_name > ~/backups/db_$(date +%Y%m%d_%H%M%S).sql

# Automated via cron (daily at 2 AM)
0 2 * * * mysqldump -u username -p'password' database_name | gzip > ~/backups/db_$(date +\%Y\%m\%d).sql.gz
```

### File Backup

```bash
# Backup storage directory
tar -czf ~/backups/storage_$(date +%Y%m%d).tar.gz -C ~/seminaryos storage/app

# Backup entire application (excluding vendor)
tar -czf ~/backups/app_$(date +%Y%m%d).tar.gz \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage/logs' \
    -C ~ seminaryos
```

### Backup Retention

```bash
# Keep last 7 daily backups
find ~/backups -name "db_*.sql.gz" -mtime +7 -delete
find ~/backups -name "storage_*.tar.gz" -mtime +7 -delete
```

---

**Document Version**: 1.0  
**Last Updated**: 2026-06-02  
**Status**: Draft for Review
