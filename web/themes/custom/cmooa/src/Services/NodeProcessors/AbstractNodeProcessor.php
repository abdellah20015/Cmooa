<?php

namespace Drupal\cmooa\Services\NodeProcessors;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Component\Utility\Xss;

abstract class AbstractNodeProcessor {

  protected $entityTypeManager;

  /**
   * Initialise le processeur avec le gestionnaire d'entités.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Traite les variables d'un nœud spécifique (à implémenter dans les sous-classes).
   */
  abstract public function process(array &$variables): void;

  /**
   * Récupère la valeur d'un champ d'un nœud.
   */
  protected function getFieldValue(NodeInterface $node, string $field_name): string {
    return $node->hasField($field_name) && !$node->get($field_name)->isEmpty() ?
      $node->get($field_name)->getValue()[0]['value'] ?? '' : '';
  }

  /**
   * Récupère une entité média associée à un champ.
   */
  protected function getFieldMedia(NodeInterface $node, string $field_name) {
    return $node->hasField($field_name) && !$node->get($field_name)->isEmpty() ?
      $this->entityTypeManager->getStorage('media')->load($node->get($field_name)->target_id) : null;
  }

  /**
   * Récupère un fichier média d'une entité média.
   */
  protected function getMediaFile($media, string $field_name) {
    return $media && $media->hasField($field_name) && !$media->get($field_name)->isEmpty() ?
      $this->entityTypeManager->getStorage('file')->load($media->get($field_name)->target_id) : null;
  }

  /**
   * Récupère l'URL d'une image à partir d'un champ.
   */
  protected function getImageUrl(NodeInterface $node, string $field_name): string {
    $media = $this->getFieldMedia($node, $field_name);
    $file = $media ? $this->getMediaFile($media, 'field_media_image') : null;
    return $file && file_exists($file->getFileUri()) ?
      \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()) : '';
  }

  /**
   * Récupère l'attribut alt d'une image.
   */
  protected function getImageAlt(NodeInterface $node, string $field_name): string {
    $media = $this->getFieldMedia($node, $field_name);
    return $media && $media->hasField('field_media_image') ? $media->get('field_media_image')->alt : '';
  }

  /**
   * Récupère le titre d'une image.
   */
  protected function getImageTitle(NodeInterface $node, string $field_name): string {
    $media = $this->getFieldMedia($node, $field_name);
    return $media && $media->hasField('field_media_image') ? $media->get('field_media_image')->title : '';
  }

  /**
   * Récupère les données d'une image (URL, alt, titre).
   */
  protected function getImageData(NodeInterface $node, string $field_name): ?array {
    $url = $this->getImageUrl($node, $field_name);
    return $url ? [
      'url' => $url,
      'alt' => $this->getImageAlt($node, $field_name) ?: 'Image de slide',
      'title' => $this->getImageTitle($node, $field_name) ?: '',
    ] : null;
  }

  /**
   * Récupère le contenu HTML traité d'un champ.
   */
  protected function getProcessedContent(NodeInterface $node, string $field_name): ?array {
    $content = $this->getFieldValue($node, $field_name);
    return $content ? ['#markup' => $this->cleanHtmlContent($content)] : null;
  }

  /**
   * Récupère le contenu décodé d'un champ.
   */
  protected function getDecodedContent(NodeInterface $node, string $field_name): string {
    $content = $this->getFieldValue($node, $field_name);
    return $content ? html_entity_decode($this->cleanHtmlContent($content), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
  }

  /**
   * Décode et nettoie le contenu HTML.
   */
  protected function decodeAndCleanHtml(string $content): string {
    return $content ? $this->filterAndCleanHtml(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
  }

  /**
   * Filtre et nettoie le HTML pour supprimer les balises non autorisées.
   */
  protected function filterAndCleanHtml(string $content): string {
    $allowed_tags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 'a', 'br', 'ul', 'ol', 'li', 'blockquote', 'span', 'div'];
    $filtered = Xss::filter($content, $allowed_tags);
    return trim(preg_replace(['/ /', '/\s+/', '/<p[^>]*>\s*<\/p>/', '/<br\s*\/?>\s*(?=<p>)/'], [' ', ' ', '', ''], $filtered));
  }

  /**
   * Nettoie le contenu HTML pour supprimer les balises inutiles.
   */
  protected function cleanHtmlContent(string $content): string {
    return trim(preg_replace(['/<br\s*\/?>\s*(?=<p>)/', '/<\/p>\s*<\/p>/', '/\s+/'], ['', '</p>', ' '], $content));
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
   * Récupère une entité référencée par un champ.
   */
  protected function getFieldEntity(NodeInterface $node, string $field_name) {
    return $node->hasField($field_name) && !$node->get($field_name)->isEmpty() ?
      $node->get($field_name)->entity : null;
  }

  /**
   * Récupère les entités référencées par un champ.
   */
  protected function getFieldReferencedEntities(NodeInterface $node, string $field_name): array {
    return $node->hasField($field_name) && !$node->get($field_name)->isEmpty() ?
      $node->get($field_name)->referencedEntities() : [];
  }

  /**
   * Récupère les données de plusieurs images d'un champ.
   */
  protected function getMultipleImagesData(NodeInterface $node, string $field_name): array {
    $media_items = $this->getFieldReferencedEntities($node, $field_name);
    $images = [];
    foreach ($media_items as $media) {
      $file = $this->getMediaFile($media, 'field_media_image');
      if ($file) {
        $images[] = [
          'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
          'alt' => $media->get('field_media_image')->alt ?: 'Image de galerie',
        ];
      }
    }
    return $images;
  }

  /**
   * Récupère les données de plusieurs vidéos d'un champ.
   */
  protected function getMultipleVideosData(NodeInterface $node, string $field_name): array {
    $media_items = $this->getFieldReferencedEntities($node, $field_name);
    return array_map([$this, 'buildVideoData'], $media_items);
  }

  /**
   * Construit les données d'une vidéo (URL, titre, miniature, etc.).
   */
  protected function buildVideoData($media): array {
    $default_thumbnail = '/' . \Drupal::service('extension.list.theme')->getPath('cmooa') . '/medias/images/img-item-galerie.jpg';
    $video_data = [
      'url' => '',
      'title' => $media->getName() ?: 'Vidéo',
      'alt' => 'Vidéo de la galerie',
      'thumbnail' => $default_thumbnail,
      'frame' => '',
      'type' => 'file',
    ];

    if ($media->hasField('field_media_oembed_video') && !$media->get('field_media_oembed_video')->isEmpty()) {
      $video_data = $this->handleRemoteVideo($media, $video_data);
    } elseif ($media->hasField('field_media_video_file') && !$media->get('field_media_video_file')->isEmpty()) {
      $video_data = $this->handleFileVideo($media, $video_data);
    }

    $custom_thumbnail = $this->getVideoThumbnail($media);
    if ($custom_thumbnail) {
      $video_data['thumbnail'] = $custom_thumbnail;
    }

    return $video_data;
  }

  /**
   * Traite les vidéos distantes (YouTube ou autres).
   */
  protected function handleRemoteVideo($media, array $video_data): array {
    $url = $media->get('field_media_oembed_video')->value;
    $video_data['url'] = $url;

    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
      $video_data['type'] = 'youtube';
      $video_data['frame'] = $this->createYouTubeFrame($url);
      $youtube_thumbnail = $this->getYouTubeThumbnail($url);
      if ($youtube_thumbnail) {
        $video_data['thumbnail'] = $youtube_thumbnail;
      }
    } else {
      $video_data['type'] = 'remote';
      $video_data['frame'] = '<div class="video-responsive"><iframe src="' . $url . '" width="560" height="315" frameborder="0" allowfullscreen></iframe></div>';
    }

    return $video_data;
  }

  /**
   * Traite les fichiers vidéo locaux.
   */
  protected function handleFileVideo($media, array $video_data): array {
    $file = $this->getMediaFile($media, 'field_media_video_file');
    if ($file) {
      $video_data['url'] = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
      $video_data['type'] = 'file';
      $video_data['frame'] = '<div class="video-responsive"><video controls width="560" height="315"><source src="' . $video_data['url'] . '" type="video/mp4">Votre navigateur ne supporte pas la vidéo.</video></div>';
    }
    return $video_data;
  }

  /**
   * Récupère la miniature d'une vidéo YouTube.
   */
  protected function getYouTubeThumbnail(string $url): string {
    $video_id = $this->extractYouTubeId($url);
    return $video_id ? "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg" : '';
  }

  /**
   * Crée un iframe pour une vidéo YouTube.
   */
  protected function createYouTubeFrame(string $url): string {
    $video_id = $this->extractYouTubeId($url);
    return $video_id ? '<div class="video-responsive"><iframe width="560" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe></div>' : '';
  }

  /**
   * Extrait l'ID d'une vidéo YouTube à partir de son URL.
   */
  protected function extractYouTubeId(string $url): string {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches);
    return $matches[1] ?? '';
  }

  /**
   * Récupère la miniature personnalisée d'une vidéo.
   */
  protected function getVideoThumbnail($media): string {
    $thumbnail_file = $this->getMediaFile($media, 'field_media_image');
    return $thumbnail_file ? \Drupal::service('file_url_generator')->generateAbsoluteString($thumbnail_file->getFileUri()) : '';
  }

  /**
   * Récupère l'URL d'un document associé à un nœud.
   */
  protected function getDocumentUrl(NodeInterface $node): string {
    $media = $this->getFieldEntity($node, 'field_document');
    if ($media && $media->hasField('field_media_document') && !$media->field_media_document->isEmpty()) {
      $file = $this->entityTypeManager->getStorage('file')->load($media->field_media_document->target_id);
      return $file ? \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()) : '';
    }
    return '';
  }

  /**
   * Calcule le temps restant pour une vente.
   */
  protected function calculateTempsRestant(DrupalDateTime $today, DrupalDateTime $date_fin, string $statut): string {
    $statut_messages = [
      'passe' => 'Cette vente est clôturée',
      'futur' => 'Cette vente va ouvrir à venir'
    ];

    if (isset($statut_messages[$statut])) {
      return $statut_messages[$statut];
    }

    $interval = $today->diff($date_fin);
    return $interval->invert ? 'Cette vente est clôturée' :
      sprintf("%02dj : %02dh : %02dm : %02ds", $interval->days, $interval->h, $interval->i, $interval->s);
  }

  /**
   * Détermine le statut d'une vente (en cours, futur, passé).
   */
  protected function getStatut(string $today_str, ?DrupalDateTime $date_debut, ?DrupalDateTime $date_fin): string {
    if (!$date_debut || !$date_fin) {
      return '';
    }

    $debut_str = $date_debut->format('Y-m-d');
    $fin_str = $date_fin->format('Y-m-d');

    return ($today_str >= $debut_str && $today_str <= $fin_str) ? 'en_cours' :
      ($today_str < $debut_str ? 'futur' : 'passe');
  }

  /**
   * Récupère le lieu d'une vente.
   */
  protected function getLieu(NodeInterface $node): string {
    $lieu_entity = $this->getFieldEntity($node, 'field_lieu');
    return $lieu_entity ? $lieu_entity->getName() : '';
  }




/**
 * Formate une plage de dates pour affichage avec format français (VERSION SIMPLIFIÉE).
 * Utilise la même logique que AbstractViewProcessor.
 */
protected function formatDateRangeSimple($date_debut, $date_fin): string {
  if (!$date_debut || !$date_fin) {
    return '';
  }

  $mois_fr = [
    'January' => 'janvier', 'February' => 'février', 'March' => 'mars',
    'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
    'July' => 'juillet', 'August' => 'août', 'September' => 'septembre',
    'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre',
  ];

  $jour_debut = $date_debut->format('d');
  $jour_fin = $date_fin->format('d');
  $mois_debut = $mois_fr[$date_debut->format('F')] ?? strtolower($date_debut->format('F'));
  $mois_fin = $mois_fr[$date_fin->format('F')] ?? strtolower($date_fin->format('F'));
  $annee_debut = $date_debut->format('Y');
  $annee_fin = $date_fin->format('Y');

  // Si même mois et même année
  if ($mois_debut === $mois_fin && $annee_debut === $annee_fin) {
    return "$jour_debut au $jour_fin $mois_debut $annee_debut";
  }
  // Si même année mais mois différents
  elseif ($annee_debut === $annee_fin) {
    return "$jour_debut $mois_debut au $jour_fin $mois_fin $annee_debut";
  }
  // Si années différentes
  else {
    return "$jour_debut $mois_debut $annee_debut au $jour_fin $mois_fin $annee_fin";
  }
}


/**
 * Formate une estimation avec field_min et field_max.
 */
protected function formatEstimationFromFields(NodeInterface $node): string {
  $min = $this->getFieldValue($node, 'field_min');
  $max = $this->getFieldValue($node, 'field_max');

  if (!$min && !$max) {
    return 'Non estimé';
  }

  if ($min && $max) {
    return number_format($min, 0, ',', ' ') . ' - ' . number_format($max, 0, ',', ' ') . ' Dhs';
  }

  if ($min) {
    return 'À partir de ' . number_format($min, 0, ',', ' ') . ' Dhs';
  }

  return 'Jusqu\'à ' . number_format($max, 0, ',', ' ') . ' Dhs';
}

  /**
 * Récupère la configuration administrateur (téléphone et email).
 */
protected function getAdminConfig(): array {
  $config = \Drupal::config('system.site');
  return [
    'telephone' => $config->get('admin_telephone') ?: '',
    'email' => $config->get('mail') ?: '',
  ];
}

/**
 * Récupère le numéro de téléphone depuis Config Pages.
 */
protected function getConfigPagesPhone(): string {
  $config_page = \Drupal::entityTypeManager()
    ->getStorage('config_pages')
    ->load('admin_config');

  if (!$config_page || !$config_page->hasField('field_telephone')) {
    return '';
  }

  return $config_page->get('field_telephone')->value ?? '';
}
}
