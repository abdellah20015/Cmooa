<?php

namespace Drupal\cmooa\Services\NodeProcessors;

use Drupal\node\NodeInterface;

class NouvelleNodeProcessor extends AbstractNodeProcessor {

  /**
   * Traite les variables pour un nœud de type 'nouvelle'.
   */
  public function process(array &$variables): void {
    $node = $variables['node'];
    $date_nouvelle = $this->getDateField($node, 'field_date_nouvelle');
    $categorie = $this->getFieldEntity($node, 'field_categorie_nouvelle');
    $description = $this->decodeAndCleanHtml($this->getFieldValue($node, 'field_description_nouvelle'));

    $variables['title'] = $node->getTitle();
    $variables['image_url'] = $this->getImageUrl($node, 'field_image_nouvelle') ?: '/medias/images/img-detail-nouvle.jpg';
    $variables['categorie_date'] = ($categorie ? $categorie->getName() : 'Non catégorisé') . ($date_nouvelle ? ' du ' . $date_nouvelle->format('d F Y') : '');
    $variables['description'] = $description;
    $variables['next_nouvelle'] = $this->getNextNouvelle($node);
  }

  /**
   * Récupère la prochaine nouvelle après le nœud donné.
   */
  private function getNextNouvelle(NodeInterface $node): array {
    $next_nid = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'nouvelle')
      ->condition('nid', $node->id(), '>')
      ->condition('status', 1)
      ->sort('field_date_nouvelle', 'ASC')
      ->range(0, 1)
      ->execute();

    if (!$next_nid) {
      return [];
    }

    $next_node = $this->entityTypeManager->getStorage('node')->load(reset($next_nid));
    return [
      'title' => $next_node->getTitle(),
      'lien' => "/nouvelle/{$next_node->id()}",
    ];
  }
}
