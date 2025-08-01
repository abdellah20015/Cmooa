<?php

// Configuration base de données Railway avec force TCP/IP
$databases['default']['default'] = [
  'database' => $_ENV['MYSQL_DATABASE'] ?? 'railway',
  'username' => $_ENV['MYSQL_USER'] ?? 'root',
  'password' => $_ENV['MYSQL_PASSWORD'] ?? '',
  'host' => $_ENV['MYSQL_HOST'] ?? 'yamabiko.proxy.rlwy.net',
  'port' => $_ENV['MYSQL_PORT'] ?? '52955',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  // IMPORTANT: Forcer la connexion TCP/IP
  'init_commands' => [
    'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
  ],
  'pdo' => [
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE,
  ],
];

// Configuration spéciale pour Railway
if (isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['MYSQL_HOST'])) {
  $databases['default']['default']['host'] = $_ENV['MYSQL_HOST'];
  $databases['default']['default']['port'] = $_ENV['MYSQL_PORT'];
  $databases['default']['default']['database'] = $_ENV['MYSQL_DATABASE'];
  $databases['default']['default']['username'] = $_ENV['MYSQL_USER'];
  $databases['default']['default']['password'] = $_ENV['MYSQL_PASSWORD'];
}

$settings['file_public_path'] = 'sites/default/files';
$settings['hash_salt'] = $_ENV['DRUPAL_HASH_SALT'] ?? 'cmooa_default_salt_' . md5(__DIR__);

$settings['trusted_host_patterns'] = [
  '^.+\.railway\.app$',
  '^.+\.up\.railway\.app$',
  '^localhost$',
  '^127\.0\.0\.1$',
];

// Debug : afficher les variables (temporaire)
if (isset($_ENV['RAILWAY_ENVIRONMENT'])) {
  error_log('MYSQL_HOST: ' . ($_ENV['MYSQL_HOST'] ?? 'NOT SET'));
  error_log('MYSQL_PORT: ' . ($_ENV['MYSQL_PORT'] ?? 'NOT SET'));
  error_log('MYSQL_DATABASE: ' . ($_ENV['MYSQL_DATABASE'] ?? 'NOT SET'));
  error_log('MYSQL_USER: ' . ($_ENV['MYSQL_USER'] ?? 'NOT SET'));
}
