<?php

namespace Drupal\newsletter\Service;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;

class NewsletterService {

  protected $formBuilder;
  protected $renderer;

  public function __construct(FormBuilderInterface $form_builder, RendererInterface $renderer) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  public function getNewsletterForm() {
    $form = $this->formBuilder->getForm('Drupal\newsletter\Form\NewsletterForm');

    // Vérifier s'il y a un message en attente
    $tempstore = \Drupal::service('tempstore.private')->get('newsletter');
    $message_data = $tempstore->get('message');

    if ($message_data && (time() - $message_data['timestamp']) < 5) {
      // Message récent (moins de 5 secondes)
      $form['#attributes']['data-newsletter-status'] = $message_data['status'];
      $form['#attributes']['data-newsletter-message'] = $message_data['text'];

      // Supprimer le message après l'avoir utilisé
      $tempstore->delete('message');
    }

    return $this->renderer->render($form);
  }
}
