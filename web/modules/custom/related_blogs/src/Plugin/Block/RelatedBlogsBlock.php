<?php

namespace Drupal\related_blogs\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Provides a 'Related Blogs' Block
 * 
 * @Block(
 *  id = "related_blogs_block",
 *  admin_label = @Translation("Related Blogs")
 * )
 */
class RelatedBlogsBlock extends BlockBase {
  
  /**
   * {@inheritDoc}
   */
  public function build() {
    // The entity manager service.
    $entityTypeManager = \Drupal::service('entity_type.manager');

    // Get the node id from the url.
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = '';
    if ($node instanceof NodeInterface) {
      $nid = $node->id();
    }
    
    // Get the author id of the current blog.
    $query = $entityTypeManager->getStorage('node')->getQuery()
      ->condition('status', 1)
      ->condition('type', 'blog')
      ->condition('nid', $nid)
      ->accessCheck(TRUE)
      ->execute();
    
    $users = $entityTypeManager->getStorage('node')->loadMultiple($query);
    foreach ($users as $user) {
      $current_author = $user->getOwner()->id();
    }

    // $nids = $entityTypeManager->getStorage('node')->getQuery()
    //   ->condition('status', 1)
    //   ->condition('type', 'blog')
    //   ->condition('nid', $nid, '!=')
    //   ->condition('uid', $current_author)
    //   ->sort('created', 'DESC')
    //   ->range(0, 3)
    //   ->accessCheck(TRUE)
    //   ->execute();

    // if (!empty($nids)) {
    //   $nodes = $entityTypeManager->getStorage('node')->loadMultiple($nids);
    // }
    $connection = Database::getConnection();
    $query = $connection->select('node_field_data', 'n');
    $query->condition('n.nid', $nid, '!=');
    $query->condition('n.type', 'blog');
    $query->condition('n.status', 1);
    $query->condition('n.uid', $current_author);
    $query->leftJoin('node__field_like_count', 'flc', 'n.nid = flc.entity_id');
    $query->fields('n', ['nid', 'title', 'type']);
    $query->fields('flc', ['field_like_count_likes']);
    $query->orderBy('flc.field_like_count_likes', 'DESC');
    $query->range(0, 3);

    $nodes = $query->execute()->fetchAll();
    $items = [];
    foreach ($nodes as $node) {
      $items[] = [
        '#type' => 'link',
        '#title' => $node->title,
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $node->nid]),
      ];
    }

    $build = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#cache' => [
        'contexts' => [
          'url.path',
        ],
      ],
    ];

    return $build;
  }

}
