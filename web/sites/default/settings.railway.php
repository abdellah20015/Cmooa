<?php

/**
 * Configuration Drupal pour Railway
 */

// Configuration de base de données avec IP vérifiée
$databases['default']['default'] = array(
  'database' => 'railway',
  'username' => 'root',
  'password' => 'BNhqAQohszaGhWlWjXxFWBrujhKOsoBD',
  'host' => '35.214.155.77',
  'port' => '52955',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
);

// Hash salt sécurisé
$settings['hash_salt'] = 'cmooa_railway_secure_salt_' . md5('railway_deployment_2024');

// Configuration des fichiers
$settings['file_public_path'] = 'sites/default/files';
$settings['file_private_path'] = 'sites/default/files/private';

// Configuration pour Railway
$settings['trusted_host_patterns'] = array(
  '^.+\.railway\.app$',
  '^.+\.up\.railway\.app$',
);

// Dossier de configuration
$settings['config_sync_directory'] = $app_root . '/' . $site_path . '/config/sync';

// Optimisations pour Railway
$settings['file_temp_path'] = '/tmp';
$settings['allow_authorize_operations'] = FALSE;
