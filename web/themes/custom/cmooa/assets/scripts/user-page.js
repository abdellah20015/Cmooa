/**
 * Gestionnaire pour l'espace client utilisateur
 */

document.addEventListener('DOMContentLoaded', function() {
  // Gestion des messages d'alerte via SweetAlert2
  handleAlertMessages();

  // Initialiser les fonctionnalités de l'interface
  initializeUserInterface();

  // Initialiser la validation du formulaire
  validatePasswordForm();
});

/**
 * Gestion des messages d'alerte via SweetAlert2
 */
function handleAlertMessages() {
  // Vérifier les messages dans les balises meta
  const messageType = document.querySelector('meta[name="message-type"]')?.content;
  const messageText = document.querySelector('meta[name="message-text"]')?.content;

  if (messageType && messageText) {
    Swal.fire({
      icon: messageType,
      title: messageType === 'success' ? 'Succès' : 'Erreur',
      text: messageText,
      confirmButtonText: 'OK'
    });
  }
}

/**
 * Initialisation de l'interface utilisateur
 */
function initializeUserInterface() {
  // Gestionnaire pour les onglets
  initializeTabs();

  // Gestionnaire pour l'accordéon
  initializeAccordion();

  // Gestionnaire pour les formulaires
  initializeFormHandlers();
}

/**
 * Gestion des onglets
 */
function initializeTabs() {
  const menuLinks = document.querySelectorAll('.menu-client a');
  const tabContents = document.querySelectorAll('.inner-tabs');

  menuLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();

      // Retirer la classe active de tous les liens
      menuLinks.forEach(l => l.classList.remove('active'));

      // Ajouter la classe active au lien cliqué
      this.classList.add('active');

      // Masquer tous les contenus d'onglets
      tabContents.forEach(content => {
        content.style.display = 'none';
      });

      // Afficher le contenu de l'onglet correspondant
      const targetId = this.getAttribute('href').substring(1);
      const targetContent = document.getElementById(targetId);
      if (targetContent) {
        targetContent.style.display = 'block';
      }
    });
  });

  // Afficher le premier onglet par défaut
  if (tabContents.length > 0) {
    tabContents[0].style.display = 'block';
  }
}

/**
 * Gestion de l'accordéon
 */
function initializeAccordion() {
  const accordionHeaders = document.querySelectorAll('.acc-heading');

  accordionHeaders.forEach(header => {
    header.addEventListener('click', function(e) {
      e.preventDefault();

      const targetId = this.getAttribute('href').substring(1);
      const targetContent = document.getElementById(targetId);

      if (targetContent) {
        // Fermer tous les autres éléments de l'accordéon
        const allContents = document.querySelectorAll('.acc-content');
        allContents.forEach(content => {
          if (content !== targetContent) {
            content.style.display = 'none';
            content.parentElement.classList.remove('active');
          }
        });

        // Basculer l'élément actuel
        if (targetContent.style.display === 'block') {
          targetContent.style.display = 'none';
          this.parentElement.classList.remove('active');
        } else {
          targetContent.style.display = 'block';
          this.parentElement.classList.add('active');
        }
      }
    });
  });
}

/**
 * Initialisation des gestionnaires de formulaires
 */
function initializeFormHandlers() {
  // Gestionnaire pour le formulaire d'informations utilisateur
  const userInfoForm = document.getElementById('user-info-form');
  if (userInfoForm) {
    userInfoForm.addEventListener('submit', function(e) {
      e.preventDefault();

      const formData = new FormData(this);

      // Créer un formulaire temporaire pour soumettre les données
      const tempForm = document.createElement('form');
      tempForm.method = 'POST';
      tempForm.action = '';
      tempForm.style.display = 'none';

      // Ajouter tous les champs du formulaire
      for (let [key, value] of formData.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        tempForm.appendChild(input);
      }

      // Ajouter le bouton de soumission
      const submitInput = document.createElement('input');
      submitInput.type = 'hidden';
      submitInput.name = 'update_user_info';
      submitInput.value = 'Envoyer';
      tempForm.appendChild(submitInput);

      document.body.appendChild(tempForm);
      tempForm.submit();
    });
  }

  // Gestionnaire pour le formulaire de changement de mot de passe
  const changePasswordForm = document.querySelector('.change-psss');
  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
      e.preventDefault();

      const newPass = document.getElementById('new-pass').value;
      const confPass = document.getElementById('conf-pass').value;

      // Validation côté client
      if (newPass !== confPass) {
        Swal.fire({
          icon: 'error',
          title: 'Erreur',
          text: 'Les mots de passe ne correspondent pas.',
          confirmButtonText: 'OK'
        });
        return false;
      }

      if (newPass.length < 6) {
        Swal.fire({
          icon: 'error',
          title: 'Erreur',
          text: 'Le mot de passe doit contenir au moins 6 caractères.',
          confirmButtonText: 'OK'
        });
        return false;
      }

      // Si la validation passe, soumettre le formulaire
      const formData = new FormData(this);

      // Créer un formulaire temporaire pour soumettre les données
      const tempForm = document.createElement('form');
      tempForm.method = 'POST';
      tempForm.action = '';
      tempForm.style.display = 'none';

      // Ajouter tous les champs du formulaire
      for (let [key, value] of formData.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        tempForm.appendChild(input);
      }

      // Ajouter le bouton de soumission
      const submitInput = document.createElement('input');
      submitInput.type = 'hidden';
      submitInput.name = 'change_password';
      submitInput.value = 'Changer mot de passe';
      tempForm.appendChild(submitInput);

      document.body.appendChild(tempForm);
      tempForm.submit();
    });
  }
}

/**
 * Fonction pour basculer le mode édition des informations utilisateur
 */
function toggleEditMode() {
  const form = document.getElementById('user-info-form');
  const inputs = form.querySelectorAll('input[type="text"], input[type="checkbox"]');
  const submitRow = document.getElementById('submit-row');
  const editLink = document.querySelector('.edit-infos');

  inputs.forEach(input => {
    input.readOnly = !input.readOnly;
    if (input.type === 'checkbox') {
      input.disabled = !input.disabled;
    }
  });

  if (submitRow.style.display === 'none' || !submitRow.style.display) {
    submitRow.style.display = 'block';
    editLink.textContent = 'Annuler';
  } else {
    submitRow.style.display = 'none';
    editLink.textContent = 'Modifier mes informations';
  }
}

/**
 * Validation du formulaire de changement de mot de passe
 */
function validatePasswordForm() {
  const form = document.querySelector('.change-psss');
  if (!form) return;

  // Cette fonction est maintenant gérée dans initializeFormHandlers()
  // mais on la garde pour la compatibilité
}

// Rendre la fonction toggleEditMode disponible globalement
window.toggleEditMode = toggleEditMode;
