# LiamKai App - Deployment Guide

## Current Status
✅ Frontend: Complete with 18 routes and all views  
✅ Backend: Laravel with Sanctum token authentication  
✅ Database: Configured with all migrations (including personal_access_tokens)  
✅ Authentication: Full working token-based auth

---

## Development Verification (Already Completed)

### Backend Auth Verified ✓
- `POST /api/login` returns JSON token and user data
- Test credentials: `test@example.com` / `password123`
- Sanctum trait and migrations in place

### Frontend Build Verified ✓
- `npm run build` successfully produces `public/build/assets/`
- Login page integrated with real auth store

---

## Production Deployment Steps

### Step 1: Prepare Environment Variables
```bash
# Copy .env.example to .env
cp .env.example .env

# Generate application key (if not already set)
php artisan key:generate

# Set production values in .env:
APP_NAME=LiamKaiApp
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database (update with production credentials)
DB_HOST=your-db-host
DB_DATABASE=liamkai_db
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

# Other critical settings
SESSION_DRIVER=cookie
CACHE_DRIVER=redis  # or file if redis unavailable
QUEUE_CONNECTION=sync
```

### Step 2: Install Dependencies
```bash
# Backend
composer install --no-dev --optimize-autoloader

# Frontend
npm install
npm run build

# Verify public/build assets exist
ls -la public/build/
```

### Step 3: Database Setup
```bash
# Run all migrations (creates all tables including personal_access_tokens)
php artisan migrate --force

# Seed initial data if seeders exist
php artisan db:seed --force

# Or create admin user manually
php artisan tinker
> User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('SecurePassword123!')])
```

### Step 4: Cache Configuration
```bash
# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear any old caches
php artisan cache:clear
php artisan config:clear  # Do this AFTER config:cache finishes
```

### Step 5: Server Configuration

#### Apache (.htaccess)
Ensure `public/.htaccess` exists with proper rewrite rules:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_friendlyurls.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [QSA,L]
    </IfModule>
</IfModule>
```

Verify DocumentRoot points to `public/` directory:
```apache
DocumentRoot /path/to/liamkai-app/public
```

#### Nginx
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    root /path/to/liamkai-app/public;
    index index.php;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
    }
}
```

### Step 6: File Permissions
```bash
# Set correct ownership
chown -R www-data:www-data /path/to/liamkai-app

# Set correct permissions
chmod -R 755 /path/to/liamkai-app
chmod -R 775 /path/to/liamkai-app/storage
chmod -R 775 /path/to/liamkai-app/bootstrap/cache
```

### Step 7: SSL/HTTPS
```bash
# Use Let's Encrypt (recommended, free)
# If using Certbot:
sudo certbot certonly --webroot -w /path/to/liamkai-app/public -d yourdomain.com

# Update nginx/apache config with certificate paths
# Force HTTPS redirect:
```

In `.env`:
```
APP_URL=https://yourdomain.com
```

In nginx, add redirect:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

### Step 8: Optional - Add Supervisor for Queue (if using jobs later)
```bash
sudo apt-get install supervisor

# Create /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/liamkai-app/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/liamkai-app/storage/logs/worker.log

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### Step 9: Backups & Monitoring
```bash
# Set up automated database backups (cron):
# Add to crontab: 0 2 * * * mysqldump -u user -ppass liamkai_db > /backups/db-$(date +\%Y\%m\%d-\%H\%M\%S).sql

# Monitor application logs
tail -f /path/to/liamkai-app/storage/logs/laravel.log
```

---

## Post-Deployment Testing

### Test Backend Auth Endpoint
```bash
# Login with test credentials
curl -X POST https://yourdomain.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"SecurePassword123!"}'

# Expected response:
# {"token":"1|xxx","user":{"id":1,"name":"Admin","email":"admin@example.com"}}

# Copy the token and test protected endpoint (example):
curl -X GET https://yourdomain.com/api/user \
  -H "Authorization: Bearer 1|xxx"
```

### Test Frontend
1. Navigate to `https://yourdomain.com`
2. You should see the login page (built Vue 3 SPA)
3. Login with your credentials
4. Should redirect to Dashboard
5. Sidebar navigation functional
6. Test a few page routes (Orders, Customers, Inventory, etc.)

---

## Key Application Architecture

### Frontend Structure
- **Framework**: Vue 3 (Composition API) + Vite
- **State**: Pinia auth store (`resources/js/stores/authStore.js`)
- **API Client**: Axios with auto Bearer token injection
- **Routes**: 18 app routes + auth guard
- **Styling**: Tailwind CSS + custom CSS

### Backend Structure
- **Framework**: Laravel 11 (API-first)
- **Auth**: Laravel Sanctum (token-based, stateless)
- **Database**: MySQL with migrations for all entities
- **CORS**: Enabled for frontend domain

### Key Files for Maintenance
```
/auth store:        resources/js/stores/authStore.js
/auth routes:       routes/api.php (lines with Route::post('/login'))
/auth logic:        app/Http/Controllers/AuthController.php
/database config:   .env (DB_* variables)
/frontend config:   resources/js/api/index.js (API baseURL)
/vite config:       vite.config.js (build output: public/build)
```

---

## Troubleshooting

### 401 Unauthorized on Protected Routes
- Check token is included in Authorization header: `Bearer <token>`
- Verify token not expired (check `personal_access_tokens` table)
- Confirm user exists and hasn't been deleted

### CORS Errors
- Update `config/cors.php` to include frontend domain
- Ensure `APP_URL` in `.env` matches your domain

### Frontend Not Loading
- Check `public/build/` directory exists with assets
- Verify webserver DocumentRoot points to `public/` (not root)
- Clear browser cache (Ctrl+Shift+Delete)

### Database Connection Error
- Verify MySQL running: `systemctl status mysql`
- Confirm `.env` database credentials
- Check database user has correct permissions

---

## Security Checklist
- [ ] Set `APP_DEBUG=false` in production `.env`
- [ ] Change default admin password
- [ ] Enable HTTPS/SSL
- [ ] Set strong database password
- [ ] Restrict file permissions (775 for storage/bootstrap)
- [ ] Review and update CORS allowed origins
- [ ] Enable rate limiting on auth endpoints
- [ ] Set up automated backups
- [ ] Monitor error logs regularly
- [ ] Keep Laravel/dependencies updated: `composer update`

---

## Support & Maintenance

### Run Migrations After Code Update
```bash
php artisan migrate --force
```

### Update Dependencies Safely
```bash
# Check for updates
composer outdated
npm outdated

# Update carefully (test in staging first)
composer update
npm update
npm run build
php artisan config:cache
```

### View Real-Time Logs
```bash
php artisan log:tail
# or
tail -f storage/logs/laravel.log
```

For urgent issues or deployment questions, refer to [Laravel Docs](https://laravel.com/docs) and [Sanctum Docs](https://laravel.com/docs/sanctum).
