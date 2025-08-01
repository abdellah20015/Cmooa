<?php

// Configuration base de données Railway
$databases['default']['default'] = [
  'database' => $_ENV['MYSQL_DATABASE'] ?? 'cmooa',
  'username' => $_ENV['MYSQL_USER'] ?? 'root',
  'password' => $_ENV['MYSQL_PASSWORD'] ?? '',
  'host' => $_ENV['MYSQL_HOST'] ?? 'localhost',
  'port' => $_ENV['MYSQL_PORT'] ?? '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

// Configuration pour Railway
$settings['file_public_path'] = 'sites/default/files';
$settings['hash_salt'] = $_ENV['DRUPAL_HASH_SALT'] ?? 'cmooa_default_salt';

// Trusted host patterns pour Railway
$settings['trusted_host_patterns'] = [
  '^.+\.railway\.app$',
  '^localhost$',
];
