# WorkFlow - Project Management System

A Zoho Projects clone built with Symfony 7, featuring project management, task tracking, Kanban boards, and team collaboration.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+ or MariaDB 10.6+
- Redis (optional, for caching)
- Node.js 18+ (for asset compilation)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/flexhosting-dev/ZohoClone.git
cd ZohoClone
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy the example environment file and configure it:

```bash
cp .env.example .env.local
```

Edit `.env.local` with your settings:

```env
# Symfony environment
APP_ENV=prod
APP_SECRET=your_generated_secret_here

# Database connection
DATABASE_URL="mysql://username:password@127.0.0.1:3306/workflow?serverVersion=10.11.13-MariaDB&charset=utf8mb4"

# Redis (optional)
REDIS_URL=redis://127.0.0.1:6379

# Mailer
MAILER_DSN=smtp://user:pass@smtp.example.com:587

# Trusted hosts (your domain)
TRUSTED_HOSTS='^(localhost|yourdomain\.com)$'
```

Generate an app secret:

```bash
php -r "echo bin2hex(random_bytes(16));"
```

### 4. Create Database

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Build Assets

```bash
npm install
npm run build
```

### 6. Set Permissions

```bash
chmod -R 775 var/
chown -R www-data:www-data var/
```

### 7. Clear Cache

```bash
php bin/console cache:clear --env=prod
```

## Web Server Configuration

### Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/workflow/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

### Apache

Ensure `mod_rewrite` is enabled. The `.htaccess` file in `public/` handles routing.

## Google OAuth Setup (Optional)

To enable "Sign in with Google":

### 1. Create Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Navigate to **APIs & Services > Credentials**
4. Click **Create Credentials > OAuth client ID**
5. Select **Web application**
6. Add authorized redirect URI: `https://yourdomain.com/connect/google/check`
7. Copy the Client ID and Client Secret

### 2. Configure Environment

Add to your `.env.local`:

```env
GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_AUTH_ENABLED=true
GOOGLE_ALLOWED_DOMAINS=
```

### 3. Domain Restriction (Optional)

To restrict login to specific email domains:

```env
# Single domain
GOOGLE_ALLOWED_DOMAINS=yourcompany.com

# Multiple domains (comma-separated)
GOOGLE_ALLOWED_DOMAINS=company1.com,company2.org
```

Leave empty to allow all Google accounts.

## Features

- **Projects**: Create and manage projects with descriptions, colors, and team members
- **Milestones**: Set project milestones with due dates
- **Tasks**: Create tasks with priorities, due dates, tags, checklists, and comments
- **Kanban Board**: Drag-and-drop task management by status or priority
- **Team Collaboration**: Assign tasks to team members, add comments
- **Activity Feed**: Track all project activities
- **Google OAuth**: Sign in with Google account

## Development

### Running Locally

```bash
# Start PHP dev server
symfony serve

# Or use PHP built-in server
php -S localhost:8000 -t public/
```

### Watch Assets

```bash
npm run watch
```

### Run Tests

```bash
php bin/phpunit
```

## Troubleshooting

### Cache Issues

```bash
php bin/console cache:clear
rm -rf var/cache/*
```

### Database Issues

```bash
# Check database connection
php bin/console doctrine:database:create --if-not-exists

# Re-run migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

### Permission Issues

```bash
chmod -R 775 var/
chown -R www-data:www-data var/
```

## License

MIT License
