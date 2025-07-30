<?php

namespace Drupal\cmooa\Services\ViewProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

class CalendrierVentesProcessor extends AbstractViewProcessor {

  /**
   * Traite les variables pour la vue 'calendrier_ventes'.
   */
  public function process(array &$variables): void {
    $today = new DrupalDateTime('now');
    $view = $variables['view'];
    $button_text = $view->current_display === 'block' ? "S'inscrire à la vente" : 'Consulter la vente';
    $variables['rows'] = $this->processVenteRows($view->result, $today, $button_text);
  }

  /**
   * Traite les lignes de ventes pour le calendrier.
   */
  private function processVenteRows(array $results, DrupalDateTime $today, string $button_text): array {
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

      // Utiliser field_id au lieu de l'ID dynamique pour le numéro de vente
      $vente_numero = $this->getFieldValue($node, 'field_id', $node->id());

      $rows[] = [
        'nid' => $vente_numero, // Utiliser le champ field_id personnalisé
        'node_id' => $node->id(), // Garder l'ID réel pour les liens
        'title' => $node->getTitle(),
        'image_url' => $this->getImageUrl($node, 'field_image_vente') ?: '/medias/images/img-calendre-default.jpg',
        'date_calendrier' => $this->formatDateCalendrier($dates['debut'], $dates['fin']),
        'lieu' => $lieu,
        'description' => $node->getTitle(),
        'lien' => ['url' => "/vente/{$node->id()}", 'title' => 'Consulter la vente'],
        'statut' => $statut,
        'statut_label' => $this->getStatutLabel($statut),
        'is_livestream' => $lieu === 'Livestream',
        'show_button' => $this->shouldShowButton($today_str, $dates['debut'], $dates['fin'], $statut),
        'button_text' => $button_text,
      ];
    }

    return $rows;
  }

  /**
   * Récupère le libellé du statut d'une vente.
   */
  private function getStatutLabel(string $statut): string {
    $labels = ['en_cours' => 'En cours', 'futur' => 'Prochaine', 'passe' => 'Passées'];
    return $labels[$statut] ?? '';
  }

  /**
   * Détermine si le bouton doit être affiché.
   */
  private function shouldShowButton(string $today_str, $date_debut, $date_fin, string $statut): bool {
    if (!$date_debut || !$date_fin) {
      return false;
    }
    return $statut === 'en_cours' || ($statut === 'futur' && $today_str === $date_debut->format('Y-m-d'));
  }
}
