<?php

namespace Drupal\forms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContactForm extends FormBase {

  protected $messenger;

  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  public function getFormId() {
    return 'forms_contact_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#theme'] = 'forms_contact_form';

    // Vérifier s'il y a un message de succès dans la session
    $session = \Drupal::request()->getSession();
    $success_message = $session->get('contact_form_success');
    $error_message = $session->get('contact_form_error');

    if ($success_message) {
      $form['#attached']['drupalSettings']['forms']['success_message'] = $success_message;
      $session->remove('contact_form_success');
    }

    if ($error_message) {
      $form['#attached']['drupalSettings']['forms']['error_message'] = $error_message;
      $session->remove('contact_form_error');
    }

    $form['nom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nom'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'nom',
        'name' => 'nom',
      ],
    ];

    $form['prenom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prénom'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'prenom',
        'name' => 'prenom',
      ],
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'email',
        'name' => 'email',
      ],
    ];

    $form['telephone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Téléphone'),
      '#required' => FALSE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'telephone',
        'name' => 'telephone',
      ],
    ];

    // Charger les termes de taxonomie pour le champ Sujet
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'contact_subjects']);
    $options = ['' => '- Sélectionner -'];
    foreach ($terms as $term) {
      $options[$term->id()] = $term->getName();
    }

    $form['sujet'] = [
      '#type' => 'select',
      '#title' => $this->t('Sujet'),
      '#options' => $options,
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'sujet',
        'name' => 'sujet',
        'class' => ['form-select'],
      ],
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Votre message'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'message',
        'name' => 'message',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Envoyer'),
      ],
    ];

    $form['#attached']['library'][] = 'forms/sweetalert';
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $email = $form_state->getValue('email');
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('L\'adresse email n\'est pas valide.'));
    }

    $telephone = $form_state->getValue('telephone');
    if ($telephone && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $telephone)) {
      $form_state->setErrorByName('telephone', $this->t('Le numéro de téléphone n\'est pas valide.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $session = \Drupal::request()->getSession();

    try {
      $values = $form_state->getValues();
      $term = Term::load($values['sujet']);

      \Drupal::database()->insert('forms_contact_submissions')
        ->fields([
          'nom' => $values['nom'],
          'prenom' => $values['prenom'],
          'email' => $values['email'],
          'telephone' => $values['telephone'],
          'sujet' => $term ? $term->getName() : '',
          'message' => $values['message'],
          'created' => time(),
        ])
        ->execute();

      // Stocker le message de succès dans la session
      $session->set('contact_form_success', 'Votre message a été envoyé avec succès !');

    } catch (\Exception $e) {
      // En cas d'erreur, stocker le message d'erreur
      $session->set('contact_form_error', 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer.');

      // Conserver les valeurs du formulaire en cas d'erreur
      $form_state->setRebuild(TRUE);
      return;
    }

    // Rediriger vers la même page pour vider le formulaire
    $form_state->setRedirect('<current>');
  }
}
