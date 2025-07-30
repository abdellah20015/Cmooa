<?php

use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;

$content_types = [
  'nouvelle' => ['/nouvelle/'],
  'vente' => ['/vente/', '/galerie/', '/livestream/'],
  'lot' => ['/lot/'],
];

$total_created = 0;
$total_existing = 0;

foreach ($content_types as $type => $alias_prefixes) {
  echo "\n=== Traitement du type de contenu '$type' ===\n";

  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => $type,
    'status' => 1,
  ]);

  if (empty($nodes)) {
    echo "Aucun nœud de type '$type' trouvé.\n";
    continue;
  }

  $created_count = 0;
  $existing_count = 0;

  foreach ($nodes as $node) {
    $node_id = $node->id();
    $node_path = '/node/' . $node_id;

    foreach ($alias_prefixes as $alias_prefix) {
      $alias_path = $alias_prefix . $node_id;

      $existing_aliases = \Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties([
        'path' => $node_path,
        'alias' => $alias_path,
      ]);

      if (empty($existing_aliases)) {
        $alias = PathAlias::create([
          'path' => $node_path,
          'alias' => $alias_path,
          'langcode' => 'fr',
        ]);
        $alias->save();

        echo "✓ Alias créé : " . $alias_path . " pour le nœud '" . $node->getTitle() . "' (ID: " . $node_id . ")\n";
        $created_count++;
      } else {
        echo "- Alias déjà existant : " . $alias_path . " pour le nœud '" . $node->getTitle() . "' (ID: " . $node_id . ")\n";
        $existing_count++;
      }
    }
  }

  echo "\n--- Résumé pour '$type' ---\n";
  echo "Nouveaux alias créés : " . $created_count . "\n";
  echo "Alias déjà existants : " . $existing_count . "\n";
  echo "Total des nœuds traités : " . count($nodes) . "\n";

  $total_created += $created_count;
  $total_existing += $existing_count;
}

echo "\n=== RÉSUMÉ GLOBAL ===\n";
echo "Total nouveaux alias créés : " . $total_created . "\n";
echo "Total alias déjà existants : " . $total_existing . "\n";
echo "Création des alias terminée.\n";
?>
