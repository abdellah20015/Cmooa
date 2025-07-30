<?php

namespace Drupal\cmooa\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\cmooa\Services\NodeProcessors\AbstractNodeProcessor;

class ResultsPageProcessor extends AbstractNodeProcessor {

  private AccountProxyInterface $currentUser;
  private Connection $database;
  private FileUrlGeneratorInterface $fileUrlGenerator;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Connection $database, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($entity_type_manager);
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Process results page variables.
   */
  public function processResultsPage(array &$variables): void {
    $user_authenticated = $this->currentUser->isAuthenticated();
    $is_admin = $user_authenticated && in_array('administrator', $this->currentUser->getRoles());
    $user_id = $user_authenticated ? $this->currentUser->id() : 0;

    // Get lots data based on user type
    $lots_data = $is_admin ? $this->getAllLotsData() : $this->getUserLotsData($user_id);

    // Get users data only for admin
    $users_data = $is_admin ? $this->getUsersData() : [];

    $variables += [
      'is_admin' => $is_admin,
      'user_authenticated' => $user_authenticated,
      'lots_data' => $lots_data,
      'users_data' => $users_data,
      'registered_users_count' => count($users_data),
      'connected_users_count' => $this->getConnectedUsersCount(),
    ];
  }

  /**
   * Get all lots data for admin.
   */
  private function getAllLotsData(): array {
    $query = $this->database->select('lot_encheres', 'le');
    $query->join('node_field_data', 'n', 'n.nid = le.lot_id');
    $query->leftJoin('node__field_auteur', 'auteur', 'auteur.entity_id = le.lot_id');
    $query->leftJoin('node__field_min', 'min', 'min.entity_id = le.lot_id');
    $query->leftJoin('node__field_max', 'max', 'max.entity_id = le.lot_id');
    $query->leftJoin('users_field_data', 'u', 'u.uid = le.uid');

    $query->fields('le', ['id', 'lot_id', 'uid', 'enchere', 'created', 'is_adjuged'])
          ->fields('n', ['title'])
          ->fields('auteur', ['field_auteur_value'])
          ->fields('min', ['field_min_value'])
          ->fields('max', ['field_max_value'])
          ->fields('u', ['name'])
          ->condition('n.status', 1)
          ->condition('n.type', 'lot')
          ->orderBy('le.lot_id', 'ASC')
          ->orderBy('le.created', 'DESC');

    $results = $query->execute()->fetchAll();

    return $this->formatLotsData($results, true);
  }

  /**
   * Get lots data for specific user.
   */
  private function getUserLotsData(int $user_id): array {
    if (!$user_id) {
      return [];
    }

    $query = $this->database->select('lot_encheres', 'le');
    $query->join('node_field_data', 'n', 'n.nid = le.lot_id');
    $query->leftJoin('node__field_auteur', 'auteur', 'auteur.entity_id = le.lot_id');
    $query->leftJoin('node__field_min', 'min', 'min.entity_id = le.lot_id');
    $query->leftJoin('node__field_max', 'max', 'max.entity_id = le.lot_id');

    $query->fields('le', ['id', 'lot_id', 'uid', 'enchere', 'created', 'is_adjuged'])
          ->fields('n', ['title'])
          ->fields('auteur', ['field_auteur_value'])
          ->fields('min', ['field_min_value'])
          ->fields('max', ['field_max_value'])
          ->condition('n.status', 1)
          ->condition('n.type', 'lot')
          ->condition('le.uid', $user_id)
          ->orderBy('le.lot_id', 'ASC')
          ->orderBy('le.created', 'DESC');

    $results = $query->execute()->fetchAll();

    return $this->formatLotsData($results, false);
  }

  /**
   * Format lots data for display.
   */
  private function formatLotsData(array $results, bool $is_admin): array {
    $lots = [];
    $processed_lots = [];

    foreach ($results as $result) {
      $lot_id = $result->lot_id;

      // Get only the latest bid per lot for display
      if (!isset($processed_lots[$lot_id])) {
        // Load the lot node to get image using AbstractNodeProcessor methods
        $lot_node = $this->entityTypeManager->getStorage('node')->load($lot_id);

        if ($lot_node) {
          // Get image URL using AbstractNodeProcessor method
          $image_url = $this->getImageUrl($lot_node, 'field_image_lot');
          $image_alt = $this->getImageAlt($lot_node, 'field_image_lot') ?: 'Image du lot ' . $lot_id;

          $lots[] = [
            'lot_id' => $lot_id,
            'title' => $result->title,
            'auteur' => $result->field_auteur_value ?: '',
            'estimation' => $this->formatEstimation($result->field_min_value, $result->field_max_value),
            'current_bid' => $this->formatPrice($result->enchere ?: 0),
            'image_url' => $image_url, // Ne pas utiliser d'image par défaut
            'image_alt' => $image_alt,
            'has_image' => !empty($image_url), // Indicateur pour savoir s'il y a une image
            'is_adjuged' => (bool) $result->is_adjuged,
            'adjuged_price' => $result->is_adjuged ? $this->formatPrice($result->enchere) : null,
            'lot_url' => '/lot/' . $lot_id,
            'user_name' => $is_admin ? ($result->name ?: 'Anonyme') : null,
            'bid_date' => $result->created,
          ];
        }

        $processed_lots[$lot_id] = true;
      }
    }

    return $lots;
  }

  /**
   * Get registered users data for admin.
   */
  private function getUsersData(): array {
    $query = $this->database->select('users_field_data', 'u');
    $query->leftJoin('sessions', 's', 's.uid = u.uid AND s.timestamp >= :time', [':time' => time() - 900]);

    $query->fields('u', ['uid', 'name', 'created'])
          ->fields('s', ['timestamp'])
          ->condition('u.status', 1)
          ->condition('u.uid', 0, '>')
          ->orderBy('u.name', 'ASC');

    $results = $query->execute()->fetchAll();

    $users = [];
    foreach ($results as $result) {
      $users[] = [
        'uid' => $result->uid,
        'name' => $result->name,
        'is_connected' => !empty($result->timestamp),
        'profile_url' => '/user/' . $result->uid,
      ];
    }

    return $users;
  }

  /**
   * Get count of connected users.
   */
  private function getConnectedUsersCount(): int {
    return (int) $this->database->select('sessions', 's')
      ->fields('s', ['sid'])
      ->condition('s.timestamp', time() - 900, '>=')
      ->condition('s.uid', 0, '>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Format price for display.
   */
  private function formatPrice($value): string {
    return $value ? number_format($value, 0, ',', ' ') . ' Dhs' : '0 Dhs';
  }

  /**
   * Format estimation for display using min and max values.
   */
  private function formatEstimation($min_value, $max_value): string {
    if (!$min_value && !$max_value) {
      return 'Non estimé';
    }

    // Si on a les deux valeurs
    if ($min_value && $max_value) {
      return number_format($min_value, 0, ',', ' ') . ' / ' . number_format($max_value, 0, ',', ' ') . ' Dhs';
    }

    // Si on a seulement une valeur minimum
    if ($min_value && !$max_value) {
      return 'À partir de ' . number_format($min_value, 0, ',', ' ') . ' Dhs';
    }

    // Si on a seulement une valeur maximum
    if (!$min_value && $max_value) {
      return 'Jusqu\'à ' . number_format($max_value, 0, ',', ' ') . ' Dhs';
    }

    return 'Non estimé';
  }

  /**
   * Required by AbstractNodeProcessor but not used in this context.
   */
  public function process(array &$variables): void {
    // Not used in this service context
  }
}
