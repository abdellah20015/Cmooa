/**
 * Gestionnaire des favoris pour les lots
 */
(function() {
  'use strict';

  const FavorisManager = {

    /**
     * Initialise le gestionnaire de favoris
     */
    init: function() {
      this.bindEvents();
    },

    /**
     * Lie les événements
     */
    bindEvents: function() {
      const favorisBtn = document.querySelector('.add-favoris');

      if (favorisBtn) {
        favorisBtn.addEventListener('click', this.handleFavorisClick.bind(this));
      }
    },

    /**
     * Gère le clic sur le bouton favoris
     */
    handleFavorisClick: function(e) {
      e.preventDefault();

      const btn = e.currentTarget;
      const lotId = btn.getAttribute('data-lot-id');
      const lotTitle = btn.getAttribute('data-lot-title');
      const lotAuthor = btn.getAttribute('data-lot-author');

      // Vérifier si l'utilisateur est connecté
      if (!window.userAuthenticated) {
        this.showNotification('Vous devez être connecté pour ajouter des favoris', 'info');
        return;
      }

      // Désactiver le bouton temporairement
      btn.style.pointerEvents = 'none';

      // Déterminer l'action
      const isCurrentlyFavoris = btn.classList.contains('active');
      const action = isCurrentlyFavoris ? 'remove' : 'add';

      // Optimistic UI - changer l'interface immédiatement
      this.toggleFavorisUI(btn, !isCurrentlyFavoris);

      // Envoyer la requête au serveur
      this.submitFavorisForm(lotId, action, btn, isCurrentlyFavoris);
    },

    /**
     * Met à jour l'interface utilisateur du bouton favoris
     */
    toggleFavorisUI: function(btn, isActive) {
      const icon = btn.querySelector('.favoris-icon');

      if (isActive) {
        btn.classList.add('active');
        icon.textContent = '♥';
      } else {
        btn.classList.remove('active');
        icon.textContent = '♡';
      }
    },

    /**
     * Soumet le formulaire de favoris
     */
    submitFavorisForm: function(lotId, action, btn, wasActive) {
      const form = document.getElementById('favoris-form');

      if (!form) {
        console.error('Formulaire favoris non trouvé');
        this.revertFavorisUI(btn, wasActive);
        return;
      }

      // Mettre à jour l'action dans le formulaire
      const actionInput = form.querySelector('input[name="favoris_action"]');
      if (actionInput) {
        actionInput.value = action;
      }

      // Créer une requête fetch pour soumettre le formulaire
      const formData = new FormData(form);

      fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Erreur réseau');
        }
        return response.text();
      })
      .then(data => {
        // Succès
        const message = action === 'add' ? 'Lot ajouté aux favoris' : 'Lot retiré des favoris';
        const type = action === 'add' ? 'success' : 'info';
        this.showNotification(message, type);

        // Mettre à jour l'état global
        window.isFavoris = action === 'add';
      })
      .catch(error => {
        console.error('Erreur lors de la mise à jour des favoris:', error);

        // Revenir à l'état précédent en cas d'erreur
        this.revertFavorisUI(btn, wasActive);
        this.showNotification('Erreur lors de la mise à jour des favoris', 'error');
      })
      .finally(() => {
        // Réactiver le bouton
        btn.style.pointerEvents = 'auto';
      });
    },

    /**
     * Remet l'interface dans son état précédent
     */
    revertFavorisUI: function(btn, wasActive) {
      this.toggleFavorisUI(btn, wasActive);
    },

    /**
     * Affiche une notification
     */
    showNotification: function(message, type = 'info') {
      // Supprimer les notifications existantes
      const existingNotifications = document.querySelectorAll('.favoris-notification');
      existingNotifications.forEach(notification => {
        notification.remove();
      });

      // Créer la nouvelle notification
      const notification = document.createElement('div');
      notification.className = `favoris-notification ${type}`;
      notification.textContent = message;

      // Ajouter au DOM
      document.body.appendChild(notification);

      // Animer l'apparition
      setTimeout(() => {
        notification.classList.add('show');
      }, 100);

      // Supprimer après 3 secondes
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 300);
      }, 3000);
    },

    /**
     * Récupère les favoris depuis le sessionStorage (pour compatibilité)
     */
    getSessionFavoris: function() {
      try {
        const favoris = sessionStorage.getItem('user_favoris');
        return favoris ? JSON.parse(favoris) : [];
      } catch (e) {
        console.warn('Erreur lors de la lecture des favoris en session:', e);
        return [];
      }
    },

    /**
     * Sauvegarde les favoris dans le sessionStorage (pour compatibilité)
     */
    saveSessionFavoris: function(favoris) {
      try {
        sessionStorage.setItem('user_favoris', JSON.stringify(favoris));
      } catch (e) {
        console.warn('Erreur lors de la sauvegarde des favoris en session:', e);
      }
    },

    /**
     * Synchronise les favoris avec le sessionStorage
     */
    syncWithSession: function(lotId, action) {
      const favoris = this.getSessionFavoris();

      if (action === 'add' && !favoris.includes(lotId)) {
        favoris.push(lotId);
      } else if (action === 'remove') {
        const index = favoris.indexOf(lotId);
        if (index > -1) {
          favoris.splice(index, 1);
        }
      }

      this.saveSessionFavoris(favoris);
    }
  };

  // Initialiser quand le DOM est prêt
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      FavorisManager.init();
    });
  } else {
    FavorisManager.init();
  }

  // Exposer globalement pour le débogage
  window.FavorisManager = FavorisManager;

})();
