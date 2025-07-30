<?php

namespace Drupal\cmooa\Services\ViewProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

class NouvellesProcessor extends AbstractViewProcessor {

  /**
   * Traite les variables pour la vue 'nouvelles'.
   */
  public function process(array &$variables): void {
    $rows = [];

    foreach ($variables['view']->result as $result) {
      $node = $result->_entity;
      if (!$node || $node->bundle() !== 'nouvelle') {
        continue;
      }

      $date_nouvelle = $this->getDateField($node, 'field_date_nouvelle');
      $categorie = $this->getFieldEntity($node, 'field_categorie_nouvelle');
      $categorie_name = $categorie ? $categorie->getName() : 'Non catégorisé';

      $rows[] = [
        'nid' => $node->id(),
        'title' => $node->getTitle(),
        'image_url' => $this->getImageUrl($node, 'field_image_nouvelle') ?: '/medias/images/img-nouovelle.jpg',
        'categorie_date' => $categorie_name . ($date_nouvelle ? ' du ' . $date_nouvelle->format('d F Y') : ''),
        'lien' => "/nouvelle/{$node->id()}",
      ];
    }

    $variables['rows'] = $rows;
  }
}
