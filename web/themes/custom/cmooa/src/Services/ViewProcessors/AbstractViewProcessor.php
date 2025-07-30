<?php

namespace Drupal\cmooa\Services\ViewProcessors;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DrupalDateTime;

abstract class AbstractViewProcessor {

  protected $entityTypeManager;

  /**
   * Initialise le processeur avec le gestionnaire d'entités.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Traite les variables d'une vue spécifique (à implémenter dans les sous-classes).
   */
  abstract public function process(array &$variables): void;

  /**
   * Récupère la valeur d'un champ d'une entité.
   */
  protected function getFieldValue(ContentEntityInterface $entity, string $field_name, $default = ''): string {
    return $entity->hasField($field_name) && !$entity->get($field_name)->isEmpty() ?
      $entity->get($field_name)->value : $default;
  }

  /**
   * Récupère une entité référencée par un champ.
   */
  protected function getFieldEntity(ContentEntityInterface $entity, string $field_name) {
    return $entity->hasField($field_name) && !$entity->get($field_name)->isEmpty() ?
      $entity->get($field_name)->entity : null;
  }

  /**
   * Récupère l'URL d'une image à partir d'un champ.
   */
  protected function getImageUrl(NodeInterface $node, string $field_name): string {
    $media = $this->getFieldEntity($node, $field_name);
    if (!$media) {
      return '';
    }
    $image = $this->getFieldEntity($media, 'field_media_image');
    return $image ? \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri()) : '';
  }

  /**
   * Récupère un champ de date sous forme d'objet DrupalDateTime.
   */
  protected function getDateField(NodeInterface $node, string $field_name) {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return null;
    }

    // Récupérer la valeur de la date
    $date_value = $node->get($field_name)->value;

    // Créer la date en spécifiant qu'elle est en UTC (comme stockée par Drupal)
    $date = new DrupalDateTime($date_value, new \DateTimeZone('UTC'));

    // Convertir vers le fuseau horaire par défaut du site
    $site_timezone = \Drupal::config('system.date')->get('timezone')['default'] ?: date_default_timezone_get();
    $date->setTimezone(new \DateTimeZone($site_timezone));

    return $date;
  }

  /**
   * Récupère le lieu d'une vente.
   */
  protected function getLieu(NodeInterface $node): string {
    $lieu_entity = $this->getFieldEntity($node, 'field_lieu');
    return $lieu_entity ? $lieu_entity->getName() : '';
  }

  /**
   * Récupère les dates de début et de fin d'une vente.
   */
  protected function getVenteDates(NodeInterface $vente): array {
    return [
      'debut' => $this->getDateField($vente, 'field_date_debut'),
      'fin' => $this->getDateField($vente, 'field_date_fin'),
    ];
  }

  /**
   * Détermine le statut d'une vente (en cours, futur, passé).
   */
  protected function getStatut(string $today_str, $date_debut, $date_fin): string {
    if (!$date_debut || !$date_fin) {
      return '';
    }
    $debut_str = $date_debut->format('Y-m-d');
    $fin_str = $date_fin->format('Y-m-d');
    return $today_str >= $debut_str && $today_str <= $fin_str ? 'en_cours' :
      ($today_str < $debut_str ? 'futur' : 'passe');
  }

  /**
   * Calcule le temps restant pour une vente.
   */
  protected function getTempsRestant(DrupalDateTime $today, $date_fin, string $statut): string {
    if ($statut !== 'en_cours' || !$date_fin) {
      return '';
    }
    $interval = $today->diff($date_fin);
    return $interval->invert ?
      'Cette vente est clôturée' :
      sprintf("%02dj : %02dh : %02dm : %02ds", $interval->days, $interval->h, $interval->i, $interval->s);
  }

  /**
   * Formate une plage de dates pour affichage.
   */
  protected function formatDateRange($date_debut, $date_fin): string {
    if (!$date_debut || !$date_fin) {
      return '';
    }

    $mois_fr = [
      'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
      'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
      'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
      'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre',
    ];

    $jour_debut = $date_debut->format('d');
    $jour_fin = $date_fin->format('d');
    $mois_debut = $mois_fr[$date_debut->format('F')] ?? $date_debut->format('F');
    $mois_fin = $mois_fr[$date_fin->format('F')] ?? $date_fin->format('F');
    $annee_debut = $date_debut->format('Y');
    $annee_fin = $date_fin->format('Y');

    // Si même mois et même année
    if ($mois_debut === $mois_fin && $annee_debut === $annee_fin) {
      return "Du $jour_debut au $jour_fin $mois_debut $annee_debut";
    }
    // Si même année mais mois différents
    elseif ($annee_debut === $annee_fin) {
      return "Du $jour_debut $mois_debut au $jour_fin $mois_fin $annee_debut";
    }
    // Si années différentes
    else {
      return "Du $jour_debut $mois_debut $annee_debut au $jour_fin $mois_fin $annee_fin";
    }
  }

  /**
   * Formate les dates pour le calendrier.
   */
  protected function formatDateCalendrier($date_debut, $date_fin): string {
    if (!$date_debut || !$date_fin) {
      return '';
    }
    $mois_fr = [
      'January' => 'janvier', 'February' => 'février', 'March' => 'mars',
      'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
      'July' => 'juillet', 'August' => 'août', 'September' => 'septembre',
      'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre',
    ];
    $mois_debut = $mois_fr[$date_debut->format('F')] ?? strtolower($date_debut->format('F'));
    $mois_fin = $mois_fr[$date_fin->format('F')] ?? strtolower($date_fin->format('F'));
    $annee = $date_debut->format('Y');
    $jour_debut = $date_debut->format('j');
    $jour_fin = $date_fin->format('j');
    return $mois_debut === $mois_fin ?
      "<span>$jour_debut - $jour_fin</span> $mois_debut $annee" :
      "<span>$jour_debut $mois_debut - $jour_fin $mois_fin</span> $annee";
  }

  /**
   * Formate un prix pour affichage.
   */
  protected function formatPrice($price): string {
    return $price ? number_format($price, 0, ',', ' ') . ' Dhs' : '0 Dhs';
  }

  /**
   * Formate une estimation avec une fourchette min/max pour affichage.
   */
  protected function formatEstimationRange($min, $max): string {
    // Si aucune valeur n'est fournie
    if (!$min && !$max) {
      return 'Non estimé';
    }

    // Convertir en nombre si c'est une chaîne numérique
    $min_num = is_numeric($min) ? (float)$min : 0;
    $max_num = is_numeric($max) ? (float)$max : 0;

    // Si seulement une valeur est fournie
    if ($min_num && !$max_num) {
      return number_format($min_num, 0, ',', ' ') . ' Dhs';
    }
    if (!$min_num && $max_num) {
      return number_format($max_num, 0, ',', ' ') . ' Dhs';
    }

    // Si les deux valeurs sont fournies
    if ($min_num && $max_num) {
      return number_format($min_num, 0, ',', ' ') . ' / ' . number_format($max_num, 0, ',', ' ') . ' Dhs';
    }

    return 'Non estimé';
  }

  /**
   * Formate une estimation pour affichage (ancienne méthode - à garder pour compatibilité).
   * @deprecated Utiliser formatEstimationRange() à la place
   */
  protected function formatEstimation($estimation): string {
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
