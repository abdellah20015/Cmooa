<?php

namespace Drupal\cmooa\Services\NodeProcessors;

use Drupal\node\NodeInterface;

class PageNodeProcessor extends AbstractNodeProcessor {

  /**
   * Traite les variables pour un nœud de type 'page'.
   */
  public function process(array &$variables): void {
    $node = $variables['node'];
    $variables['image_slide'] = $this->getImageData($node, 'field_image_slide');
    $variables['page_title'] = $node->getTitle();
    $variables['page_body_processed'] = $this->getProcessedContent($node, 'body');
    $variables['page_body_decoded'] = $this->getDecodedContent($node, 'body');
  }
}
