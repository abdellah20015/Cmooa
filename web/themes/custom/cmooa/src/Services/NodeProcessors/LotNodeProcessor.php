<?php

namespace Drupal\cmooa\Services\NodeProcessors;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

class LotNodeProcessor extends AbstractNodeProcessor {

  private const EXCHANGE_RATE = 0.09;
  private const PAS_ENCHERE = 1000;

  /**
   * Traite les variables pour l'affichage des pages de lot
   */
  public function process(array &$variables): void {
    $node = $variables['node'];
    $vente = $this->getFieldEntity($node, 'field_vente_associee');
    $today = new DrupalDateTime('now');
    $date_debut = $this->getDateField($vente, 'field_date_debut');
    $date_fin = $this->getDateField($vente, 'field_date_fin');
    $statut = $this->getStatut($today->format('Y-m-d'), $date_debut, $date_fin);

    $current_user = \Drupal::currentUser();
    $user_authenticated = $current_user->isAuthenticated();
    $user_id = $user_authenticated ? $current_user->id() : 0;
    $is_admin = $user_authenticated && in_array('administrator', $current_user->getRoles());
    $user_inscrit = $is_admin || ($user_authenticated && $this->isUserInscritForLot($user_id, $node->id()));
    $user_has_bid = $user_authenticated && $this->hasUserBid($user_id, $node->id());

    $this->handleUserActions($user_authenticated, $user_id, $node->id(), $user_inscrit, $statut, $today, $date_debut, $is_admin);

    $user_enchere = $user_has_bid ? $this->getUserLatestBid($user_id, $node->id()) : 0;
    $enchere = max((int)$this->getFieldValue($node, 'field_enchere', 0), $user_enchere);
    $next_enchere = $enchere ? $enchere + self::PAS_ENCHERE : self::PAS_ENCHERE;
    $is_adjuged = $this->isLotAdjuged($node->id());

    $variables += [
      'lot' => $this->buildLotData($node, $enchere, $next_enchere, $is_adjuged),
      'vente' => $this->buildVenteData($vente, $date_debut, $date_fin, $statut, $today),
      'user_authenticated' => $user_authenticated,
      'user_id' => $user_id,
      'user_inscrit' => $user_inscrit,
      'user_has_bid' => $user_has_bid,
      'user_has_highest_bid' => $user_has_bid && $user_enchere == $enchere && $statut === 'en_cours',
      'user_favoris' => $user_authenticated ? $this->getUserFavoris($user_id) : [],
      'is_favoris' => $user_authenticated && $this->isLotInFavoris($user_id, $node->id()),
      'is_admin' => $is_admin,
    ];
  }

  /**
   * Gère les actions utilisateur (favoris, enchères, adjudication)
   */
  private function handleUserActions(bool $user_authenticated, int $user_id, int $lot_id, bool $user_inscrit, string $statut, DrupalDateTime $today, ?DrupalDateTime $date_debut, bool $is_admin): void {
    if (!$user_authenticated) return;

    $this->handleFavoris($user_id, $lot_id);

    if (($user_inscrit || $is_admin) && $this->canBid($statut, $today, $date_debut, $lot_id)) {
      $this->handleEnchere($lot_id, $user_id);
    }

    if ($is_admin && isset($_POST['submit_adjuged']) && $lot_id == (int)$_POST['lot_id']) {
      $this->setLotAdjuged($lot_id);
    }
  }

  /**
   * Vérifie si les enchères sont autorisées
   */
  private function canBid(string $statut, DrupalDateTime $today, ?DrupalDateTime $date_debut, int $lot_id): bool {
    return ($statut === 'en_cours' || ($statut === 'futur' && $today->format('Y-m-d') === $date_debut?->format('Y-m-d')))
           && !$this->isLotAdjuged($lot_id);
  }

  /**
   * Construit les données du lot
   */
  private function buildLotData(NodeInterface $node, int $enchere, int $next_enchere, bool $is_adjuged): array {
    return [
      'nid' => $node->id(),
      'title' => $node->getTitle(),
      'auteur' => $this->getFieldValue($node, 'field_auteur'),
      'description' => $this->decodeAndCleanHtml($this->getFieldValue($node, 'field_description')),
      'estimation' => $this->formatEstimationFromFields($node),
      'enchere_dh' => $this->formatPrice($enchere, 'DH'),
      'enchere_eur' => $this->formatPrice($enchere * self::EXCHANGE_RATE, '€', 2),
      'next_enchere_dh' => $this->formatPrice($next_enchere, 'DH'),
      'next_enchere_eur' => $this->formatPrice($next_enchere * self::EXCHANGE_RATE, '€', 2),
      'enchere_propositions' => $this->getEncherePropositions($next_enchere),
      'image_url' => $this->getImageUrl($node, 'field_image_lot') ?: '/medias/images/img-visu-lot.jpg',
      'image_alt' => $this->getImageAlt($node, 'field_image_lot') ?: 'Image du lot',
      'images' => $this->getMultipleImagesData($node, 'field_image_lot'),
      'document_url' => $this->getDocumentUrl($node),
      'details' => $this->getLotDetails($node),
      'is_adjuged' => $is_adjuged,
      'registered_users_count' => $this->getRegisteredUsersCount($node->id()),
      'connected_users_count' => $this->getConnectedUsersCount(),
      'bids' => $this->getBidsWithUsernames($node->id()),
    ];
  }

  /**
   * Construit les données de la vente
   */
  private function buildVenteData($vente, ?DrupalDateTime $date_debut, ?DrupalDateTime $date_fin, string $statut, DrupalDateTime $today): array {
    $admin_config = $this->getAdminConfig();

    return [
      'nid' => $vente->id(),
      'title' => $vente->getTitle(),
      'vente_numero' => $this->getFieldValue($vente, 'field_id'),
      'date_label' => $this->formatDateRangeSimple($date_debut, $date_fin),
      'lieu' => $this->getLieu($vente),
      'temps_restant' => $this->calculateTempsRestant($today, $date_fin, $statut),
      'lots_list_url' => "/vente/{$vente->id()}/lots",
      'statut' => $statut,
      'is_today' => $today->format('Y-m-d') === $date_debut?->format('Y-m-d'),
      'telephone' => $this->getConfigPagesPhone(),
      'email' => $admin_config['email'],
    ];
  }

  /**
   * Gère les enchères soumises
   */
  private function handleEnchere(int $lot_id, int $user_id): void {
    if (isset($_POST['submit_enchere'], $_POST['enchere_value']) && $lot_id == (int)$_POST['lot_id']) {
      $this->saveBid($lot_id, $user_id, (int)$_POST['enchere_value']);
    }
  }

  /**
   * Gère l'ajout/suppression des favoris
   */
  private function handleFavoris(int $user_id, int $lot_id): void {
    if (!isset($_POST['submit_favoris']) || (int)$_POST['lot_id'] != $lot_id) return;

    $action = $_POST['favoris_action'] ?? 'toggle';
    $is_favoris = $this->isLotInFavoris($user_id, $lot_id);

    match ($action) {
      'add' => !$is_favoris && $this->addToFavoris($user_id, $lot_id),
      'remove' => $is_favoris && $this->removeFromFavoris($user_id, $lot_id),
      default => $is_favoris ? $this->removeFromFavoris($user_id, $lot_id) : $this->addToFavoris($user_id, $lot_id)
    };
  }

  /**
   * Sauvegarde une enchère
   */
  private function saveBid(int $lot_id, int $user_id, int $enchere_value): void {
    \Drupal::database()->insert('lot_encheres')
      ->fields(['lot_id' => $lot_id, 'uid' => $user_id, 'enchere' => $enchere_value, 'created' => time()])
      ->execute();

    $node = $this->entityTypeManager->getStorage('node')->load($lot_id);
    $node?->set('field_enchere', $enchere_value)->save();
  }

  /**
   * Ajoute un lot aux favoris
   */
  private function addToFavoris(int $user_id, int $lot_id): void {
    \Drupal::database()->merge('user_favoris')
      ->key(['uid' => $user_id, 'lot_id' => $lot_id])
      ->fields(['created' => time()])
      ->execute();
  }

  /**
   * Supprime un lot des favoris
   */
  private function removeFromFavoris(int $user_id, int $lot_id): void {
    \Drupal::database()->delete('user_favoris')
      ->condition('uid', $user_id)
      ->condition('lot_id', $lot_id)
      ->execute();
  }

  /**
   * Vérifie si un lot est dans les favoris
   */
  private function isLotInFavoris(int $user_id, int $lot_id): bool {
    return (bool) \Drupal::database()->select('user_favoris', 'uf')
      ->fields('uf', ['id'])
      ->condition('uf.uid', $user_id)
      ->condition('uf.lot_id', $lot_id)
      ->execute()
      ->fetchField();
  }

  /**
   * Récupère les favoris d'un utilisateur
   */
  private function getUserFavoris(int $user_id): array {
    return array_map(fn($result) => [
      'lot_id' => $result->lot_id,
      'created' => $result->created,
    ], \Drupal::database()->select('user_favoris', 'uf')
      ->fields('uf', ['lot_id', 'created'])
      ->condition('uf.uid', $user_id)
      ->orderBy('uf.created', 'DESC')
      ->execute()
      ->fetchAll());
  }

  /**
   * Vérifie si un utilisateur est inscrit pour un lot
   */
  private function isUserInscritForLot(int $user_id, int $lot_id): bool {
    return (bool) \Drupal::database()->select('forms_inscription_submissions', 'fis')
      ->fields('fis', ['id'])
      ->condition('fis.uid', $user_id)
      ->condition('fis.lot_id', $lot_id)
      ->condition('fis.created', time(), '<=')
      ->execute()
      ->fetchField();
  }

  /**
   * Vérifie si un utilisateur a enchéri
   */
  private function hasUserBid(int $user_id, int $lot_id): bool {
    return (bool) \Drupal::database()->select('lot_encheres', 'le')
      ->fields('le', ['id'])
      ->condition('le.uid', $user_id)
      ->condition('le.lot_id', $lot_id)
      ->condition('le.created', time(), '<=')
      ->execute()
      ->fetchField();
  }

  /**
   * Récupère la dernière enchère d'un utilisateur
   */
  private function getUserLatestBid(int $user_id, int $lot_id): int {
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
   * Récupère les détails d'un lot
   */
  private function getLotDetails(NodeInterface $node): array {
    return ['Huile sur toile', '175 x 120 cm', 'Signée en bas à droite'];
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

  /**
   * Compte les utilisateurs inscrits pour un lot
   */
  private function getRegisteredUsersCount(int $lot_id): int {
    return (int) \Drupal::database()->select('forms_inscription_submissions', 'fis')
      ->fields('fis', ['id'])
      ->condition('fis.lot_id', $lot_id)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Compte les utilisateurs connectés
   */
  private function getConnectedUsersCount(): int {
    return (int) \Drupal::database()->select('sessions', 's')
      ->fields('s', ['sid'])
      ->condition('s.timestamp', time() - 900, '>=')
      ->condition('s.uid', 0, '>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Récupère les enchères avec noms d'utilisateurs
   */
  private function getBidsWithUsernames(int $lot_id): array {
    $query = \Drupal::database()->select('lot_encheres', 'le');
    $query->join('users_field_data', 'u', 'u.uid = le.uid');
    $query->fields('le', ['enchere', 'created'])
          ->fields('u', ['name'])
          ->condition('le.lot_id', $lot_id)
          ->orderBy('le.created', 'DESC');
    return array_map(fn($result) => [
        'enchere' => (int) $result->enchere,
        'username' => $result->name ?: 'Anonyme',
        'created' => (int) $result->created,
    ], $query->execute()->fetchAll());
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
   * Marque un lot comme adjugé
   */
  private function setLotAdjuged(int $lot_id): void {
    \Drupal::database()->update('lot_encheres')
      ->fields(['is_adjuged' => 1])
      ->condition('lot_id', $lot_id)
      ->condition('created', \Drupal::database()->select('lot_encheres', 'le')
        ->fields('le', ['created'])
        ->condition('le.lot_id', $lot_id)
        ->orderBy('le.created', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField())
      ->execute();
  }
}
