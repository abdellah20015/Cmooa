<?php

namespace Drupal\cmooa\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\cmooa\Services\NodeProcessors\PageNodeProcessor;
use Drupal\cmooa\Services\NodeProcessors\NouvelleNodeProcessor;
use Drupal\cmooa\Services\NodeProcessors\VenteNodeProcessor;
use Drupal\cmooa\Services\NodeProcessors\LotNodeProcessor;
use Drupal\cmooa\Services\NodeProcessors\LivestreamNodeProcessor;

class NodeProcessor {

  private static $entityTypeManager;
  private static $processors = [];

  public static function processNode(array &$variables, EntityTypeManagerInterface $entityTypeManager) {
    self::$entityTypeManager = $entityTypeManager;
    $node = $variables['node'];
    $bundle = $node->bundle();
    $request_uri = \Drupal::request()->getRequestUri();
    $view_mode = $variables['view_mode'] ?? 'full';

    if (preg_match('#^/livestream/(\d+)$#', $request_uri) && $bundle === 'vente' && $view_mode === 'livestream_full') {
      $processor = self::getProcessor('livestream');
    } else {
      $processor = self::getProcessor($bundle);
    }

    if ($processor) {
      $processor->process($variables);
    }
  }

  public static function processPageNode(array &$variables, EntityTypeManagerInterface $entityTypeManager = null) {
    $entityTypeManager = $entityTypeManager ?? \Drupal::service('entity_type.manager');
    $processor = self::getProcessor('page');
    $processor->process($variables);
  }

  private static function getProcessor(string $bundle) {
    if (!isset(self::$processors[$bundle])) {
      self::$processors[$bundle] = self::createProcessor($bundle);
    }
    return self::$processors[$bundle];
  }

  private static function createProcessor(string $bundle) {
    $processorClasses = [
      'page' => PageNodeProcessor::class,
      'nouvelle' => NouvelleNodeProcessor::class,
      'vente' => VenteNodeProcessor::class,
      'lot' => LotNodeProcessor::class,
      'livestream' => LivestreamNodeProcessor::class,
    ];

    return isset($processorClasses[$bundle]) ? new $processorClasses[$bundle](self::$entityTypeManager) : null;
  }
}
