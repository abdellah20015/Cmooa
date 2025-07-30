<?php

namespace Drupal\cmooa\Services;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service pour traiter les données des régions.
 */
class RegionProcessor {

  // Mapping des réseaux sociaux
  private const SOCIAL_CLASSES = [
    'facebook' => 'face',
    'instagram' => 'insta',
  ];

  private const SOCIAL_ICONS = [
    'facebook' => 'icon-facebook',
    'instagram' => 'icon-instagram',
  ];

  /**
   * Traite les données selon la région.
   */
  public static function process(array &$variables, EntityTypeManagerInterface $entityTypeManager) {
    $region = $variables['region'];

    switch ($region) {
      case 'header':
        $variables += self::getHeaderData();
        break;
      case 'footer':
        $variables += self::getFooterData($entityTypeManager);
        break;
    }
  }

  /**
   * Données du header.
   */
  private static function getHeaderData(): array {
    return [
      'main_menu' => self::getMenu('menu-main', 2),
      'secondary_menu' => self::getMenu('secondary-menu', 1),
      'social_menu' => self::getSocialMenu(),
      'logo_url' => self::getLogoUrl(),
      'site_name' => \Drupal::config('system.site')->get('name'),
      'user_login_url' => Url::fromRoute('user.login')->toString(),
      'search_url' => Url::fromRoute('search.view')->toString(),
      'user_info' => self::getUserInfo(),
    ];
  }

  /**
   * Données du footer.
   */
  private static function getFooterData(EntityTypeManagerInterface $entityTypeManager): array {
    return [
      'main_menu' => self::getMenu('menu-main', 2),
      'footer_menu' => self::getMenu('footer-menu', 1),
      'social_menu' => self::getSocialMenu(),
      'logo_footer_url' => self::getLogoFooterUrl(),
      'infos_footer' => self::getFooterInfos($entityTypeManager),
      'newsletter_form' => \Drupal::service('newsletter.service')->getNewsletterForm(),
    ];
  }

  /**
   * Récupère un menu formaté.
   */
  private static function getMenu(string $menu_name, int $max_depth = 1): array {
    $parameters = new MenuTreeParameters();
    $parameters->setMaxDepth($max_depth)->onlyEnabledLinks();

    $menu_link_tree = \Drupal::service('menu.link_tree');
    $tree = $menu_link_tree->load($menu_name, $parameters);
    $tree = $menu_link_tree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    return self::buildMenuArray($tree);
  }

  /**
   * Construit le tableau de menu récursif.
   */
  private static function buildMenuArray(array $tree): array {
    $items = [];
    foreach ($tree as $element) {
      if (!$element->link->isEnabled()) continue;

      $item = [
        'title' => $element->link->getTitle(),
        'url' => $element->link->getUrlObject()->toString(),
        'active' => $element->inActiveTrail,
      ];

      if ($element->subtree) {
        $item['children'] = self::buildMenuArray($element->subtree);
      }

      $items[] = $item;
    }
    return $items;
  }

  /**
   * Menu social avec icônes.
   */
  private static function getSocialMenu(): array {
    $tree = self::getMenu('social-menu', 1);
    array_walk($tree, function(&$item) {
      $title_lower = strtolower($item['title']);
      $item['class'] = self::getSocialAttribute($title_lower, self::SOCIAL_CLASSES, 'social');
      $item['icon'] = self::getSocialAttribute($title_lower, self::SOCIAL_ICONS, 'icon-social');
    });
    return $tree;
  }

  /**
   * Attribut social (classe ou icône).
   */
  private static function getSocialAttribute(string $title, array $map, string $default): string {
    foreach ($map as $network => $value) {
      if (strpos($title, $network) !== FALSE) return $value;
    }
    return $default;
  }

  /**
   * URL du logo principal.
   */
  private static function getLogoUrl(): string {
    $theme_settings = \Drupal::config('system.theme.global');

    if ($theme_settings->get('logo.use_default')) {
      $active_theme = \Drupal::service('theme.manager')->getActiveTheme();
      return '/' . $active_theme->getPath() . '/logo.svg';
    }

    $logo_path = $theme_settings->get('logo.path');
    return $logo_path ? \Drupal::service('file_url_generator')->generateAbsoluteString($logo_path) : '';
  }

  /**
   * URL du logo footer.
   */
  private static function getLogoFooterUrl(): string {
    $active_theme = \Drupal::service('theme.manager')->getActiveTheme();
    return '/' . $active_theme->getPath() . '/logo.svg';
  }

  /**
   * Informations utilisateur.
   */
  private static function getUserInfo(): array {
    $current_user = \Drupal::currentUser();

    if (!$current_user->isAuthenticated()) {
      return [
        'is_logged_in' => FALSE,
        'username' => '',
        'user_id' => 0,
        'logout_url' => '',
        'profile_url' => '',
      ];
    }

    $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($current_user->id());

    return [
      'is_logged_in' => TRUE,
      'username' => $user_entity->getDisplayName(),
      'user_id' => $current_user->id(),
      'logout_url' => Url::fromRoute('user.logout')->toString(),
      'profile_url' => Url::fromRoute('entity.user.canonical', ['user' => $current_user->id()])->toString(),
    ];
  }

  /**
   * Informations du footer depuis le bloc.
   */
  private static function getFooterInfos(EntityTypeManagerInterface $entityTypeManager): array {
    $blocks = $entityTypeManager->getStorage('block_content')->loadByProperties(['type' => 'infos_footer']);
    $block = reset($blocks);

    return $block ? [
      'adresse' => self::getFieldValue($block, 'field_adresse'),
      'plan_lien' => self::getLinkField($block, 'field_plan_lien', 'Voir sur le plan'),
      'horaires' => self::getFieldValue($block, 'field_horaires'),
      'telephone' => self::getFieldValue($block, 'field_telephone'),
      'email' => self::getLinkField($block, 'field_email', 'cmooa@cmooa.com'),
      'realisation_lien' => self::getLinkField($block, 'field_realisation_lien'),
      'realisation_logo' => self::getMediaImage($block, 'field_realisation_logo'),
    ] : [];
  }

  /**
   * Valeur d'un champ.
   */
  private static function getFieldValue($entity, string $field_name): string {
    return $entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()
      ? $entity->get($field_name)->value
      : '';
  }

  /**
   * Champ lien formaté.
   */
  private static function getLinkField($entity, string $field_name, string $default_text = ''): array {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return ['url' => '#', 'text' => $default_text];
    }

    $field = $entity->get($field_name);
    $url = $field->uri;

    if (strpos($url, 'internal:') === 0) {
      $url = \Drupal::request()->getSchemeAndHttpHost() . substr($url, 9);
    }

    return [
      'url' => $url,
      'text' => $field->title ?: $default_text,
    ];
  }

  /**
   * Image média avec URI et alt.
   */
  private static function getMediaImage($entity, string $field_name): array {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return ['uri' => '', 'alt' => ''];
    }

    $media = $entity->get($field_name)->entity;
    if (!$media || !$media->hasField('field_media_image') || !$media->field_media_image->entity) {
      return ['uri' => '', 'alt' => ''];
    }

    return [
      'uri' => $media->field_media_image->entity->getFileUri(),
      'alt' => $media->field_media_image->alt ?: '',
    ];
  }
}
