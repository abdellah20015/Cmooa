// JavaScript natif pour le formulaire newsletter
(function() {
  'use strict';

  // Fonction pour initialiser le formulaire
  function initNewsletterForm() {
    const form = document.querySelector('form[id*="newsletter-subscription-form"]');
    const emailInput = document.getElementById('email');

    if (!form || !emailInput) return;

    // Validation email
    function validateEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }

    // Gérer la soumission du formulaire
    form.addEventListener('submit', function(e) {
      e.preventDefault(); // Empêcher la soumission normale

      const email = emailInput.value.trim();

      // Validation côté client
      if (!email) {
        Swal.fire({
          icon: 'error',
          title: 'Erreur',
          text: 'Veuillez saisir votre adresse email',
          timer: 3000,
          showConfirmButton: false
        });
        return false;
      }

      if (!validateEmail(email)) {
        Swal.fire({
          icon: 'error',
          title: 'Erreur',
          text: 'Veuillez saisir une adresse email valide',
          timer: 3000,
          showConfirmButton: false
        });
        return false;
      }

      // Désactiver le bouton de soumission
      const submitBtn = form.querySelector('input[type="submit"]');
      const originalValue = submitBtn.value;
      submitBtn.disabled = true;
      submitBtn.value = 'En cours...';

      // Envoyer les données avec fetch
      const formData = new FormData(form);

      fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.text())
      .then(html => {
        // Parser la réponse pour extraire le message
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Chercher s'il y a un message dans la réponse
        const messageElement = doc.querySelector('[data-newsletter-message]');

        if (messageElement) {
          const status = messageElement.getAttribute('data-newsletter-status');
          const message = messageElement.getAttribute('data-newsletter-message');

          showMessage(status, message);

          // Vider le champ email si succès
          if (status === 'success') {
            emailInput.value = '';
          }
        } else {
          // Si pas de message spécifique, vérifier les erreurs de validation
          const errors = doc.querySelectorAll('.form-item--error-message');
          if (errors.length > 0) {
            showMessage('error', errors[0].textContent);
          } else {
            showMessage('success', 'Inscription réussie !');
            emailInput.value = '';
          }
        }
      })
      .catch(error => {
        console.error('Erreur:', error);
        showMessage('error', 'Une erreur est survenue. Veuillez réessayer.');
      })
      .finally(() => {
        // Réactiver le bouton
        submitBtn.disabled = false;
        submitBtn.value = originalValue;
      });
    });

    // Vérifier les messages au chargement de la page
    checkForMessages();
  }

  // Fonction pour afficher un message
  function showMessage(status, message) {
    let icon = 'info';
    let title = 'Information';

    switch(status) {
      case 'success':
        icon = 'success';
        title = 'Succès';
        break;
      case 'warning':
        icon = 'warning';
        title = 'Avertissement';
        break;
      case 'error':
        icon = 'error';
        title = 'Erreur';
        break;
    }

    Swal.fire({
      icon: icon,
      title: title,
      text: message,
      timer: 5000,
      showConfirmButton: false,
      toast: true,
      position: 'top-end'
    });
  }

  // Fonction pour vérifier et afficher les messages (pour compatibilité)
  function checkForMessages() {
    // Vérifier si drupalSettings existe et contient des données newsletter
    if (typeof drupalSettings !== 'undefined' &&
        drupalSettings.newsletter &&
        drupalSettings.newsletter.status &&
        drupalSettings.newsletter.message) {

      const status = drupalSettings.newsletter.status;
      const message = drupalSettings.newsletter.message;

      showMessage(status, message);

      // Nettoyer les données pour éviter la re-affichage
      if (drupalSettings.newsletter) {
        delete drupalSettings.newsletter;
      }
    }

    // Vérifier aussi dans les attributs data
    const messageElement = document.querySelector('[data-newsletter-message]');
    if (messageElement) {
      const status = messageElement.getAttribute('data-newsletter-status');
      const message = messageElement.getAttribute('data-newsletter-message');
      showMessage(status, message);

      // Supprimer les attributs pour éviter la re-affichage
      messageElement.removeAttribute('data-newsletter-status');
      messageElement.removeAttribute('data-newsletter-message');
    }
  }

  // Initialiser quand le DOM est prêt
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNewsletterForm);
  } else {
    initNewsletterForm();
  }

  // Réinitialiser après les changements de contenu (pour compatibilité)
  if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
          // Vérifier si le formulaire newsletter a été ajouté
          for (let i = 0; i < mutation.addedNodes.length; i++) {
            const node = mutation.addedNodes[i];
            if (node.nodeType === Node.ELEMENT_NODE) {
              if (node.querySelector('form[id*="newsletter-subscription-form"]')) {
                initNewsletterForm();
              }
            }
          }
        }
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

})();
