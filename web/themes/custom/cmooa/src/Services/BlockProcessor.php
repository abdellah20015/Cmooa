<?php

namespace Drupal\cmooa\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Service pour traiter les blocs personnalisés avec gestion des galeries vidéo.
 */
class BlockProcessor {

  /**
   * Point d'entrée principal - Traite les variables selon le type de bloc.
   *
   * @param array $variables Variables du template
   * @param EntityTypeManagerInterface $entityTypeManager Service de gestion d'entités
   */
  public static function processBlock(&$variables, EntityTypeManagerInterface $entityTypeManager) {
    // Vérification de la structure du bloc
    if (!str_starts_with($variables['plugin_id'], 'block_content:') ||
        !isset($variables['content']['#block_content'])) {
      return;
    }

    $content = $variables['content']['#block_content'];

    // Routage vers les méthodes spécifiques selon le type de bloc
    switch (true) {
      case $content->id() == 3: // Galerie accueil
        self::processGalleryBlock($variables, $content, $entityTypeManager);
        break;

      case $content->bundle() == 'estimation_oeuvre':
        self::processEstimationBlock($variables, $content);
        break;

      case $content->bundle() == 'contact':
        self::processContactBlock($variables, $content);
        break;
    }
  }

  /**
   * Traite le bloc galerie avec validation de vente en cours et récupération des vidéos.
   * Génère les thumbnails appropriées pour l'affichage des frames vidéo.
   *
   * @param array $variables Variables du template
   * @param object $content Contenu du bloc
   * @param EntityTypeManagerInterface $entityTypeManager Service de gestion d'entités
   */
  private static function processGalleryBlock(&$variables, $content, EntityTypeManagerInterface $entityTypeManager) {
    $today = new DrupalDateTime('now');

    // Récupération de la vente référencée
    $vente_referencee = self::getReferencedSale($content);
    if (!$vente_referencee) {
      $variables['elements_galerie'] = [];
      return;
    }

    // Vérification si la vente est actuellement active
    if (!self::isSaleActive($vente_referencee, $today)) {
      $variables['elements_galerie'] = [];
      return;
    }

    // Calcul des jours restants pour l'affichage
    $jour_text = self::getRemainingDaysText($vente_referencee, $today);

    // Construction de la galerie avec les vidéos
    $elements_galerie = self::buildGalleryElements($vente_referencee, $jour_text);

    // Configuration finale des variables
    $variables['elements_galerie'] = $elements_galerie;
    self::attachGalleryAssets($variables);
  }

  /**
   * Récupère la vente référencée dans le bloc.
   *
   * @param object $content Contenu du bloc
   * @return object|null Entité vente ou null si non trouvée
   */
  private static function getReferencedSale($content) {
    if (!$content->hasField('field_vente_reference') ||
        $content->get('field_vente_reference')->isEmpty()) {
      return null;
    }

    return $content->get('field_vente_reference')->entity;
  }

  /**
   * Vérifie si une vente est actuellement active selon ses dates.
   *
   * @param object $vente_referencee Entité vente
   * @param DrupalDateTime $today Date actuelle
   * @return bool True si la vente est active
   */
  private static function isSaleActive($vente_referencee, DrupalDateTime $today): bool {
    $date_debut = $vente_referencee->hasField('field_date_debut') &&
                  !$vente_referencee->field_date_debut->isEmpty()
      ? new DrupalDateTime($vente_referencee->field_date_debut->value)
      : null;

    $date_fin = $vente_referencee->hasField('field_date_fin') &&
                !$vente_referencee->field_date_fin->isEmpty()
      ? new DrupalDateTime($vente_referencee->field_date_fin->value)
      : null;

    return $date_debut && $date_fin &&
           $today >= $date_debut && $today <= $date_fin;
  }

  /**
   * Calcule et formate le texte des jours restants pour la vente.
   *
   * @param object $vente_referencee Entité vente
   * @param DrupalDateTime $today Date actuelle
   * @return string Texte formaté des jours restants
   */
  private static function getRemainingDaysText($vente_referencee, DrupalDateTime $today): string {
    $date_fin_vente = new DrupalDateTime($vente_referencee->field_date_fin->value);
    $diff = $today->diff($date_fin_vente);
    $jours_restants = $diff->days;

    return ($jours_restants == 0) ? 'dernier jour' : "jour $jours_restants";
  }

  /**
   * Construit les éléments de la galerie à partir des vidéos de la vente.
   * Optimise l'affichage des frames vidéo avec thumbnails appropriées.
   *
   * @param object $vente_referencee Entité vente
   * @param string $jour_text Texte des jours restants
   * @return array Tableau des éléments de galerie
   */
  private static function buildGalleryElements($vente_referencee, string $jour_text): array {
    $elements_galerie = [];

    if (!$vente_referencee->hasField('field_videos') ||
        $vente_referencee->get('field_videos')->isEmpty()) {
      return $elements_galerie;
    }

    foreach ($vente_referencee->get('field_videos')->referencedEntities() as $media) {
      if (!$media) continue;

      $video_data = self::buildVideoDataForGallery($media);

      $elements_galerie[] = [
        'thumbnail' => $video_data['thumbnail'],
        'alt' => $video_data['alt'],
        'lien' => [
          'url' => "/galerie/{$vente_referencee->id()}#video-{$media->id()}",
          'text' => 'Voir la vidéo'
        ],
        'description' => "Vidéos vente " . $jour_text,
      ];
    }

    return $elements_galerie;
  }

  /**
   * Construit les données optimisées d'une vidéo pour l'affichage galerie.
   * Gère les thumbnails YouTube haute qualité et images personnalisées.
   *
   * @param object $media Entité média vidéo
   * @return array Données formatées de la vidéo
   */
  private static function buildVideoDataForGallery($media): array {
    $default_thumbnail = '/' . \Drupal::service('extension.list.theme')->getPath('cmooa') . '/medias/images/img-item-galerie.jpg';

    $video_data = [
      'thumbnail' => $default_thumbnail,
      'alt' => 'Vidéo de la galerie',
    ];

    // Traitement prioritaire : thumbnail personnalisée
    if ($media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
      $file = $media->get('field_media_image')->entity;
      if ($file) {
        $video_data['thumbnail'] = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($file->getFileUri());
      }
    }
    // Fallback : thumbnail YouTube si vidéo YouTube
    elseif ($media->hasField('field_media_oembed_video') &&
            !$media->get('field_media_oembed_video')->isEmpty()) {

      $url = $media->get('field_media_oembed_video')->value;
      $youtube_thumbnail = self::getYouTubeThumbnail($url);

      if ($youtube_thumbnail) {
        $video_data['thumbnail'] = $youtube_thumbnail;
      }
    }

    $video_data['alt'] = $media->getName() ?: 'Vidéo de la galerie';

    return $video_data;
  }

  /**
   * Récupère la thumbnail haute qualité d'une vidéo YouTube.
   * Essaie plusieurs qualités : maxresdefault > hqdefault > mqdefault.
   *
   * @param string $url URL de la vidéo YouTube
   * @return string|null URL de la thumbnail ou null si non trouvée
   */
  private static function getYouTubeThumbnail(string $url): ?string {
    if (strpos($url, 'youtube.com') === false && strpos($url, 'youtu.be') === false) {
      return null;
    }

    $video_id = self::extractYouTubeId($url);
    if (!$video_id) {
      return null;
    }

    // Priorité aux thumbnails de meilleure qualité
    $qualities = ['maxresdefault', 'hqdefault', 'mqdefault'];

    foreach ($qualities as $quality) {
      $thumbnail_url = "https://img.youtube.com/vi/{$video_id}/{$quality}.jpg";
      // On retourne la première qualité disponible (optimisation)
      if (self::thumbnailExists($thumbnail_url)) {
        return $thumbnail_url;
      }
    }

    return "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg"; // Fallback
  }

  /**
   * Vérifie si une thumbnail YouTube existe (évite les images 404).
   *
   * @param string $url URL de la thumbnail à vérifier
   * @return bool True si la thumbnail existe
   */
  private static function thumbnailExists(string $url): bool {
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '200') !== false;
  }

  /**
   * Extrait l'ID d'une vidéo YouTube depuis différents formats d'URL.
   * Supporte : youtube.com/watch?v=xxx, youtu.be/xxx, youtube.com/embed/xxx
   *
   * @param string $url URL de la vidéo YouTube
   * @return string ID de la vidéo ou chaîne vide
   */
  private static function extractYouTubeId(string $url): string {
    $patterns = [
      '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/',
      '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
      }
    }

    return '';
  }

  /**
   * Attache les assets CSS/JS et configurations pour la galerie.
   * Configure le slider avec les paramètres optimisés.
   *
   * @param array $variables Variables du template
   */
  private static function attachGalleryAssets(&$variables): void {
    $variables['#attached']['library'][] = 'cmooa/global-styling';
    $variables['#attached']['drupalSettings']['cmooa']['slide_galerie_home'] = [
      'slidesToShow' => 3,
      'slidesToScroll' => 1,
      'arrows' => true,
      'dots' => false,
      'autoplay' => false,
      'responsive' => [
        [
          'breakpoint' => 768,
          'settings' => [
            'slidesToShow' => 1,
            'slidesToScroll' => 1,
          ]
        ]
      ]
    ];
  }

  /**
   * Traite le bloc estimation avec récupération des données média et liens.
   *
   * @param array $variables Variables du template
   * @param object $content Contenu du bloc
   */
  private static function processEstimationBlock(&$variables, $content): void {
    $variables['estime_data'] = [
      'image' => self::getMediaImageUri($content, 'field_image_estime'),
      'image_alt' => self::getMediaImageAlt($content, 'field_image_estime'),
      'titre' => self::getFieldValue($content, 'field_titre_estime'),
      'description' => self::getFieldValue($content, 'field_description_estime'),
      'lien' => self::getLinkData($content, 'field_lien_estime', 'Comment vendre ou acheter ?'),
    ];
  }

  /**
   * Traite le bloc contact avec gestion des données multiples (téléphones).
   * Attache le formulaire de contact et les assets SweetAlert.
   *
   * @param array $variables Variables du template
   * @param object $content Contenu du bloc
   */
  private static function processContactBlock(&$variables, $content): void {
    $contact_data = self::buildContactData($content);

    $variables['contact_data'] = $contact_data;
    $variables['contact_form'] = \Drupal::formBuilder()->getForm('Drupal\forms\Form\ContactForm');

    // Assets
    $variables['#attached']['library'][] = 'forms/sweetalert';
    $variables['#theme'] = 'block__contact';
  }

  /**
   * Construit le tableau de données contact depuis les champs du bloc.
   *
   * @param object $content Contenu du bloc contact
   * @return array Données formatées du contact
   */
  private static function buildContactData($content): array {
    $contact_data = [
      'adresse' => '',
      'telephone' => [],
      'email' => '',
      'horaire' => '',
      'contact_nom' => '',
      'contact_telephone' => '',
      'contact_email' => '',
    ];

    // Champs simples
    $simple_fields = [
      'field_adresse' => 'adresse',
      'field_courriel' => 'email',
      'field_horaires' => 'horaire',
      'field_contact_nom' => 'contact_nom',
      'field_contact_telephone' => 'contact_telephone',
      'field_contact_email' => 'contact_email',
    ];

    foreach ($simple_fields as $field_name => $key) {
      if ($content->hasField($field_name) && !$content->get($field_name)->isEmpty()) {
        $value = ($key === 'adresse' || $key === 'horaire')
          ? $content->get($field_name)->processed
          : $content->get($field_name)->value;
        $contact_data[$key] = $value;
      }
    }

    // Téléphones multiples
    if ($content->hasField('field_telephone') && !$content->get('field_telephone')->isEmpty()) {
      foreach ($content->field_telephone as $phone_item) {
        if (!empty($phone_item->value)) {
          $contact_data['telephone'][] = $phone_item->value;
        }
      }
    }

    return $contact_data;
  }

  /**
   * Récupère l'URI d'une image depuis un champ média.
   *
   * @param object $entity Entité source
   * @param string $field_name Nom du champ média
   * @return string URI du fichier ou chaîne vide
   */
  private static function getMediaImageUri($entity, string $field_name): string {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return '';
    }

    $media = $entity->get($field_name)->entity;
    if (!$media || !$media->hasField('field_media_image')) {
      return '';
    }

    $file = $media->field_media_image->entity;
    return $file ? $file->getFileUri() : '';
  }

  /**
   * Récupère l'attribut alt d'une image média.
   *
   * @param object $entity Entité source
   * @param string $field_name Nom du champ média
   * @return string Texte alternatif ou chaîne vide
   */
  private static function getMediaImageAlt($entity, string $field_name): string {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return '';
    }

    $media = $entity->get($field_name)->entity;
    if (!$media || !$media->hasField('field_media_image')) {
      return '';
    }

    return $media->field_media_image->alt ?: '';
  }

  /**
   * Récupère la valeur d'un champ simple avec fallback.
   *
   * @param object $entity Entité source
   * @param string $field_name Nom du champ
   * @param string $default Valeur par défaut
   * @return string Valeur du champ ou défaut
   */
  private static function getFieldValue($entity, string $field_name, string $default = ''): string {
    return $entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()
      ? $entity->get($field_name)->value
      : $default;
  }

  /**
   * Récupère et formate les données d'un champ lien (Link ou URI).
   * Gère les liens internes et externes avec fallbacks.
   *
   * @param object $entity Entité source
   * @param string $field_name Nom du champ lien
   * @param string $default_text Texte par défaut du lien
   * @return array Tableau avec 'url' et 'text'
   */
  private static function getLinkData($entity, string $field_name, string $default_text = ''): array {
    $default = ['url' => '#', 'text' => $default_text];

    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return $default;
    }

    $link_field = $entity->get($field_name);
    $first_item = $link_field->first();

    // Type Link field (Drupal Link field)
    if (method_exists($first_item, 'getUrl')) {
      $url = $first_item->getUrl()->toString();
      $text = $first_item->getTitle() ?: $default_text;

      return [
        'url' => $url === '<nolink>' ? '#' : $url,
        'text' => $text,
      ];
    }

    // Type URI field
    if (isset($first_item->uri)) {
      $uri = $first_item->uri;

      // Conversion des liens internes
      if (strpos($uri, 'internal:') === 0) {
        $uri = \Drupal::request()->getSchemeAndHttpHost() . substr($uri, 9);
      }

      return [
        'url' => $uri,
        'text' => $first_item->title ?: $default_text,
      ];
    }

    return $default;
  }
}
