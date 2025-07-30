<?php

namespace Drupal\newsletter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class NewsletterForm extends FormBase {

  public function getFormId() {
    return 'newsletter_subscription_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'newsletter/newsletter';

    // Supprimer les wrappers par défaut
    $form['#prefix'] = '';
    $form['#suffix'] = '';

    $form['email_label'] = [
      '#type' => 'markup',
      '#markup' => '<label for="email">Inscription à la newsletter</label>',
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'type' => 'text',
        'placeholder' => 'Renseignez votre email',
        'name' => 'email',
        'id' => 'email',
      ],
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => '',
      '#attributes' => [
        'type' => 'submit',
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', 'Email invalide');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');

    // Vérifier si l'email existe déjà
    $existing = \Drupal::database()->select('newsletter_subscriptions', 'ns')
      ->fields('ns', ['id'])
      ->condition('email', $email)
      ->execute()
      ->fetchField();

    if ($existing) {
      // Email déjà inscrit
      $status = 'warning';
      $message = 'Cette adresse email est déjà inscrite à la newsletter !';
    } else {
      // Sauvegarder le nouvel email
      \Drupal::database()->insert('newsletter_subscriptions')
        ->fields([
          'email' => $email,
          'subscribed_at' => time(),
          'status' => 1,
        ])
        ->execute();

      $status = 'success';
      $message = 'Inscription réussie ! Merci de vous être inscrit à notre newsletter.';

      // Vider le champ email après succès
      $form_state->setValue('email', '');
    }

    // Stocker le message dans une variable de session temporaire
    $tempstore = \Drupal::service('tempstore.private')->get('newsletter');
    $tempstore->set('message', [
      'status' => $status,
      'text' => $message,
      'timestamp' => time()
    ]);

    // IMPORTANT: Ne pas utiliser setRebuild() pour éviter le rafraîchissement
    // Au lieu de cela, on utilise une redirection vers la même page
    $form_state->setRedirect('<current>');
  }
}
