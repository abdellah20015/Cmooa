<?php

namespace Drupal\cmooa\Services\ViewProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

class EncheresRecentesProcessor extends AbstractViewProcessor {

  /**
   * Traite les variables pour la vue 'encheres_recentes'.
   */
  public function process(array &$variables): void {
    $today = new DrupalDateTime('now');
    $today_str = $today->format('Y-m-d');
    $rows = [];

    foreach ($variables['view']->result as $result) {
      $node = $result->_entity;
      if (!$node || $node->bundle() !== 'lot') {
        continue;
      }

      $lot_vente = $this->getFieldEntity($node, 'field_vente_associee');
      if (!$lot_vente) {
        continue;
      }

      $dates = $this->getVenteDates($lot_vente);

      $statut = $this->getStatut($today_str, $dates['debut'], $dates['fin']);

      $rows[] = [
        'nid' => $node->id(),
        'title' => $node->getTitle(),
        'image_url' => $this->getImageUrl($node, 'field_image_lot') ?: '/medias/images/img-recent-default.jpg',
        'auteur' => $this->getFieldValue($node, 'field_auteur'),
        'estimation' => $this->formatEstimationRange($this->getFieldValue($node, 'field_min'), $this->getFieldValue($node, 'field_max')),
        'enchere' => $this->formatPrice($this->getFieldValue($node, 'field_enchere', 0)),
        'lien_lot' => $node->toUrl()->toString(),
      ];
    }

    $variables['rows'] = $rows;
  }
}
