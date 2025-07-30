<?php

namespace Drupal\forms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InscriptionForm extends FormBase {

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
    return 'forms_inscription_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $vente_id = NULL, $lot_id = NULL) {
    $form['#theme'] = 'forms_inscription_form';

    // Ajout des champs cachés pour vente_id et lot_id
    $form['vente_id'] = [
      '#type' => 'hidden',
      '#value' => $vente_id,
    ];
    $form['lot_id'] = [
      '#type' => 'hidden',
      '#value' => $lot_id,
    ];

    // Vérifier s'il y a un message de succès ou d'erreur dans la session
    $session = \Drupal::request()->getSession();
    $success_message = $session->get('inscription_form_success');
    $error_message = $session->get('inscription_form_error');

    if ($success_message) {
      $form['#attached']['drupalSettings']['forms']['success_message'] = $success_message;
      $session->remove('inscription_form_success');
    }

    if ($error_message) {
      $form['#attached']['drupalSettings']['forms']['error_message'] = $error_message;
      $session->remove('inscription_form_error');
    }

    // Formulaire d'inscription complet
    $form['civilite'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Civilité'),
      '#required' => FALSE,
      '#attributes' => [
        'placeholder' => 'Mr',
        'id' => 'civilite',
        'name' => 'civilite',
      ],
    ];

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

    $form['adresse'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Adresse'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'adresse',
        'name' => 'adresse',
      ],
    ];

    $form['cpt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code postal'),
      '#required' => FALSE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'cpt',
        'name' => 'cpt',
      ],
    ];

    $form['ville'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ville'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'ville',
        'name' => 'ville',
      ],
    ];

    $form['tel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Téléphone'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'tel',
        'name' => 'tel',
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

    $form['fax'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fax'),
      '#required' => FALSE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'fax',
        'name' => 'fax',
      ],
    ];

    $form['rib'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Références bancaires (RIB ou IBAN)'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'rib',
        'name' => 'rib',
      ],
    ];

    $form['namebanque'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nom de la banque'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'namebanque',
        'name' => 'namebanque',
      ],
    ];

    $form['adresse2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Adresse de la banque'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => '-',
        'id' => 'adresse2',
        'name' => 'adresse2',
      ],
    ];

    $form['conditions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('En envoyant ces informations, vous acceptez les <a href="/termes-conditions" title="">conditions d\'utilisation</a> de ce site'),
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'conditions',
        'name' => 'conditions',
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

    $tel = $form_state->getValue('tel');
    if ($tel && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $tel)) {
      $form_state->setErrorByName('tel', $this->t('Le numéro de téléphone n\'est pas valide.'));
    }

    $cpt = $form_state->getValue('cpt');
    if ($cpt && !preg_match('/^[0-9]{4,6}$/', $cpt)) {
      $form_state->setErrorByName('cpt', $this->t('Le code postal n\'est pas valide.'));
    }

    $rib = $form_state->getValue('rib');
    if ($rib && !preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4}[0-9]{7}([A-Z0-9]?){0,16}$|^[0-9]{20,23}$/', $rib)) {
      $form_state->setErrorByName('rib', $this->t('Le RIB/IBAN n\'est pas valide.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $session = \Drupal::request()->getSession();
    $current_user = \Drupal::currentUser();

    try {
      $values = $form_state->getValues();
      $vente_id = $values['vente_id'];
      $lot_id = $values['lot_id'];

      // Enregistrer l'inscription
      $inscription_id = \Drupal::database()->insert('forms_inscription_submissions')
        ->fields([
          'uid' => $current_user->id(),
          'lot_id' => $lot_id,
          'civilite' => $values['civilite'] ?? '',
          'nom' => $values['nom'],
          'prenom' => $values['prenom'],
          'adresse' => $values['adresse'],
          'cpt' => $values['cpt'] ?? '',
          'ville' => $values['ville'],
          'tel' => $values['tel'],
          'email' => $values['email'],
          'fax' => $values['fax'] ?? '',
          'rib' => $values['rib'],
          'namebanque' => $values['namebanque'],
          'adresse2' => $values['adresse2'],
          'conditions' => $values['conditions'] ? 1 : 0,
          'created' => time(),
        ])
        ->execute();

      // Associer l'inscription à la vente
      if ($vente_id) {
        \Drupal::database()->insert('vente_inscription')
          ->fields([
            'inscription_id' => $inscription_id,
            'vente_id' => $vente_id,
          ])
          ->execute();
      }

      $session->set('inscription_form_success', 'Votre inscription a été enregistrée avec succès ! Un identifiant et un mot de passe vous seront transmis sur votre adresse mail.');

    } catch (\Exception $e) {
      $session->set('inscription_form_error', 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.');
      $form_state->setRebuild(TRUE);
      return;
    }

    // Rediriger vers la page du lot pour refléter la mise à jour
    if ($lot_id) {
      $form_state->setRedirect('entity.node.canonical', ['node' => $lot_id]);
    } else {
      $form_state->setRedirect('<current>');
    }
  }
}
