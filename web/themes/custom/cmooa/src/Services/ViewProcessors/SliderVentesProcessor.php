<?php

namespace Drupal\cmooa\Services\ViewProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

class SliderVentesProcessor extends AbstractViewProcessor {

  /**
   * Traite les variables pour la vue 'slider_ventes'.
   */
  public function process(array &$variables): void {
    $site_timezone = \Drupal::config('system.date')->get('timezone')['default'] ?: date_default_timezone_get();
    $today = new DrupalDateTime('now', new \DateTimeZone($site_timezone));

    $variables['rows'] = $this->processVenteRows($variables['view']->result, $today);
  }

  /**
   * Traite les lignes de ventes pour le slider.
   */
  private function processVenteRows(array $results, DrupalDateTime $today): array {
    $rows = [];
    $today_str = $today->format('Y-m-d');

    foreach ($results as $result) {
      $node = $result->_entity;
      if (!$node || $node->bundle() !== 'vente') {
        continue;
      }

      $dates = $this->getVenteDates($node);
      if (!$dates['debut'] || !$dates['fin']) {
        continue;
      }

      $statut = $this->getStatut($today_str, $dates['debut'], $dates['fin']);
      $lieu = $this->getLieu($node);

      $vente_numero = $this->getFieldValue($node, 'field_id', $node->id());

      $rows[] = [
        'nid' => $vente_numero,
        'title' => $node->getTitle(),
        'image_url' => $this->getImageUrl($node, 'field_image_vente') ?: '/medias/images/img-slide-default.jpg',
        'date_label' => $this->formatDateRange($dates['debut'], $dates['fin']),
        'date_fin_formatted' => $dates['fin'] ? $dates['fin']->format('d F Y') : '',
        'lieu' => $lieu,
        'description' => $statut === 'en_cours' ? $this->getFieldValue($node, 'field_description') : '',
        'lien' => ['url' => "/vente/{$node->id()}", 'title' => 'Voir le détail de la vente'],
        'statut' => $statut,
        'is_livestream' => $lieu === 'Livestream',
        'nid_dynamic' => $node->id(),
      ];
    }

    return $rows;
  }
}
