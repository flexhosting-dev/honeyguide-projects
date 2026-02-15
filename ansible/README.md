# Ansible Deployment for Honeyguide Projects

Automated server provisioning and deployment using Ansible.

## Prerequisites

1. Install Ansible on your local machine:
   ```bash
   # macOS
   brew install ansible

   # Ubuntu/Debian
   sudo apt install ansible

   # pip
   pip install ansible
   ```

2. A fresh Ubuntu 22.04+ server with SSH access

3. SSH key-based authentication set up

## Quick Start

### 1. Configure inventory

Edit `inventory.yml` with your server IP:

```yaml
all:
  hosts:
    production:
      ansible_host: YOUR_SERVER_IP
      ansible_user: root
```

### 2. Set environment variables

Create a `.env.ansible` file (DO NOT commit this):

```bash
export DOMAIN=projects.yourdomain.com
export DB_PASSWORD=$(openssl rand -base64 32)
export MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
export APP_SECRET=$(php -r "echo bin2hex(random_bytes(16));")
export JWT_PASSPHRASE=$(openssl rand -base64 32)
export MAILER_DSN=smtp://user:pass@smtp.example.com:587
export GOOGLE_CLIENT_ID=your_google_client_id
export GOOGLE_CLIENT_SECRET=your_google_client_secret
export GOOGLE_AUTH_ENABLED=true
```

Source it before running Ansible:
```bash
source .env.ansible
```

### 3. Update git repository URL

Edit `group_vars/all.yml` and set your git repository:

```yaml
git_repo: "git@github.com:yourusername/zohoclone.git"
```

### 4. Run the playbook

**First-time provisioning (full setup):**
```bash
ansible-playbook -i inventory.yml playbook.yml
```

**Subsequent deployments (code updates only):**
```bash
ansible-playbook -i inventory.yml deploy.yml
```

## What Gets Installed

- **Nginx** - Web server with optimized config for Symfony
- **PHP 8.3-FPM** - With all required extensions
- **MySQL 8.0** - Database server
- **Composer** - PHP dependency manager
- **Let's Encrypt** - Free SSL certificate (auto-renewal)
- **UFW** - Firewall (ports 22, 80, 443)
- **Fail2ban** - Intrusion prevention

## Directory Structure

```
/var/www/honeyguide-projects/
├── bin/
├── config/
├── public/           # Web root
├── src/
├── var/              # Cache, logs
├── vendor/
└── .env              # Environment config
```

## Common Tasks

### SSH into server
```bash
ssh root@YOUR_SERVER_IP
```

### View application logs
```bash
tail -f /var/www/honeyguide-projects/var/log/prod.log
```

### View PHP-FPM logs
```bash
tail -f /var/log/php/honeyguide-projects_error.log
```

### View Nginx logs
```bash
tail -f /var/log/nginx/honeyguide-projects_access.log
tail -f /var/log/nginx/honeyguide-projects_error.log
```

### Manually clear cache
```bash
cd /var/www/honeyguide-projects
sudo -u www-data php bin/console cache:clear --env=prod
```

### Run migrations manually
```bash
cd /var/www/honeyguide-projects
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction
```

### Restart services
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
sudo systemctl restart mysql
```

## Staging Environment

To set up a staging server, add to `inventory.yml`:

```yaml
all:
  hosts:
    production:
      ansible_host: PROD_IP
    staging:
      ansible_host: STAGING_IP
```

Create `group_vars/staging.yml`:
```yaml
app_domain: staging.yourdomain.com
app_env: dev
app_debug: true
git_branch: develop
```

Deploy to staging only:
```bash
ansible-playbook -i inventory.yml playbook.yml --limit staging
```

## Troubleshooting

### Permission issues
```bash
sudo chown -R www-data:www-data /var/www/honeyguide-projects/var
sudo chmod -R 775 /var/www/honeyguide-projects/var
```

### SSL certificate issues
```bash
sudo certbot --nginx -d yourdomain.com
```

### PHP-FPM not starting
```bash
sudo journalctl -u php8.3-fpm -f
```
