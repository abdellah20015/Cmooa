<?php

namespace Drupal\cmooa\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\cmooa\Services\ViewProcessors\SliderVentesProcessor;
use Drupal\cmooa\Services\ViewProcessors\LotsVenteProcessor;
use Drupal\cmooa\Services\ViewProcessors\CalendrierVentesProcessor;
use Drupal\cmooa\Services\ViewProcessors\EncheresRecentesProcessor;
use Drupal\cmooa\Services\ViewProcessors\NouvellesProcessor;
use Drupal\cmooa\Services\ViewProcessors\GaleriesProcessor;

class ViewProcessor {

  private static $entityTypeManager;
  private static $processors = [];

  /**
   * Traite une vue en déléguant à la sous-classe appropriée selon l'ID de la vue.
   */
  public static function processView(array &$variables, EntityTypeManagerInterface $entityTypeManager) {
    self::$entityTypeManager = $entityTypeManager;
    $view_id = $variables['view']->id();

    $processor = self::getProcessor($view_id);
    if ($processor) {
      $processor->process($variables);
    }
  }

  /**
   * Récupère ou crée une instance de la sous-classe de processeur pour une vue donnée.
   */
  private static function getProcessor(string $view_id) {
    if (!isset(self::$processors[$view_id])) {
      self::$processors[$view_id] = self::createProcessor($view_id);
    }
    return self::$processors[$view_id];
  }

  /**
   * Crée une instance de la sous-classe de processeur selon l'ID de la vue.
   */
  private static function createProcessor(string $view_id) {
    $processorClasses = [
      'slider_ventes' => SliderVentesProcessor::class,
      'lots_vente' => LotsVenteProcessor::class,
      'calendrier_ventes' => CalendrierVentesProcessor::class,
      'encheres_recentes' => EncheresRecentesProcessor::class,
      'nouvelles' => NouvellesProcessor::class,
      'galeries' => GaleriesProcessor::class,
    ];

    return isset($processorClasses[$view_id]) ? new $processorClasses[$view_id](self::$entityTypeManager) : null;
  }
}
