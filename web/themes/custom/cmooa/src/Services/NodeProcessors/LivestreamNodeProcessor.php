<?php

namespace Drupal\cmooa\Services\NodeProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

class LivestreamNodeProcessor extends AbstractNodeProcessor {

  private const EXCHANGE_RATE = 0.09;
  private const PAS_ENCHERE = 1000;

  /**
   * Traite les variables pour l'affichage des pages livestream
   */
  public function process(array &$variables): void {
    $node = $variables['node'];
    if ($node->bundle() !== 'vente' || !preg_match('#^/livestream/(\d+)$#', \Drupal::request()->getRequestUri())) {
      return;
    }

    $today = new DrupalDateTime('now', new \DateTimeZone('UTC'));
    $date_debut = $this->getDateField($node, 'field_date_debut');
    $date_fin = $this->getDateField($node, 'field_date_fin');
    $statut = $this->getStatut($today->format('Y-m-d'), $date_debut, $date_fin);

    $current_user = \Drupal::currentUser();
    $is_admin = $current_user->isAuthenticated() && in_array('administrator', $current_user->getRoles());
    $user_id = $current_user->isAuthenticated() ? $current_user->id() : 0;
    $user_authenticated = $current_user->isAuthenticated();
    $user_inscrit = $is_admin || ($user_authenticated && $this->isUserInscritForVente($user_id, $node->id()));

    $lots_data = $this->getLotsForVente($node);
    $current_lot = $this->getCurrentLot($lots_data);

    $this->handleLivestreamActions($node, $user_id, $is_admin, $user_authenticated, $user_inscrit, $statut, $today, $date_debut);

    [$lots_adjuged, $lots_en_cours] = $this->separateLots($lots_data);

    $variables = array_merge($variables, [
      'vente' => $this->buildVenteData($node, $current_lot, $is_admin, $this->getLinkFieldUrl($node, 'field_live_vente')),
      'lots' => ['adjuged' => $lots_adjuged, 'en_cours' => $lots_en_cours],
      'current_lot' => $current_lot,
      'user_authenticated' => $user_authenticated,
      'user_inscrit' => $user_inscrit,
      'user_id' => $user_id,
      'pas_enchere' => self::PAS_ENCHERE,
      'is_admin' => $is_admin,
      'statut' => $statut,
      '#attached' => [
        'library' => ['cmooa/livestream'],
        'drupalSettings' => ['cmooa' => ['livestream' => $this->buildJsSettings($lots_adjuged, $lots_en_cours, $current_lot, $this->getLinkFieldUrl($node, 'field_live_vente'), $is_admin, $user_authenticated, $user_inscrit)]]
      ]
    ]);
  }

  /**
   * Gère les actions d'enchères et d'adjudication
   */
  private function handleLivestreamActions(NodeInterface $node, int $user_id, bool $is_admin, bool $user_authenticated, bool $user_inscrit, string $statut, DrupalDateTime $today, ?DrupalDateTime $date_debut): void {
    if (!$user_authenticated) return;

    if (isset($_POST['submit_enchere'], $_POST['enchere_value'], $_POST['lot_id'])) {
      $this->processEnchere($node, $user_id, $is_admin, $user_inscrit, $statut, $today, $date_debut);
    }

    if ($is_admin && isset($_POST['submit_adjuged'], $_POST['lot_id'])) {
      $this->processAdjudication($node);
    }
  }

  /**
   * Traite une enchère soumise
   */
  private function processEnchere(NodeInterface $node, int $user_id, bool $is_admin, bool $user_inscrit, string $statut, DrupalDateTime $today, ?DrupalDateTime $date_debut): void {
    $lot_id = (int) $_POST['lot_id'];
    $enchere_value = (int) $_POST['enchere_value'];

    $lot = $this->entityTypeManager->getStorage('node')->load($lot_id);
    if (!$lot || $lot->bundle() !== 'lot' || !$this->isLotBelongsToVente($lot, $node->id())) {
      \Drupal::messenger()->addError('Lot invalide.');
      return;
    }

    if (!$this->canUserBid($is_admin, $user_inscrit, $statut, $today, $date_debut, $lot_id)) {
      return;
    }

    $current_enchere = max((int) $this->getFieldValue($lot, 'field_enchere', 0), $this->getLatestBidForLot($lot_id));

    if ($enchere_value > $current_enchere) {
      $this->saveBid($lot_id, $user_id, $enchere_value);
    } else {
      \Drupal::messenger()->addError("Enchère insuffisante.");
    }
  }

  /**
   * Traite une adjudication
   */
  private function processAdjudication(NodeInterface $node): void {
    $lot_id = (int) $_POST['lot_id'];
    $lot = $this->entityTypeManager->getStorage('node')->load($lot_id);

    if ($lot && $this->isLotBelongsToVente($lot, $node->id())) {
      $this->setLotAdjuged($lot_id);
      \Drupal::messenger()->addStatus("Lot #{$lot_id} adjugé avec succès.");
    } else {
      \Drupal::messenger()->addError('Lot invalide pour adjudication.');
    }
  }

  /**
   * Vérifie si un utilisateur peut enchérir
   */
  private function canUserBid(bool $is_admin, bool $user_inscrit, string $statut, DrupalDateTime $today, ?DrupalDateTime $date_debut, int $lot_id): bool {
    if ($is_admin) return true;

    if (!$user_inscrit) {
      \Drupal::messenger()->addError('Vous n\'êtes pas inscrit pour cette vente');
      return false;
    }

    if ($this->isLotAdjuged($lot_id)) {
      \Drupal::messenger()->addError('Ce lot est déjà adjugé');
      return false;
    }

    if (!($statut === 'en_cours' || ($statut === 'futur' && $today->format('Y-m-d') === $date_debut?->format('Y-m-d')))) {
      \Drupal::messenger()->addError('La vente n\'est pas active');
      return false;
    }

    return true;
  }

  /**
   * Vérifie si un lot appartient à une vente
   */
  private function isLotBelongsToVente(NodeInterface $lot, int $vente_id): bool {
    if (!$lot->hasField('field_vente_associee') || $lot->get('field_vente_associee')->isEmpty()) {
      return false;
    }
    return $lot->get('field_vente_associee')->target_id == $vente_id;
  }

  /**
   * Récupère l'URL d'un champ Link
   */
  private function getLinkFieldUrl(NodeInterface $node, string $field_name): string {
    if (!$node->hasField($field_name)) return '';

    $field_value = $node->get($field_name)->getValue();
    return $field_value[0]['uri'] ?? '';
  }

  /**
   * Récupère tous les lots d'une vente
   */
  private function getLotsForVente(NodeInterface $vente): array {
    $lots = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'lot',
      'status' => 1,
      'field_vente_associee' => $vente->id(),
    ]);

    $current_user_id = \Drupal::currentUser()->id();
    $rows = [];

    foreach ($lots as $lot) {
      $enchere_node = (int) $this->getFieldValue($lot, 'field_enchere', 0);
      $enchere_db = $this->getLatestBidForLot($lot->id());
      $enchere_actuelle = max($enchere_node, $enchere_db);
      $next_enchere = $enchere_actuelle + self::PAS_ENCHERE;
      $is_adjuged = $this->isLotAdjuged($lot->id());

      $latest_bid_data = $this->getLatestBidDataForLot($lot->id());
      $user_latest_bid = $this->getUserLatestBid($current_user_id, $lot->id());
      $user_has_bid = $this->hasUserBid($current_user_id, $lot->id());

      $rows[] = [
        'nid' => $lot->id(),
        'title' => $lot->getTitle() ?: 'Titre non défini',
        'auteur' => $this->getFieldValue($lot, 'field_auteur') ?: 'Auteur inconnu',
        'description' => $this->decodeAndCleanHtml($this->getFieldValue($lot, 'field_description')) ?: 'Description non disponible',
        'estimation' => $this->formatEstimationFromFields($lot) ?: 'Estimation non définie',
        'enchere_dh' => $this->formatPrice($enchere_actuelle, 'DH'),
        'enchere_eur' => $this->formatPrice($enchere_actuelle * self::EXCHANGE_RATE, '€', 2),
        'next_enchere_dh' => $this->formatPrice($next_enchere, 'DH'),
        'next_enchere_eur' => $this->formatPrice($next_enchere * self::EXCHANGE_RATE, '€', 2),
        'enchere_propositions' => $this->getEncherePropositions($next_enchere),
        'image_url' => $this->getImageUrl($lot, 'field_image_lot') ?: '/medias/images/img-visu-lot.jpg',
        'image_alt' => $this->getImageAlt($lot, 'field_image_lot') ?: 'Image du lot',
        'images' => $this->getMultipleImagesData($lot, 'field_image_lot') ?: [['url' => '/medias/images/img-visu-lot.jpg', 'alt' => 'Image du lot']],
        'is_adjuged' => $is_adjuged,
        'lien_lot' => '/livestream/' . $vente->id(),
        'user_has_highest_bid' => $user_has_bid && $user_latest_bid == $enchere_actuelle,
        'last_bidder_name' => $latest_bid_data['username'],
      ];
    }

    usort($rows, fn($a, $b) => $a['nid'] <=> $b['nid']);
    return $rows;
  }

  /**
   * Détermine le lot actuellement en cours
   */
  private function getCurrentLot(array $lots_data): ?array {
    foreach ($lots_data as $lot) {
      if (!$lot['is_adjuged']) return $lot;
    }
    return $lots_data[0] ?? null;
  }

  /**
   * Sépare les lots adjugés des lots en cours
   */
  private function separateLots(array $lots_data): array {
    $adjuged = $en_cours = [];
    foreach ($lots_data as $lot) {
      $lot['is_adjuged'] ? $adjuged[] = $lot : $en_cours[] = $lot;
    }
    return [array_values($adjuged), array_values($en_cours)];
  }

  /**
   * Construit les données de la vente
   */
  private function buildVenteData(NodeInterface $node, ?array $current_lot, bool $is_admin, string $youtube_live_url): array {
    $date_debut = $this->getDateField($node, 'field_date_debut');
    $date_fin = $this->getDateField($node, 'field_date_fin');

    return [
      'nid' => $node->id(),
      'vente_numero' => $this->getFieldValue($node, 'field_id'),
      'title' => $node->getTitle(),
      'date_label' => $this->formatDateRangeSimple($date_debut, $date_fin),
      'lieu' => $this->getLieu($node) ?: 'Livestream',
      'image_url' => $this->getImageUrl($node, 'field_image_vente') ?: '/medias/images/banner-vente.jpg',
      'image_alt' => $this->getImageAlt($node, 'field_image_vente') ?: 'Image de la vente',
      'is_livestream' => true,
      'vente_id' => $node->id(),
      'lot_id' => $current_lot ? $current_lot['nid'] : '0',
      'is_admin' => $is_admin,
      'youtube_live_url' => $youtube_live_url,
    ];
  }

  /**
   * Construit les paramètres JavaScript
   */
  private function buildJsSettings(array $lots_adjuged, array $lots_en_cours, ?array $current_lot, string $youtube_live_url, bool $is_admin, bool $user_authenticated, bool $user_inscrit): array {
    return [
      'lots_adjuged_count' => count($lots_adjuged),
      'lots_en_cours_count' => count($lots_en_cours),
      'current_lot_id' => $current_lot ? $current_lot['nid'] : null,
      'youtube_live_url' => $youtube_live_url,
      'is_admin' => $is_admin,
      'user_authenticated' => $user_authenticated,
      'user_inscrit' => $user_inscrit,
    ];
  }

  /**
   * Récupère la dernière enchère pour un lot
   */
  private function getLatestBidForLot(int $lot_id): int {
    return (int) \Drupal::database()->select('lot_encheres', 'le')
      ->fields('le', ['enchere'])
      ->condition('le.lot_id', $lot_id)
      ->orderBy('le.created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Récupère les données de la dernière enchère
   */
  private function getLatestBidDataForLot(int $lot_id): array {
    $query = \Drupal::database()->select('lot_encheres', 'le');
    $query->join('users_field_data', 'u', 'u.uid = le.uid');
    $query->fields('le', ['enchere'])
          ->fields('u', ['name'])
          ->condition('le.lot_id', $lot_id)
          ->orderBy('le.created', 'DESC')
          ->range(0, 1);

    $result = $query->execute()->fetchAssoc();
    return [
      'enchere' => $result ? (int) $result['enchere'] : 0,
      'username' => $result ? ($result['name'] ?: 'Anonyme') : '',
    ];
  }

  /**
   * Vérifie si un utilisateur a enchéri sur un lot
   */
  private function hasUserBid(int $user_id, int $lot_id): bool {
    if (!$user_id) return false;
    return (bool) \Drupal::database()->select('lot_encheres', 'le')
      ->fields('le', ['id'])
      ->condition('le.uid', $user_id)
      ->condition('le.lot_id', $lot_id)
      ->execute()
      ->fetchField();
  }

  /**
   * Récupère la dernière enchère d'un utilisateur
   */
  private function getUserLatestBid(int $user_id, int $lot_id): int {
    if (!$user_id) return 0;
    return (int) \Drupal::database()->select('lot_encheres', 'le')
      ->fields('le', ['enchere'])
      ->condition('le.uid', $user_id)
      ->condition('le.lot_id', $lot_id)
      ->orderBy('le.created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Sauvegarde une enchère
   */
  private function saveBid(int $lot_id, int $user_id, int $enchere_value): void {
    \Drupal::database()->insert('lot_encheres')
      ->fields([
        'lot_id' => $lot_id,
        'uid' => $user_id,
        'enchere' => $enchere_value,
        'created' => time(),
        'is_adjuged' => 0
      ])
      ->execute();

    $node = $this->entityTypeManager->getStorage('node')->load($lot_id);
    if ($node) {
      $node->set('field_enchere', $enchere_value);
      $node->save();
    }

    \Drupal::messenger()->addStatus("Enchère de " . number_format($enchere_value, 0, ',', ' ') . " DH enregistrée avec succès.");
  }

  /**
   * Marque un lot comme adjugé
   */
  private function setLotAdjuged(int $lot_id): void {
    $latest_created = \Drupal::database()->select('lot_encheres', 'le')
      ->fields('le', ['created'])
      ->condition('le.lot_id', $lot_id)
      ->orderBy('le.created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($latest_created) {
      \Drupal::database()->update('lot_encheres')
        ->fields(['is_adjuged' => 1])
        ->condition('lot_id', $lot_id)
        ->condition('created', $latest_created)
        ->execute();
    }
  }

  /**
   * Vérifie si un lot est adjugé
   */
  private function isLotAdjuged(int $lot_id): bool {
    return (bool) \Drupal::database()->select('lot_encheres', 'le')
      ->fields('le', ['id'])
      ->condition('le.lot_id', $lot_id)
      ->condition('le.is_adjuged', 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Vérifie si un utilisateur est inscrit pour une vente
   */
  private function isUserInscritForVente(int $user_id, int $vente_id): bool {
    $lots = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'lot',
      'field_vente_associee' => $vente_id,
    ]);

    foreach ($lots as $lot) {
      if ((bool) \Drupal::database()->select('forms_inscription_submissions', 'fis')
        ->fields('fis', ['id'])
        ->condition('fis.uid', $user_id)
        ->condition('fis.lot_id', $lot->id())
        ->execute()
        ->fetchField()) {
        return true;
      }
    }
    return false;
  }

  /**
   * Formate un prix avec devise
   */
  private function formatPrice(float $value, string $currency, int $decimals = 0): string {
    return $value ? number_format($value, $decimals, ',', ' ') . " $currency" : "0 $currency";
  }

  /**
   * Génère les propositions d'enchères
   */
  private function getEncherePropositions(int $next_enchere): array {
    return array_map(fn($value) => [
      'dh' => $this->formatPrice($value, 'DH'),
      'eur' => $this->formatPrice($value * self::EXCHANGE_RATE, '€', 2),
      'value' => $value,
    ], [
      $next_enchere,
      $next_enchere + self::PAS_ENCHERE,
      $next_enchere + (2 * self::PAS_ENCHERE),
    ]);
  }
}
