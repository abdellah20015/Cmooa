let youtubeIframe = null;

const streamContainer = document.getElementById('youtube-stream');
if (streamContainer) {
  const { venteId, lotId, isAdmin, userId, youtubeUrl } = streamContainer.dataset;
  const isAdminMode = isAdmin === '1';

  /**
   * Extrait l'ID vidéo YouTube de l'URL
   */
  function getYouTubeVideoId(url) {
    if (!url) return null;
    const match = url.match(/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/);
    return (match && match[7].length === 11) ? match[7] : null;
  }

  /**
   * Crée l'iframe YouTube pour utilisateurs normaux
   */
  function createYouTubeIframe(videoId) {
    if (!videoId) {
      showError('Aucun stream configuré', 'L\'administrateur doit configurer l\'URL YouTube Live.');
      return;
    }

    const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=0&controls=1&showinfo=0&rel=0&iv_load_policy=3&modestbranding=1&origin=${window.location.origin}`;

    streamContainer.innerHTML = `
      <div style="display: flex; justify-content: center; align-items: center; background: #f8f9fa; border-radius: 10px; padding: 20px; width: 100%; height: 100%;">
        <iframe
          src="${embedUrl}"
          width="80%"
          height="450"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
          style="max-width: 800px; border-radius: 10px; box-shadow: 0 8px 25px rgba(0,0,0,0.15);"
          title="YouTube Live Stream">
        </iframe>
      </div>
    `;
  }

  /**
   * Crée l'interface admin avec YouTube Live
   */
  function createAdminInterface(videoId) {
    if (!videoId) {
      showError('Aucun stream configuré', 'Veuillez configurer l\'URL YouTube Live.');
      return;
    }

    const liveUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=0&controls=1&showinfo=0&rel=0&iv_load_policy=3&modestbranding=1&origin=${window.location.origin}`;

    streamContainer.innerHTML = `
      <iframe
        src="${liveUrl}"
        style="width: 100%; height: 600px; border: none; border-radius: 0 0 10px 10px; display: block;"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
        title="YouTube Live Stream">
      </iframe>
    `;
  }

  /**
   * Affiche un message d'erreur dans le container
   */
  function showError(title, message) {
    streamContainer.innerHTML = `
      <div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; height: 100%; display: flex; align-items: center; justify-content: center;">
        <div style="text-align: center;">
          <h4>${title}</h4>
          <p>${message}</p>
        </div>
      </div>
    `;
  }

  /**
   * Rafraîchit le stream YouTube
   */
  window.refreshYouTubeStream = () => {
    const currentUrl = streamContainer.dataset.youtubeUrl || '';
    const currentVideoId = getYouTubeVideoId(currentUrl);

    if (currentVideoId) {
      isAdminMode ? createAdminInterface(currentVideoId) : createYouTubeIframe(currentVideoId);
      showNotification('✅ Stream actualisé avec succès', 'success');
    } else {
      location.reload();
    }
  };

  /**
   * Affiche le formulaire d'adjudication
   */
  window.showAdjugeForm = () => {
    const currentLotElement = document.querySelector('.nbr-lot span');
    const lotId = currentLotElement?.textContent;

    if (!lotId) {
      showNotification('❌ Aucun lot sélectionné', 'error');
      return;
    }

    if (confirm(`Êtes-vous sûr de vouloir adjuger le lot #${lotId} ?`)) {
      submitForm({ lot_id: lotId, submit_adjuged: '1' });
      showNotification('⏳ Adjudication en cours...', 'info');
    }
  };

  /**
   * Soumet un formulaire avec les données spécifiées
   */
  function submitForm(data) {
    const form = document.createElement('form');
    form.method = 'post';
    form.style.display = 'none';

    Object.entries(data).forEach(([name, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
  }

  /**
   * Affiche une notification
   */
  function showNotification(message, type = 'info') {
    const colors = {
      success: '#28a745',
      error: '#dc3545',
      info: '#007bff'
    };

    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px;
      color: white; font-weight: bold; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      transition: all 0.3s ease; background: ${colors[type] || colors.info};
    `;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => notification.remove(), 3000);
  }

  /**
   * Gère la conversion de devises
   */
  function setupCurrencyConversion() {
    document.querySelectorAll('.convert').forEach(button => {
      button.addEventListener('click', function() {
        const currency = this.dataset.currency;

        // Mise à jour des boutons actifs
        document.querySelectorAll('.convert').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');

        // Mise à jour des valeurs affichées
        document.querySelectorAll('[data-dh][data-eur]').forEach(element => {
          element.textContent = element.dataset[currency];
        });

        // Mise à jour des symboles de devise
        document.querySelectorAll('.currency-symbol').forEach(symbol => {
          symbol.textContent = currency === 'dh' ? 'Dhs' : '€';
        });

        // Mise à jour du pas d'enchère
        const pasElement = document.querySelector('.pas-value');
        if (pasElement) pasElement.textContent = pasElement.dataset[currency];

        // Mise à jour des options des selects d'enchère
        document.querySelectorAll('select[name="enchere_value"] option').forEach(option => {
          if (option.dataset[currency]) option.textContent = option.dataset[currency];
        });
      });
    });
  }

  /**
   * Gère la soumission des enchères
   */
  function setupBidding() {
    document.querySelectorAll('form').forEach(form => {
      const enchereSubmit = form.querySelector('input[name="submit_enchere"]');
      if (!enchereSubmit) return;

      form.addEventListener('submit', function(e) {
        const enchereSelect = form.querySelector('select[name="enchere_value"]');
        const lotIdField = form.querySelector('input[name="lot_id"]');

        if (!enchereSelect || !lotIdField || !enchereSelect.value || !lotIdField.value) {
          e.preventDefault();
          showNotification('❌ Formulaire d\'enchère invalide.', 'error');
          return;
        }

        const selectedOption = enchereSelect.options[enchereSelect.selectedIndex];
        const displayValue = selectedOption.textContent;
        const lotId = lotIdField.value;

        if (!confirm(`Confirmer votre enchère de ${displayValue} pour le lot #${lotId} ?`)) {
          e.preventDefault();
          return;
        }

        showNotification('⏳ Enchère en cours de traitement...', 'info');
      });
    });
  }

  /**
   * Met à jour automatiquement les enchères
   */
  function setupAutoUpdate() {
    if (isAdminMode) return;

    const updateInterval = setInterval(() => {
      fetch(window.location.href, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Cache-Control': 'no-cache'
        }
      })
      .then(response => response.text())
      .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        updateElement('.c-value-enchere', doc, (oldValue, newValue) => {
          if (oldValue !== newValue) {
            showNotification('🔄 Nouvelle enchère: ' + newValue, 'info');
            highlightElement('.c-value-enchere');
          }
        });

        updateElement('.next-value', doc);
        updateSelectOptions('select[name="enchere_value"]', doc);
      })
      .catch(error => console.error('Erreur mise à jour enchères:', error));
    }, 10000);

    window.addEventListener('beforeunload', () => clearInterval(updateInterval));
  }

  /**
   * Met à jour un élément DOM
   */
  function updateElement(selector, doc, callback) {
    const element = document.querySelector(selector);
    const newElement = doc.querySelector(selector);

    if (element && newElement) {
      const oldValue = element.textContent.trim();
      const newValue = newElement.textContent.trim();

      element.textContent = newValue;
      if (newElement.dataset.dh) element.dataset.dh = newElement.dataset.dh;
      if (newElement.dataset.eur) element.dataset.eur = newElement.dataset.eur;

      if (callback) callback(oldValue, newValue);
    }
  }

  /**
   * Met à jour les options d'un select
   */
  function updateSelectOptions(selector, doc) {
    const selectElement = document.querySelector(selector);
    const newSelect = doc.querySelector(selector);
    if (selectElement && newSelect) {
      selectElement.innerHTML = newSelect.innerHTML;
    }
  }

  /**
   * Ajoute un effet de surbrillance à un élément
   */
  function highlightElement(selector) {
    const element = document.querySelector(selector);
    if (!element) return;

    element.style.backgroundColor = '#28a745';
    element.style.color = 'white';
    setTimeout(() => {
      element.style.backgroundColor = '';
      element.style.color = '';
    }, 2000);
  }

  /**
   * Gère les messages de succès dans l'URL
   */
  function handleSuccessMessages() {
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.get('enchere_success')) {
      showNotification('✅ Enchère soumise avec succès !', 'success');
    }
    if (urlParams.get('adjuge_success')) {
      showNotification('🔨 Lot adjugé avec succès !', 'success');
    }

    if (urlParams.get('enchere_success') || urlParams.get('adjuge_success')) {
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }

  // Initialisation
  const videoId = getYouTubeVideoId(youtubeUrl);

  if (videoId) {
    isAdminMode ? createAdminInterface(videoId) : createYouTubeIframe(videoId);
  } else if (youtubeUrl) {
    showError('URL YouTube invalide', `L'URL fournie n'est pas valide. URL: ${youtubeUrl}`);
  } else {
    streamContainer.innerHTML = `
      <div style="padding: 20px; background: #d1ecf1; color: #0c5460; border: 1px solid #b6ebfc; border-radius: 5px; height: 100%; display: flex; align-items: center; justify-content: center;">
        <div style="text-align: center;">
          <h4>En attente du livestream</h4>
          <p>Le livestream sera disponible une fois configuré par l'administrateur.</p>
        </div>
      </div>
    `;
  }

  // Configuration des fonctionnalités
  setupCurrencyConversion();
  setupBidding();
  setupAutoUpdate();
  handleSuccessMessages();
}
