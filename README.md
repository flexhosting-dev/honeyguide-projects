# Honeyguide Projects - Project Management System

A project management system built with Symfony 7, featuring project management, task tracking, Kanban boards, and team collaboration.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+ or MariaDB 10.6+
- Redis (optional, for caching)
- Node.js 18+ (for asset compilation)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/flexhosting-dev/honeyguide-projects.git
cd honeyguide-projects
```

### 2. Install Dependencies

```bash
composer install
npm install
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
php bin/console doctrine:fixtures:load --no-interaction  # Optional: Load sample data
```

### 5. Build Assets

```bash
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

### Projects
- Create and manage team projects with descriptions and colors
- Personal project automatically created for each user
- Favourite projects for quick sidebar access
- Hide projects from main view (per-user preference)
- Recent projects tracked automatically
- Public and private visibility settings
- Project overview dashboard with key metrics

### Milestones
- Organize tasks into milestones with target dates
- Progress tracking per milestone
- Milestone targets and deadlines

### Tasks
- Full task management with rich text descriptions
- Priority levels (None, Low, Medium, High)
- Status workflow (To Do, In Progress, In Review, Completed)
- Start and due dates with overdue highlighting
- Multi-user task assignments
- Subtasks with unlimited nesting depth and parent chain navigation
- Task checklists with progress tracking
- Tags with customizable colors (price tag style UI)
- File attachments with drag-and-drop upload
- Comments with @mentions and rich text editor

### Kanban Board
- Vue.js powered drag-and-drop kanban
- Multiple grouping modes: Status, Priority, or Milestone
- Collapsible columns with smooth animations
- Quick-add cards for tasks and subtasks
- Smart input with `#assign` and `@date` shortcuts
- Per-card loading spinners during operations
- Automatic position persistence

### Task Panel
- Slide-out panel for quick task editing
- Inline editing for all fields
- Stacking navigation for subtasks
- Real-time sync with kanban board
- Activity log with lazy loading

### Task Filters
- Filter by status, priority, assignee, milestone
- Due date presets (Overdue, Today, This Week, Custom Range)
- Search by title and description
- URL-persisted filters for sharing
- Active filter chips with quick removal
- Alpine.js powered filter dropdowns

### My Tasks & All Tasks
- Dedicated pages for personal and team-wide task views
- Shared task components across views
- Assignee filtering

### Notifications
- In-app notification bell with unread count
- Task assignments, comments, @mentions, due dates
- Mark as read functionality
- Configurable notification preferences

### Team Collaboration
- Invite team members to projects
- Role-based access control (Owner, Manager, Member, Viewer)
- Granular permission system
- Project activity feed
- Comment threads with @mentions

### User Profile & Settings
- Profile management with avatar upload and cropping
- Notification preferences
- Centralized settings hub

### Admin Features
- User management
- Role management with customizable permissions
- Permission-based UI visibility

### Authentication
- Email/password login
- Google OAuth integration
- Remember me functionality
- Access denied handling

## Development

### Running Locally

```bash
# Start Symfony dev server
symfony serve

# Or use PHP built-in server
php -S localhost:8000 -t public/

# Or with Docker
docker-compose up -d
```

### Watch Assets

```bash
npm run watch
```

### Run Tests

```bash
php bin/phpunit
```

### Code Quality

```bash
# PHP CS Fixer
./vendor/bin/php-cs-fixer fix

# PHPStan static analysis
./vendor/bin/phpstan analyse
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

# Validate schema
php bin/console doctrine:schema:validate
```

### Permission Issues

```bash
chmod -R 775 var/
chown -R www-data:www-data var/
```

### Asset Issues

```bash
# Rebuild assets
rm -rf public/build/*
npm run build

# Clear asset cache
php bin/console assets:install
```

## Tech Stack

- **Backend**: Symfony 7, PHP 8.2+
- **Database**: MySQL/MariaDB with Doctrine ORM
- **Frontend**: Twig templates, Tailwind CSS, Vue.js components, Alpine.js
- **Assets**: Webpack Encore
- **Authentication**: Symfony Security with OAuth support

## License

MIT License
