<?php

namespace Deployer;

require_once 'deployment/rsync.php';
require_once 'recipe/magento2.php';

set('application', '     Magento2 Shop');

set('composer_options', implode(' ', [
    '{{composer_action}}',
    '--no-dev',
    '--ansi',
    '--no-interaction',
    '--prefer-dist',
    '--optimize-autoloader',
    '--ignore-platform-reqs'
]));

// Hosts


host('production')
    ->hostname('hostname')
    ->user('user')
    ->addSshOption('UserKnownHostsFile', __DIR__ . '/.ssh/known_hosts')
    ->stage('production')
    ->forwardAgent(true)
    ->set('deploy_path', 'path here');

// Data is transferred via rsync because we run the build on the Bamboo agent, not on the final system
set('rsync_src', __DIR__);
set('rsync_dest', '{{release_path}}');
set('rsync', [
    'exclude' => [
        'deploy.php',
    ],
    'exclude-file' => false,
    'include' => [],
    'include-file' => false,
    'filter' => [],
    'filter-file' => false,
    'filter-perdir' => false,
    // use "a" instead of "r" to get symlinks transferred
    'flags' => 'az',
    'options' => ['delete'],
    'timeout' => 300,
]);

set('shared_dirs', array_merge(
    get('shared_dirs'),
    [
        // this folder is used for uploads by the client
        'var/import'
    ]
));

set('shared_files', [
    '.env',
    'app/etc/config.php',
    'var/.maintenance.ip',
]);

// Composer is run on the Bamboo agent because we don't want to fiddle around with SSH keys that have access to Stash
// on all the stage and production systems
task('vendors', function() {
    run('./composer config http-basic.repo.magento.com b1e9485d8fa6c7acab317a7a14618cbd e33e9c6839ec5fe10e6f68fc304f4375');

    run('./composer -vvv {{composer_options}}');
})->local();



// redefined here to add parameters from the old deployment script that are not present in the original task from the recipe
task('magento:deploy:assets', function () {
    run("{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy de_DE -f -t Bootstrap/project");
});

task('magento:reindex', function () {
    run('{{bin/php}} {{release_path}}/bin/magento indexer:reindex');
});

task('magento:upgrade', function () {
    run('{{bin/php}} {{release_path}}/bin/magento setup:upgrade');
});

task('magento:init-deployment-config', function () {
    if (!test('[ -s {{deploy_path}}/shared/app/etc/config.php ]')) {
        run('{{bin/php}} {{release_path}}/bin/magento module:enable --all');
    }
});

task('magento:crontab-update', function () {
    if (test('[ -L {{deploy_path}}/current ]')) {
        run("{{bin/php}} {{current_path}}/bin/magento cron:remove");
    }
    run("{{bin/php}} {{release_path}}/bin/magento cron:install");
});



desc('Enable maintenance mode');
task('magento:maintenance:enable', function () {
    run("if [ -d $(echo {{deploy_path}}/current) ]; then {{bin/php}} {{deploy_path}}/current/bin/magento maintenance:enable; fi");
});

desc('Disable maintenance mode');
task('magento:maintenance:disable', function () {
    run("if [ -d $(echo {{deploy_path}}/current) ]; then {{bin/php}} {{deploy_path}}/current/bin/magento maintenance:disable; fi");
});

task('deploy:magento', [
    'magento:init-deployment-config',
    'magento:upgrade',
    'magento:compile',
    'magento:deploy:assets',
   
    'magento:crontab-update',
    'magento:upgrade:db',
    'magento:reindex',
    'magento:cache:flush'
])->desc('Magento2 deployment operations');

task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'vendors',
    'rsync',
    'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:magento',
    'frontend-build',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
])->desc('Deploy your project');
after('deploy', 'success');

fail('deploy', 'deploy:failed');

after('deploy:failed', 'deploy:unlock');
