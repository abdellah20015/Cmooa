/**
 * Gestionnaire des enchères pour les lots
 * Ce fichier gère la conversion des devises et les interactions sur la page de lot
 * Version mise à jour avec support Livestream
 */

class LotAuctionManager {
    constructor(lotData, userAuthenticated = false, isFavoris = false) {
        this.lotData = lotData;
        this.userAuthenticated = userAuthenticated;
        this.isFavoris = isFavoris;
        this.currentCurrency = 'dh'; // devise par défaut

        this.init();
    }

    init() {
        this.bindCurrencyConverters();
        this.bindFavorisToggle();
        this.bindAuctionForm();
        this.bindPropositionSelection();
    }

    /**
     * Gère la conversion entre les devises (DH/EUR)
     */
    bindCurrencyConverters() {
        const converts = document.querySelectorAll('.btns-covert .convert');

        converts.forEach(convert => {
            convert.addEventListener('click', (e) => {
                e.preventDefault();

                // Mise à jour de l'état actif
                converts.forEach(c => c.classList.remove('active'));
                convert.classList.add('active');

                // Récupération de la devise sélectionnée
                const currency = convert.getAttribute('data-currency');
                this.currentCurrency = currency;

                // Mise à jour des valeurs affichées
                this.updateCurrencyDisplay(currency);
            });
        });
    }

    /**
     * Met à jour l'affichage des prix selon la devise sélectionnée
     */
    updateCurrencyDisplay(currency) {
        // Mise à jour de l'enchère en cours
        const currentValue = document.querySelector('.c-value-enchere');
        if (currentValue) {
            if (this.lotData && this.lotData.enchere_dh && this.lotData.enchere_eur) {
                // Mode avec données lotData (page lot classique)
                currentValue.textContent = currency === 'dh' ?
                    this.lotData.enchere_dh :
                    this.lotData.enchere_eur;
            } else if (currentValue.dataset.dh && currentValue.dataset.eur) {
                // Mode avec data attributes (livestream)
                const value = currency === 'dh' ? currentValue.dataset.dh : currentValue.dataset.eur;
                currentValue.textContent = value;
            }
        }

        // Mise à jour de l'enchère suivante
        const nextValue = document.querySelector('.next-value') || document.querySelector('.next-enchere .value-enchere span:not(.label-enchere)');
        if (nextValue) {
            if (this.lotData && this.lotData.next_enchere_dh && this.lotData.next_enchere_eur) {
                // Mode avec données lotData
                nextValue.textContent = currency === 'dh' ?
                    this.lotData.next_enchere_dh :
                    this.lotData.next_enchere_eur;
            } else if (nextValue.dataset.dh && nextValue.dataset.eur) {
                // Mode avec data attributes
                const value = currency === 'dh' ? nextValue.dataset.dh : nextValue.dataset.eur;
                nextValue.textContent = value;
            }
        }

        // Mise à jour du pas d'enchère (spécifique au livestream)
        const pasValue = document.querySelector('.pas-value');
        if (pasValue && pasValue.dataset.dh && pasValue.dataset.eur) {
            const value = currency === 'dh' ? pasValue.dataset.dh : pasValue.dataset.eur;
            pasValue.textContent = value;
        }

        // Mise à jour des symboles de devise
        const currencySymbols = document.querySelectorAll('.currency-symbol, sub');
        currencySymbols.forEach(symbol => {
            if (symbol.textContent === 'Dhs' || symbol.textContent === '€' || symbol.textContent === 'DH') {
                symbol.textContent = currency === 'dh' ? 'Dhs' : '€';
            }
        });

        // Mise à jour des propositions visibles (page lot classique)
        const propositionItems = document.querySelectorAll('.lis-propo .item-propo');
        if (propositionItems.length > 0 && this.lotData && this.lotData.enchere_propositions) {
            propositionItems.forEach((item, index) => {
                if (this.lotData.enchere_propositions[index]) {
                    item.textContent = currency === 'dh' ?
                        this.lotData.enchere_propositions[index].dh :
                        this.lotData.enchere_propositions[index].eur;
                }
            });
        }

        // Mise à jour des propositions avec data attributes (livestream)
        const propositionsData = document.querySelectorAll('.item-propo[data-dh]');
        propositionsData.forEach(proposition => {
            const value = currency === 'dh' ? proposition.dataset.dh : proposition.dataset.eur;
            if (value) {
                proposition.textContent = value;
            }
        });

        // Mise à jour des options du select
        const selectOptions = document.querySelectorAll('#enchere_value option, #enchere_value_admin option');
        if (selectOptions.length > 0) {
            selectOptions.forEach((option, index) => {
                // Mode avec données lotData
                if (this.lotData && this.lotData.enchere_propositions && this.lotData.enchere_propositions[index]) {
                    option.textContent = currency === 'dh' ?
                        this.lotData.enchere_propositions[index].dh :
                        this.lotData.enchere_propositions[index].eur;
                    option.value = this.lotData.enchere_propositions[index].value;
                }
                // Mode avec data attributes
                else if (option.dataset.dh && option.dataset.eur) {
                    const value = currency === 'dh' ? option.dataset.dh : option.dataset.eur;
                    option.textContent = value;
                }
            });
        }

        // Mise à jour des lots adjugés (livestream)
        const adjugedValues = document.querySelectorAll('.value-adjuge');
        adjugedValues.forEach(value => {
            if (value.dataset.dh && value.dataset.eur) {
                const displayValue = currency === 'dh' ? value.dataset.dh : value.dataset.eur;
                value.textContent = displayValue;
            }
        });
    }

    /**
     * Gère le toggle des favoris
     */
    bindFavorisToggle() {
        if (!this.userAuthenticated) return;

        const favorisBtn = document.querySelector('.add-favoris');
        const favorisForm = document.getElementById('favoris-form');

        if (favorisBtn && favorisForm) {
            favorisBtn.addEventListener('click', (e) => {
                e.preventDefault();

                // Soumission du formulaire
                favorisForm.submit();

                // Mise à jour visuelle immédiate
                favorisBtn.classList.toggle('active');
                this.isFavoris = !this.isFavoris;
            });
        }
    }

    /**
     * Gère la soumission du formulaire d'enchère
     */
    bindAuctionForm() {
        const auctionSubmitBtn = document.querySelector('.sindentifier[onclick]');

        if (auctionSubmitBtn) {
            // Remplacer l'onclick par un event listener propre
            auctionSubmitBtn.removeAttribute('onclick');
            auctionSubmitBtn.addEventListener('click', (e) => {
                e.preventDefault();

                const form = auctionSubmitBtn.closest('form');
                if (form) {
                    // Validation optionnelle avant soumission
                    if (this.validateAuctionForm(form)) {
                        form.submit();
                    }
                }
            });
        }
    }

    /**
     * Gère la sélection des propositions d'enchères (nouveau pour livestream)
     */
    bindPropositionSelection() {
        const propositions = document.querySelectorAll('.item-propo');
        const select = document.querySelector('#enchere_value, #enchere_value_admin');

        propositions.forEach(proposition => {
            proposition.addEventListener('click', () => {
                const value = proposition.dataset.value;
                if (select && value) {
                    select.value = value;
                }

                // Mise à jour visuelle
                propositions.forEach(p => p.classList.remove('selected'));
                proposition.classList.add('selected');
            });
        });
    }

    /**
     * Valide le formulaire d'enchère
     */
    validateAuctionForm(form) {
        const enchereSelect = form.querySelector('#enchere_value, #enchere_value_admin');

        if (!enchereSelect || !enchereSelect.value) {
            alert('Veuillez sélectionner un montant d\'enchère');
            return false;
        }

        return true;
    }

    /**
     * Met à jour les données du lot (utile pour les mises à jour en temps réel)
     */
    updateLotData(newLotData) {
        this.lotData = { ...this.lotData, ...newLotData };
        this.updateCurrencyDisplay(this.currentCurrency);
    }

    /**
     * Obtient la devise actuelle
     */
    getCurrentCurrency() {
        return this.currentCurrency;
    }

    /**
     * Force la mise à jour de l'affichage
     */
    refresh() {
        this.updateCurrencyDisplay(this.currentCurrency);
    }
}

/**
 * Classe spécialisée pour le livestream
 * Hérite de LotAuctionManager avec des fonctionnalités spécifiques
 */
class LivestreamCurrencyManager extends LotAuctionManager {
    constructor(lotData = null, userAuthenticated = false, isFavoris = false) {
        super(lotData, userAuthenticated, isFavoris);
    }

    init() {
        super.init();
        this.bindLivestreamSpecificEvents();
    }

    /**
     * Événements spécifiques au livestream
     */
    bindLivestreamSpecificEvents() {
        // Gérer les mises à jour en temps réel
        this.setupRealTimeUpdates();

        // Gérer les changements de lot actuel
        this.bindCurrentLotChange();
    }

    /**
     * Configuration des mises à jour en temps réel
     */
    setupRealTimeUpdates() {
        // Écouter les événements WebSocket ou autres mises à jour temps réel
        // Cette fonction peut être étendue selon les besoins spécifiques
        if (window.WebSocket && window.agoraStreamChannel) {
            // Exemple d'intégration avec Agora ou WebSocket
            console.log('Setup real-time updates for livestream');
        }
    }

    /**
     * Gère les changements de lot actuel pendant le livestream
     */
    bindCurrentLotChange() {
        // Observer les changements DOM du lot actuel
        const currentLotObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' || mutation.type === 'attributes') {
                    // Remettre à jour l'affichage des devises après changement de lot
                    setTimeout(() => {
                        this.updateCurrencyDisplay(this.currentCurrency);
                    }, 100);
                }
            });
        });

        const currentLotContainer = document.querySelector('.detail-lot-live');
        if (currentLotContainer) {
            currentLotObserver.observe(currentLotContainer, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['data-dh', 'data-eur']
            });
        }
    }

    /**
     * Met à jour un lot spécifique (pour les mises à jour temps réel)
     */
    updateLotPricing(lotId, newPricing) {
        // Mettre à jour les prix d'un lot spécifique
        const lotElements = document.querySelectorAll(`[data-lot-id="${lotId}"]`);

        lotElements.forEach(element => {
            if (newPricing.enchere_dh) {
                element.dataset.dh = newPricing.enchere_dh;
            }
            if (newPricing.enchere_eur) {
                element.dataset.eur = newPricing.enchere_eur;
            }
        });

        // Rafraîchir l'affichage
        this.updateCurrencyDisplay(this.currentCurrency);
    }
}

/**
 * Fonction globale pour sélectionner une proposition (utilisée dans le Twig)
 */
function selectProposition(value) {
    const select = document.querySelector('#enchere_value, #enchere_value_admin');
    if (select) {
        select.value = value;
    }

    // Mise à jour visuelle
    const propositions = document.querySelectorAll('.item-propo');
    propositions.forEach(p => {
        p.classList.remove('selected');
        if (p.dataset.value == value) {
            p.classList.add('selected');
        }
    });
}

/**
 * Fonction d'initialisation automatique
 * Détecte le contexte (lot classique vs livestream) et initialise la bonne classe
 */
function initializeCurrencyManager() {
    const isLivestreamPage = document.querySelector('.body-live') !== null;
    const hasLotData = typeof window.lotData !== 'undefined';

    if (isLivestreamPage) {
        // Page livestream
        window.currencyManager = new LivestreamCurrencyManager(
            hasLotData ? window.lotData : null,
            window.userAuthenticated || false,
            window.isFavoris || false
        );
        console.log('Livestream Currency Manager initialized');
    } else if (hasLotData) {
        // Page lot classique
        window.currencyManager = new LotAuctionManager(
            window.lotData,
            window.userAuthenticated || false,
            window.isFavoris || false
        );
        console.log('Lot Auction Manager initialized');
    } else {
        // Fallback: initialiser quand même pour la conversion de base
        window.currencyManager = new LotAuctionManager(
            null,
            window.userAuthenticated || false,
            window.isFavoris || false
        );
        console.log('Basic Currency Manager initialized');
    }
}

// Initialisation automatique
document.addEventListener('DOMContentLoaded', function() {
    initializeCurrencyManager();
});

// Maintien de la compatibilité avec l'ancienne initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si les données globales existent (ancienne méthode)
    if (typeof window.lotData !== 'undefined' && !window.currencyManager) {
        const userAuthenticated = window.userAuthenticated || false;
        const isFavoris = window.isFavoris || false;

        // Créer une instance globale (compatibilité)
        window.lotAuctionManager = new LotAuctionManager(
            window.lotData,
            userAuthenticated,
            isFavoris
        );
    }
});

// Export pour utilisation en module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        LotAuctionManager,
        LivestreamCurrencyManager,
        selectProposition,
        initializeCurrencyManager
    };
}
