<?php

namespace Drupal\cmooa\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\cmooa\Services\NodeProcessors\AbstractNodeProcessor;

class UserProcessor extends AbstractNodeProcessor {

  private MessengerInterface $messenger;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger) {
    parent::__construct($entityTypeManager);
    $this->messenger = $messenger;
  }

  // Traite les données de la page utilisateur
  public function processUserPage(array &$variables): void {
    $current_user = \Drupal::currentUser();
    if (!$current_user->isAuthenticated()) return;

    $user_id = $current_user->id();
    $message = $this->handleFormSubmissions($user_id);

    if ($message) {
      $variables['message_type'] = $message['type'];
      $variables['message_text'] = $message['message'];
    }

    $url_generator = \Drupal::service('url_generator');
    $logout_url = $url_generator->generate('user.logout');

    $variables += [
      'user_data' => $this->getUserData($user_id),
      'user_favoris' => $this->getUserFavoris($user_id),
      'user_ventes' => $this->getUserVentes($user_id),
      'meilleures_ventes' => $this->getMeilleuresVentes($user_id),
      'logout_url' => $logout_url
    ];
  }

  // Gère les soumissions de formulaires
  private function handleFormSubmissions(int $user_id): ?array {
    $request = \Drupal::request();
    if ($request->getMethod() !== 'POST') return null;

    $post_data = $request->request->all();

    return match (true) {
      isset($post_data['update_user_info']) => $this->updateUserInfo($user_id, $post_data),
      isset($post_data['change_password']) => $this->changePassword($user_id, $post_data),
      default => null
    };
  }

  // Récupère les données utilisateur depuis la base
  private function getUserData(int $user_id): array {
    $user = User::load($user_id);
    $query = \Drupal::database()->select('forms_inscription_submissions', 'fis')
      ->fields('fis')
      ->condition('uid', $user_id)
      ->orderBy('created', 'DESC')
      ->range(0, 1);

    $result = $query->execute()->fetchObject();
    $data = $result ? (array) $result : $this->getDefaultUserData();
    $data['email'] = $user->getEmail();
    $data['username'] = $user->getAccountName();

    return $data;
  }

  // Retourne la structure par défaut des données utilisateur
  private function getDefaultUserData(): array {
    return array_fill_keys([
      'civilite', 'nom', 'prenom', 'adresse', 'cpt', 'ville',
      'tel', 'email', 'fax', 'rib', 'namebanque', 'adresse2'
    ], '') + ['conditions' => 0];
  }

  // Récupère les lots favoris de l'utilisateur
  private function getUserFavoris(int $user_id): array {
    $lot_ids = \Drupal::database()->select('user_favoris', 'uf')
      ->fields('uf', ['lot_id'])
      ->condition('uid', $user_id)
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchCol();

    return $lot_ids ? $this->getLotDataBatch($lot_ids) : [];
  }

  // Récupère les ventes où l'utilisateur a encéhri
  private function getUserVentes(int $user_id): array {
    $lot_ids = \Drupal::database()->select('lot_encheres', 'le')
      ->fields('le', ['lot_id'])
      ->condition('uid', $user_id)
      ->groupBy('lot_id')
      ->execute()
      ->fetchCol();

    if (empty($lot_ids)) return [];

    $lots_data = $this->getLotDataBatch($lot_ids);
    $ventes_grouped = [];

    foreach ($lots_data as $lot) {
      $vente_id = $lot['vente_id'];
      if (!isset($ventes_grouped[$vente_id])) {
        $vente_node = $this->entityTypeManager->getStorage('node')->load($vente_id);
        if (!$vente_node) continue;

        $today = new \DateTime();
        $date_debut = $this->getDateField($vente_node, 'field_date_debut');
        $date_fin = $this->getDateField($vente_node, 'field_date_fin');
        $statut = $this->getStatut($today->format('Y-m-d'), $date_debut, $date_fin);

        $ventes_grouped[$vente_id] = [
          'vente_id' => $vente_id,
          'vente_title' => $vente_node->getTitle(),
          'vente_status' => $this->getVenteStatusLabel($statut),
          'vente_image' => $this->getImageUrl($vente_node, 'field_image_vente') ?: '/medias/images/img-offres-01.jpg',
          'lots' => []
        ];
      }

      $lot['encheres'] = $this->getLotEncheres($lot['lot_id']);
      $ventes_grouped[$vente_id]['lots'][] = $lot;
    }

    return array_values($ventes_grouped);
  }

  // Récupère les enchères d'un lot
  private function getLotEncheres(int $lot_id): array {
    $results = \Drupal::database()->select('lot_encheres', 'le')
      ->fields('le', ['enchere', 'created'])
      ->condition('lot_id', $lot_id)
      ->orderBy('created', 'DESC')
      ->range(0, 5)
      ->execute()
      ->fetchAll();

    return array_map(fn($enchere) => [
      'prix' => number_format($enchere->enchere, 0, ',', ' ') . ' Dhs',
      'date' => date('d/m/Y H:i', $enchere->created)
    ], $results);
  }

  // Récupère les meilleures ventes de l'utilisateur
  private function getMeilleuresVentes(int $user_id): array {
    $lot_ids = \Drupal::database()->select('lot_encheres', 'ae')
      ->fields('ae', ['lot_id'])
      ->condition('uid', $user_id)
      ->orderBy('enchere', 'DESC')
      ->range(0, 10)
      ->execute()
      ->fetchCol();

    return $lot_ids ? $this->getLotDataBatch($lot_ids, true) : [];
  }

  // Charge les données des lots en batch
  private function getLotDataBatch(array $lot_ids, bool $with_adjuge = false): array {
    $lots = $this->entityTypeManager->getStorage('node')->loadMultiple($lot_ids);
    $result = [];

    foreach ($lots as $lot) {
      $vente = $this->getFieldEntity($lot, 'field_vente_associee');
      $today = new \DateTime();
      $date_debut = $this->getDateField($vente, 'field_date_debut');
      $date_fin = $this->getDateField($vente, 'field_date_fin');
      $statut = $this->getStatut($today->format('Y-m-d'), $date_debut, $date_fin);

      $lot_data = [
        'lot_id' => $lot->id(),
        'lot_numero' => $lot->id(),
        'title' => $lot->getTitle(),
        'auteur' => $this->getFieldValue($lot, 'field_auteur'),
        'estimation' => $this->formatEstimation($this->getFieldValue($lot, 'field_estimation')),
        'image_url' => $this->getImageUrl($lot, 'field_image_lot') ?: '/medias/images/visu-favoris.jpg',
        'vente_id' => $vente?->id() ?? 0,
        'vente_title' => $vente?->getTitle() ?? '',
        'vente_status' => $this->getVenteStatusLabel($statut),
        'vente_image' => $vente ? $this->getImageUrl($vente, 'field_image_vente') ?: '/medias/images/vgn-favoris.jpg' : '/medias/images/vgn-favoris.jpg',
      ];

      if ($with_adjuge) {
        $lot_data['prix_adjuge'] = $this->formatPrice($this->getFieldValue($lot, 'field_enchere', 0));
      }

      $result[] = $lot_data;
    }

    return $result;
  }

  // Retourne le label du statut de vente
  private function getVenteStatusLabel(string $statut): string {
    return match ($statut) {
      'en_cours' => 'Vente en cours',
      'futur' => 'Vente à venir',
      default => 'Vente terminée'
    };
  }

  // Formate l'estimation avec séparateur
  private function formatEstimation(string $estimation): string {
    if (strpos($estimation, '/') !== false) {
      $parts = explode('/', $estimation);
      return count($parts) === 2 ?
        number_format(trim($parts[0]), 0, ',', ' ') . ' / ' . number_format(trim($parts[1]), 0, ',', ' ') . ' Dhs' :
        $estimation;
    }
    return is_numeric($estimation) ? number_format($estimation, 0, ',', ' ') . ' Dhs' : $estimation;
  }

  // Formate un prix avec devise
  private function formatPrice(int $price): string {
    return number_format($price, 0, ',', ' ') . ' Dhs';
  }

  // Met à jour les informations utilisateur
  private function updateUserInfo(int $user_id, array $data): array {
    $fields = array_intersect_key($data, array_flip([
      'civilite', 'nom', 'prenom', 'adresse', 'cpt', 'ville',
      'tel', 'fax', 'rib', 'namebanque', 'adresse2'
    ])) + ['conditions' => isset($data['conditions']) ? 1 : 0];

    \Drupal::database()->merge('forms_inscription_submissions')
      ->key(['uid' => $user_id])
      ->fields($fields + ['created' => time()])
      ->execute();

    return ['type' => 'success', 'message' => 'Vos informations ont été mises à jour avec succès.'];
  }

  // Change le mot de passe utilisateur
  private function changePassword(int $user_id, array $data): array {
    $user = User::load($user_id);
    $password_hasher = \Drupal::service('password');

    $old_pass = $data['old-pass'] ?? '';
    $new_pass = $data['new-pass'] ?? '';
    $conf_pass = $data['conf-pass'] ?? '';

    if ($new_pass !== $conf_pass) {
      return ['type' => 'error', 'message' => 'Les mots de passe ne correspondent pas.'];
    }

    if (!$password_hasher->check($old_pass, $user->getPassword())) {
      return ['type' => 'error', 'message' => 'L\'ancien mot de passe est incorrect.'];
    }

    $user->setPassword($new_pass)->save();
    return ['type' => 'success', 'message' => 'Votre mot de passe a été modifié avec succès.'];
  }

  // Traitement vide requis par l'abstract
  public function process(array &$variables): void {}
}
