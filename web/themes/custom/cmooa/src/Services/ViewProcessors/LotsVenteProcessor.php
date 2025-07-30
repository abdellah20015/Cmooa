<?php

namespace Drupal\cmooa\Services\ViewProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

class LotsVenteProcessor extends AbstractViewProcessor {

  /**
   * Traite les variables pour la vue 'lots_vente'.
   */
  public function process(array &$variables): void {
    // Créer la date d'aujourd'hui avec le fuseau horaire du site
    $site_timezone = \Drupal::config('system.date')->get('timezone')['default'] ?: date_default_timezone_get();
    $today = new DrupalDateTime('now', new \DateTimeZone($site_timezone));

    $vente = $this->getVenteContext($variables['view']);
    if (!$vente) {
      $variables['rows'] = [];
      $variables['vente'] = null;
      return;
    }

    $dates = $this->getVenteDates($vente);
    $statut = $this->getStatut($today->format('Y-m-d'), $dates['debut'], $dates['fin']);

    // Utiliser field_id au lieu de l'ID dynamique pour le numéro de vente
    $vente_numero = $this->getFieldValue($vente, 'field_id', $vente->id());

    $variables['vente'] = [
      'nid' => $vente_numero, // Utiliser le champ field_id personnalisé
      'node_id' => $vente->id(), // Garder l'ID réel pour les liens
      'title' => $vente->getTitle(),
      'date_label' => $this->formatDateRange($dates['debut'], $dates['fin']),
      'statut' => $statut,
      'temps_restant' => $this->getTempsRestant($today, $dates['fin'], $statut),
      'image_url' => $this->getImageUrl($vente, 'field_image_vente') ?: '/medias/images/vng-majeurs.jpg',
    ];

    $lots = $this->getLotsFiltered($vente->id());
    $variables['rows'] = $this->formatLots($lots);
    $variables['all_auteurs'] = $this->getAuteurs($lots);
    $variables['auteur_filter'] = \Drupal::request()->query->get('field_auteur');
  }

  /**
   * Récupère le contexte de vente à partir de la vue.
   */
  private function getVenteContext($view) {
    $route = \Drupal::routeMatch();
    if ($route->getRouteName() === 'entity.node.canonical') {
      $node = $route->getParameter('node');
      if ($node && $node->bundle() === 'vente') {
        return $node;
      }
    }
    if (!empty($view->args)) {
      return $this->entityTypeManager->getStorage('node')->load($view->args[0]);
    }
    if (!empty($view->result)) {
      return $this->getFieldEntity($view->result[0]->_entity, 'field_vente_associee');
    }
    return null;
  }

  /**
   * Récupère les lots filtrés pour une vente.
   * Note: Le filtre field_oeuvre_majeure = True doit être configuré dans la vue Drupal
   */
  private function getLotsFiltered(int $vente_id): array {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'lot')
      ->condition('status', 1)
      ->condition('field_vente_associee', $vente_id)
      ->condition('field_oeuvre_majeure', 1) // Filtrer seulement les œuvres majeures
      ->accessCheck(TRUE)
      ->sort('created', 'DESC');

    $nids = $query->execute();
    $lots = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Filtre supplémentaire par auteur si demandé
    $auteur_filter = \Drupal::request()->query->get('field_auteur');
    if ($auteur_filter) {
      $lots = array_filter($lots, function($lot) use ($auteur_filter) {
        return stripos($this->getFieldValue($lot, 'field_auteur'), $auteur_filter) !== false;
      });
    }

    return $lots;
  }

  /**
   * Formate les lots pour affichage.
   */
  private function formatLots(array $lots): array {
    $rows = [];
    foreach ($lots as $lot) {
      $rows[] = [
        'nid' => $lot->id(),
        'title' => $lot->getTitle(),
        'image_url' => $this->getImageUrl($lot, 'field_image_lot') ?: '/medias/images/img-slide-majeurs-default.jpg',
        'auteur' => $this->getFieldValue($lot, 'field_auteur'),
        'estimation' => $this->formatEstimationRange($this->getFieldValue($lot, 'field_min'), $this->getFieldValue($lot, 'field_max')),
        'enchere' => $this->formatPrice($this->getFieldValue($lot, 'field_enchere', 0)),
        'lien_lot' => $lot->toUrl()->toString(),
        'is_oeuvre_majeure' => (bool) $this->getFieldValue($lot, 'field_oeuvre_majeure', 0),
      ];
    }
    return $rows;
  }

  /**
   * Récupère la liste des auteurs uniques des lots.
   */
  private function getAuteurs(array $lots): array {
    $auteurs = [];
    foreach ($lots as $lot) {
      $auteur = $this->getFieldValue($lot, 'field_auteur');
      if ($auteur && !in_array($auteur, $auteurs)) {
        $auteurs[] = $auteur;
      }
    }
    sort($auteurs);
    return $auteurs;
  }
}
