<?php

namespace Drupal\cmooa\Services\ViewProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

class GaleriesProcessor extends AbstractViewProcessor {

  /**
   * Traite les variables pour la vue 'galeries'.
   */
  public function process(array &$variables): void {
    $rows = [];

    foreach ($variables['view']->result as $result) {
      $node = $result->_entity;
      if (!$node || $node->bundle() !== 'vente') {
        continue;
      }

      // Récupérer le field_id pour le numéro de vente
      $vente_id = $this->getFieldValue($node, 'field_id', $node->id());

      // Compter les images et vidéos directement depuis le nœud vente
      $counts = $this->getMediaCounts($node);

      $rows[] = [
        'nid' => $node->id(),
        'title' => $node->getTitle(),
        'image_url' => $this->getImageUrl($node, 'field_image_vente') ?: '/medias/images/visu-galerie.jpg',
        'vente_numero' => "Vente n° " . $vente_id,
        'nbr_images' => $counts['images'],
        'nbr_videos' => $counts['videos'],
        'lien_galerie' => "/galerie/" . $node->id(),
      ];
    }

    $variables['rows'] = $rows;
  }

  /**
   * Compte les médias (images et vidéos) directement depuis le nœud vente.
   */
  private function getMediaCounts(NodeInterface $vente): array {
    $counts = ['images' => 0, 'videos' => 0];

    // Compter les images
    if ($vente->hasField('field_images') && !$vente->field_images->isEmpty()) {
      $counts['images'] = count($vente->field_images->getValue());
    }

    // Compter les vidéos
    if ($vente->hasField('field_videos') && !$vente->field_videos->isEmpty()) {
      $counts['videos'] = count($vente->field_videos->getValue());
    }

    return $counts;
  }
}
