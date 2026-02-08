<?php

namespace Deployer;

require 'recipe/symfony.php';

// =============================================================================
// Project Configuration
// =============================================================================

set('application', 'workflow');
set('repository', 'git@github.com:YOUR_USERNAME/workflow.git'); // TODO: Update this

// Default branch to deploy
set('branch', 'main');

// Keep last 5 releases for rollback
set('keep_releases', 5);

// =============================================================================
// Shared Files & Directories
// =============================================================================

// Files shared between releases (symlinked from shared/)
add('shared_files', [
    '.env.local',
]);

// Directories shared between releases (symlinked from shared/)
add('shared_dirs', [
    'var/uploads',
    'public/uploads',
]);

// Directories that need to be writable
add('writable_dirs', [
    'var/uploads',
    'public/uploads',
]);

// =============================================================================
// Hosts Configuration
// =============================================================================

host('production')
    ->set('hostname', 'your-server.com')          // TODO: Update this
    ->set('remote_user', 'deploy')                 // TODO: Update this
    ->set('deploy_path', '/var/www/workflow')
    ->set('branch', 'main')
    ->set('http_user', 'www-data');

// Uncomment for staging server
// host('staging')
//     ->set('hostname', 'staging.your-server.com')
//     ->set('remote_user', 'deploy')
//     ->set('deploy_path', '/var/www/workflow-staging')
//     ->set('branch', 'develop')
//     ->set('http_user', 'www-data');

// =============================================================================
// Tasks
// =============================================================================

// Install importmap packages
desc('Install importmap packages');
task('deploy:importmap', function () {
    run('cd {{release_path}} && {{bin/console}} importmap:install {{console_options}}');
});

// Compile assets for production
desc('Compile assets for production');
task('deploy:assets:compile', function () {
    run('cd {{release_path}} && {{bin/console}} asset-map:compile {{console_options}}');
});

// Run database migrations
desc('Run database migrations');
task('deploy:migrate', function () {
    run('cd {{release_path}} && {{bin/console}} doctrine:migrations:migrate --allow-no-migration {{console_options}}');
});

// Database backup before migration (optional)
desc('Backup database before migration');
task('database:backup', function () {
    $backupDir = '{{deploy_path}}/shared/backups';
    run("mkdir -p {$backupDir}");
    writeln('<comment>Database backup would run here - configure based on your DB setup</comment>');
    // Example for MySQL:
    // run("mysqldump -u \$DB_USER -p\$DB_PASS \$DB_NAME > {$backupDir}/backup-$(date +%Y%m%d-%H%M%S).sql");
});

// Notify on deployment
desc('Send deployment notification');
task('notify:success', function () {
    writeln('<info>Deployment successful!</info>');
});

// =============================================================================
// Deployment Flow
// =============================================================================

// Install importmap packages after composer
after('deploy:vendors', 'deploy:importmap');

// Compile assets after importmap
after('deploy:importmap', 'deploy:assets:compile');

// Run migrations after cache clear
after('deploy:cache:clear', 'deploy:migrate');

// Notify on success
after('deploy:success', 'notify:success');

// Unlock on failure
after('deploy:failed', 'deploy:unlock');

// =============================================================================
// Custom Commands
// =============================================================================

// Clear OPcache after symlink switch
desc('Clear OPcache');
task('opcache:clear', function () {
    run('{{bin/console}} cache:pool:clear cache.global_clearer {{console_options}} 2>/dev/null || true');
});

after('deploy:symlink', 'opcache:clear');

// SSH into server
desc('Connect to server via SSH');
task('ssh', function () {
    run('cd {{deploy_path}} && $SHELL');
});
