<?php

namespace Drupal\cmooa\Services\NodeProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

class VenteNodeProcessor extends AbstractNodeProcessor {

  /**
 * Traite les variables pour un nœud de type 'vente'.
 */
public function process(array &$variables): void {
  $node = $variables['node'];
  $is_galerie = strpos(\Drupal::request()->getRequestUri(), "/galerie/{$node->id()}") !== false;
  $variables['is_galerie'] = $is_galerie;
  $variables['elements_galerie'] = $this->getGalerieElementsForVente($node);

  $is_galerie ? $this->processGalerie($variables, $node) : $this->processVenteDetails($variables, $node);
}

  /**
 * Traite les données pour la vue galerie d'une vente.
 */
private function processGalerie(array &$variables, NodeInterface $node): void {
  // Récupérer directement les images et vidéos de la vente
  $images = $this->getMultipleImagesData($node, 'field_images');
  $videos = $this->getMultipleVideosData($node, 'field_videos');

  $variables['tableau'] = [
    'image_url' => $this->getImageUrl($node, 'field_image_vente') ?: '/medias/images/banner-vente.jpg',
    'image_alt' => 'Image de la vente',
    'vente_numero' => 'Galerie vente n° ' . $this->getFieldValue($node, 'field_id'),
    'titre_vente' => $node->getTitle(),
    'nombre_images' => count($images),
    'nombre_videos' => count($videos),
    'images' => $images,
    'videos' => $videos,
  ];
}

  /**
 * Traite les détails d'une vente.
 */
private function processVenteDetails(array &$variables, NodeInterface $node): void {
  $today = new DrupalDateTime('now', new \DateTimeZone('UTC'));
  $date_debut = $this->getDateField($node, 'field_date_debut');
  $date_fin = $this->getDateField($node, 'field_date_fin');
  $statut = $this->getStatut($today->format('Y-m-d'), $date_debut, $date_fin);
  $lots_data = $this->getLotsForVente($node);
  $admin_config = $this->getAdminConfig();

  // Récupérer directement les vidéos de la vente
  $videos_galerie = $this->getMultipleVideosData($node, 'field_videos');

  $variables['rows'] = $lots_data['rows'];
  $variables['oeuvres_phares'] = $lots_data['oeuvres_phares'];
  $variables['all_auteurs'] = $lots_data['all_auteurs'];
  $variables['auteur_filter'] = $lots_data['auteur_filter'];
  $variables['videos_galerie'] = $videos_galerie; // Ajouter les vidéos
  $variables['vente'] = [
    'nid' => $node->id(),
    'title' => $node->getTitle(),
    'image_url' => $this->getImageUrl($node, 'field_image_vente') ?: '/medias/images/banner-vente.jpg',
    'image_alt' => 'Image de la vente',
    'vente_numero' => 'Vente n° ' . $this->getFieldValue($node, 'field_id'),
    'date_label' => $this->formatDateRangeSimple($date_debut, $date_fin),
    'lieu' => $this->getLieu($node),
    'description' => $this->getFieldValue($node, 'field_description'),
    'telephone' => $this->getConfigPagesPhone(),
    'email' => $admin_config['email'],
    'document_url' => $this->getDocumentUrl($node),
    'temps_restant' => $this->calculateTempsRestant($today, $date_fin, $statut),
    'show_participer_button' => $statut === 'en_cours' || ($statut === 'futur' && $today->format('Y-m-d') === $date_debut?->format('Y-m-d')),
    'lots_list_url' => "/vente/{$node->id()}/lots/print",
    'is_livestream' => $this->getLieu($node) === 'Livestream',
  ];
}

  /**
   * Récupère les éléments de galerie pour une vente.
   */
  private function getGalerieElementsForVente(NodeInterface $vente): array {
    $block_ids = $this->entityTypeManager->getStorage('block_content')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'galerie')
      ->condition('field_vente_reference', $vente->id())
      ->condition('status', 1)
      ->execute();

    if (!$block_ids) {
      return [];
    }

    $block_content = $this->entityTypeManager->getStorage('block_content')->load(reset($block_ids));
    if (!$block_content) {
      return [];
    }

    $variables = [
      'plugin_id' => 'block_content:' . $block_content->uuid(),
      'content' => ['#block_content' => $block_content]
    ];

    \Drupal\cmooa\Services\BlockProcessor::processBlock($variables, $this->entityTypeManager);
    return $variables['elements_galerie'] ?? [];
  }

  /**
 * Récupère les lots associés à une vente.
 */
private function getLotsForVente(NodeInterface $vente): array {
  $lots = $this->entityTypeManager->getStorage('node')->loadByProperties([
    'type' => 'lot',
    'status' => 1,
    'field_vente_associee' => $vente->id(),
  ]);

  $auteur_filter = \Drupal::request()->query->get('field_auteur');
  if ($auteur_filter) {
    $lots = array_filter($lots, fn($lot) => stripos($this->getFieldValue($lot, 'field_auteur'), $auteur_filter) !== false);
  }

  $rows = [];
  $oeuvres_phares = []; // Séparer les œuvres phares
  $all_auteurs = [];

  foreach ($lots as $lot) {
    $auteur = $this->getFieldValue($lot, 'field_auteur');
    if ($auteur && !in_array($auteur, $all_auteurs)) {
      $all_auteurs[] = $auteur;
    }

    $lot_data = [
      'nid' => $lot->id(),
      'title' => $lot->getTitle(),
      'image_url' => $this->getImageUrl($lot, 'field_image_lot') ?: '/medias/images/img-slide-majeurs-default.jpg',
      'auteur' => $auteur,
      'estimation' => $this->formatEstimationFromFields($lot),
      'enchere' => $this->formatPrice($this->getFieldValue($lot, 'field_enchere', 0)),
      'lien_lot' => $lot->toUrl()->toString(),
      'is_oeuvre_phare' => (bool) $this->getFieldValue($lot, 'field_oeuvre_phare', 0),
    ];

    // Séparer les œuvres phares
    if ($lot_data['is_oeuvre_phare']) {
      $oeuvres_phares[] = $lot_data;
    } else {
      $rows[] = $lot_data;
    }
  }

  sort($all_auteurs);
  return [
    'rows' => $rows,
    'oeuvres_phares' => $oeuvres_phares,
    'all_auteurs' => $all_auteurs,
    'auteur_filter' => $auteur_filter
  ];
}
  /**
   * Formate un prix pour affichage.
   */
  private function formatPrice($value): string {
    return $value ? number_format($value, 0, ',', ' ') . ' Dhs' : '0 Dhs';
  }

  /**
   * Formate une estimation pour affichage.
   */
  private function formatEstimation($estimation): string {
    if (!$estimation) {
      return 'Non estimé';
    }
    if (strpos($estimation, '/') !== false) {
      $parts = array_map('trim', explode('/', $estimation));
      return count($parts) === 2 ?
        number_format($parts[0], 0, ',', ' ') . ' / ' . number_format($parts[1], 0, ',', ' ') . ' Dhs' :
        $estimation;
    }
    return is_numeric($estimation) ? number_format($estimation, 0, ',', ' ') . ' Dhs' : $estimation;
  }
}
