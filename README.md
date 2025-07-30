# Cmooa

Un site des véhicules pour différent type de client développé avec Drupal 10/11 et un thème personnalisé.



## 🛠️ Technologies

- **CMS** : Drupal 10/11
- **Thème** : Thème personnalisé "Cmooa Theme"
- **Icons** : Bootstrap Icons
- **PHP** : 8.1+
- **Base de données** : MySQL/MariaDB

## 📁 Structure du projet

```
/
├── web/                          # Racine web Drupal
│   ├── themes/custom/Cmooa_theme/  # Thème personnalisé
│   ├── modules/custom/             # Modules personnalisés
│   └── sites/default/             # Configuration site
├── vendor/                       # Dépendances Composer
├── composer.json                 # Configuration Composer
└── README.md                    # Ce fichier
```

## 🎨 Fonctionnalités du thème

Le thème Cmooa Theme inclut les régions suivantes :

- **header** : Barre supérieure
- **Top content** : haut contenu
- **Content** : Contenu principale
- **Bottom content** : Les sous contenus
- **Footer** : Pied de page

## 🚀 Installation

### Prérequis

- PHP 8.1 ou supérieur
- Composer
- MySQL/MariaDB
- Serveur web (Apache/Nginx)

### Étapes d'installation

1. **Cloner le repository**
   ```bash
   git clone https://github.com/votre-username/Cmooa.git
   cd Cmooa-drupal
   ```

2. **Installer les dépendances**
   ```bash
   composer install
   ```

3. **Configuration de la base de données**
   - Créer une base de données MySQL
   - Copier `web/sites/default/default.settings.php` vers `web/sites/default/settings.php`
   - Configurer les paramètres de connexion dans `settings.php`

4. **Installation Drupal**
   ```bash
   cd web
   ../vendor/bin/drush site:install --db-url=mysql://user:password@localhost/database_name
   ```

5. **Activer le thème**
   ```bash
   ../vendor/bin/drush theme:enable Cmooa
   ../vendor/bin/drush config:set system.theme default Cmooa
   ```

## 🔧 Configuration

### Permissions de fichiers
```bash
chmod 755 web/sites/default
chmod 644 web/sites/default/settings.php
chmod 755 web/sites/default/files
```

### Configuration recommandée

1. **Modules essentiels à activer** :
   - Views
   - Pathauto
   - Token

2. **Configuration du thème** :
   - Aller dans Apparence > Paramètres du thème
   - Configurer le logo et les couleurs

## 📝 Développement

### Structure du thème personnalisé
```
web/themes/custom/Cmooa_theme/
├── Cmooa_theme.info.yml          # Configuration du thème
├── Cmooa_theme.libraries.yml     # Bibliothèques CSS/JS
├── templates/                     # Templates Twig
├── css/                          # Fichiers CSS
├── js/                           # Fichiers JavaScript
├── images/                       # Images du thème
├── logo.svg                      # Logo
└── screenshot.png                # Capture d'écran du thème
```

### Commandes utiles

```bash
# Vider les caches
drush cache:rebuild

# Importer/Exporter la configuration
drush config:export
drush config:import

# Mettre à jour la base de données
drush updatedb

# Voir les logs
drush watchdog:show
```

## 🌐 Déploiement

### Variables d'environnement

Créer un fichier `web/sites/default/settings.local.php` pour la configuration locale :

```php
<?php
$databases['default']['default'] = [
  'database' => 'votre_db',
  'username' => 'votre_user',
  'password' => 'votre_password',
  'host' => 'localhost',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
];
```

### Production

1. Désactiver les modules de développement
2. Activer la mise en cache
3. Optimiser les images
4. Configurer HTTPS

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit les changements (`git commit -am 'Ajout nouvelle fonctionnalité'`)
4. Push sur la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

## 📄 Sécurité

- Ne jamais committer les fichiers `settings.php` ou `settings.local.php`
- Exclure le dossier `web/sites/default/files/`
- Maintenir Drupal et les modules à jour
- Utiliser des mots de passe forts

## 📞 Support

Pour toute question ou problème :

- Créer une issue sur GitHub
- Consulter la documentation Drupal
- Vérifier les logs d'erreur

## 📜 Licence

Ce projet est sous licence [indiquer la licence]. Voir le fichier `LICENSE` pour plus de détails.

---

**Version** : 1.0.0
**Dernière mise à jour** : Juillet 2025
