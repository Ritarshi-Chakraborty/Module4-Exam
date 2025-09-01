<?php

namespace Drupal\blogs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns JSON data for all 'blog' content nodes.
 */
class BlogApiController extends ControllerBase {
  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getAllBlogs(Request $request) {
    // Load all nodes of type 'blog'.
    $blog_list = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'blog')
      ->accessCheck(TRUE);
    
    // Check if author name has been passed via url.
    if ($request->query->get('author')) {
      $author_name = $request->query->get('author');
      $blog_list->condition('uid.entity.roles', $author_name, '=');
    }

    // Check if tag has been passed.
    if ($request->query->get('tag')) {
      $tag_name = $request->query->get('tag');
      // Load terms by properties: Term name.
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'name' => $tag_name,
        ]);
      
      if (!empty($terms)) {
        // Assuming all terms have a unique Id.
        $term = reset($terms);
        $term_id = $term->id();

        // Get the database connection.
        $database = \Drupal::database();
        $subquery = $database->select('node__field_blog_tags', 't')
          ->fields('t', ['entity_id']);

        $term_list = $database->select('node__field_blog_tags', 't')
          ->fields('t', ['field_blog_tags_target_id'])
          ->condition('t.field_blog_tags_target_id', $term_id, '=');

        $blog_list->condition('nid', $subquery, 'IN');
      }
    }
    
    $blog_details = $blog_list->execute();
    $blogs = $this->entityTypeManager->getStorage('node')->loadMultiple($blog_details);
    // Check if any `blog` exists.
    if(empty($blogs)) {
      return new JsonResponse(['blank' => 'No blogs found']);
    }

    // Proceed if we get some `blog`
    $blog_data = [];
    foreach ($blogs as $blog) {
      // Getting the author.
      $blog_author = $blog->getOwner();

      // Getting the tags.
      $blog_tags = [];
      $referenced_terms = $blog->get('field_blog_tags')->referencedEntities();

      foreach ($referenced_terms as $term) {
        $blog_tags[] = [
          'tag_name' => $term->getName(),
        ];
      }
      
      $blog_data[] = [
        'title' => $blog->getTitle(),
        'published_time' => date('Y-m-d', $blog->getCreatedTime()),
        'author' => $blog_author->get('field_full_name')->value,
        'tags' => $blog_tags,
        'body' => $blog->get('field_body')->value,
      ];
    }

    return new JsonResponse($blog_data);
  }
}
