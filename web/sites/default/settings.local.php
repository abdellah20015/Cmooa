<?php

// Désactive la mise en cache de l'interface Views UI (corrige le bug).
$settings['views_ui.disable_cache'] = TRUE;

// Autorise l'accès à /rebuild.php si besoin
$settings['rebuild_access'] = TRUE;

// Active le logging détaillé
$config['system.logging']['error_level'] = 'verbose';

// Désactive certains caches pour le développement
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
