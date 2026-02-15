<?php

namespace Deployer;

require 'recipe/symfony.php';

// =============================================================================
// Project Configuration
// =============================================================================

set('application', 'honeyguide-projects');
set('repository', 'git@github.com:flexhosting-dev/honeyguide-projects.git');

// Default branch to deploy
set('branch', 'liveApp');

// Keep last 5 releases for rollback
set('keep_releases', 5);

// Disable multiplexing for stability
set('ssh_multiplexing', false);

// PHP binary path
set('bin/php', '/usr/bin/php8.3');

// Composer options for production
set('composer_options', '--no-dev --optimize-autoloader --no-interaction');

// Allow composer to run as root
set('env', [
    'COMPOSER_ALLOW_SUPERUSER' => '1',
]);

// =============================================================================
// Shared Files & Directories
// =============================================================================

// Files shared between releases (symlinked from shared/)
add('shared_files', [
    '.env.local',
    '.env.local.php',
]);

// Directories shared between releases (symlinked from shared/)
add('shared_dirs', [
    'var/log',
    'public/uploads',
]);

// Directories that need to be writable
add('writable_dirs', [
    'var',
    'var/cache',
    'var/log',
    'public/uploads',
]);

set('writable_mode', 'chmod');

// =============================================================================
// Hosts Configuration
// =============================================================================

host('production')
    ->setHostname('193.187.129.7')
    ->setRemoteUser('root')
    ->setDeployPath('/var/www/honeyguide-projects')
    ->set('branch', 'liveApp')
    ->set('http_user', 'www-data')
    ->setSshArguments([
        '-o StrictHostKeyChecking=no',
    ]);

// =============================================================================
// Tasks
// =============================================================================

// Database backup before migration
desc('Backup database before deploy');
task('database:backup', function () {
    $backupDir = '/var/backups/honeyguide-projects';
    $dbName = 'honeyguide_projects';
    $dbUser = 'honeyguide';
    $dbPass = 'HoneyguideApp2024Secure';
    $timestamp = date('Ymd_His');
    $backupFile = "{$backupDir}/db_{$dbName}_{$timestamp}.sql.gz";

    run("mysqldump -u{$dbUser} -p{$dbPass} {$dbName} 2>/dev/null | gzip > {$backupFile}");
    writeln("<info>Database backed up to: {$backupFile}</info>");

    // Clean old backups (keep last 10)
    run("cd {$backupDir} && ls -t db_*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -- 2>/dev/null || true");
});

// Dump environment for production
desc('Dump environment for production');
task('deploy:dump-env', function () {
    cd('{{release_path}}');
    run('COMPOSER_ALLOW_SUPERUSER=1 {{bin/composer}} dump-env prod');
});

// Install importmap packages
desc('Install importmap packages');
task('deploy:importmap', function () {
    cd('{{release_path}}');
    run('{{bin/php}} {{bin/console}} importmap:install --no-interaction');
});

// Compile assets for production
desc('Compile assets for production');
task('deploy:assets:compile', function () {
    cd('{{release_path}}');
    run('{{bin/php}} {{bin/console}} asset-map:compile --no-interaction');

    // Create symlinks from unhashed to hashed filenames
    // This is needed because compiled JS files use relative imports that
    // browsers may not resolve through the importmap correctly
    run('cd {{release_path}}/public/assets && find . -name "*-*.js" -type f | while read f; do
        base=$(echo "$f" | sed "s/-[a-f0-9]*\\.js/.js/")
        [ ! -e "$base" ] && ln -sf "$(basename $f)" "$base"
    done');
    run('cd {{release_path}}/public/assets && find . -name "*-*.css" -type f | while read f; do
        base=$(echo "$f" | sed "s/-[a-f0-9]*\\.css/.css/")
        [ ! -e "$base" ] && ln -sf "$(basename $f)" "$base"
    done');
});

// Restart PHP-FPM
desc('Restart PHP-FPM');
task('php-fpm:restart', function () {
    run('systemctl restart php8.3-fpm');
});

// Fix ownership after deploy
desc('Fix file ownership');
task('deploy:ownership', function () {
    run('chown -R www-data:www-data {{release_path}}');
    run('chown -R www-data:www-data {{deploy_path}}/shared');
});

// =============================================================================
// Deployment Flow
// =============================================================================

// Backup database before deploy starts
before('deploy:prepare', 'database:backup');

// Dump env after vendors are installed (so composer plugins are available)
after('deploy:vendors', 'deploy:dump-env');

// Install importmap packages after env dump
after('deploy:dump-env', 'deploy:importmap');

// Compile assets after importmap
after('deploy:importmap', 'deploy:assets:compile');

// Fix ownership after cache clear (cache is created by root, needs www-data ownership)
after('deploy:cache:clear', 'deploy:ownership');

// Restart PHP-FPM after symlink switch
after('deploy:symlink', 'php-fpm:restart');

// Unlock on failure
after('deploy:failed', 'deploy:unlock');

// =============================================================================
// Rollback
// =============================================================================

// Restart PHP-FPM after rollback too
after('rollback', 'php-fpm:restart');
