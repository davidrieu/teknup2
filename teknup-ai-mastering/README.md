# Teknup AI Mastering - WordPress Plugin

Plugin WordPress complet pour un service SaaS de mastering audio professionnel utilisant l'API Dolby.io Media Enhancement.

## 🎯 Description

Teknup AI Mastering permet aux producteurs de musique électronique d'obtenir un mastering de qualité studio en moins d'une minute grâce à l'intelligence artificielle de Dolby.io. Le plugin intègre complètement avec WooCommerce Subscriptions pour gérer les plans tarifaires et les limites d'utilisation.

## ✨ Fonctionnalités

### Pour les utilisateurs
- **Upload simple** : Interface drag-and-drop intuitive pour uploader des fichiers audio (WAV, MP3, FLAC, AIFF)
- **Mastering rapide** : Traitement en moins de 60 secondes via l'API Dolby.io
- **Contrôles avancés** : Ajustement de l'intensité, sélection du genre, contrôle du LUFS cible
- **Dashboard personnel** : Vue d'ensemble des jobs, statistiques d'utilisation, historique complet
- **Téléchargement facile** : URLs sécurisées avec expiration automatique
- **Notifications email** : Alertes automatiques lors de la complétion ou en cas d'erreur

### Pour les administrateurs
- **Dashboard complet** : Statistiques détaillées, graphiques d'utilisation
- **Gestion des jobs** : Vue sur tous les jobs avec filtres et actions en masse
- **Configuration facile** : Interface de réglages intuitive dans l'admin WordPress
- **Logs de debug** : Système de logging complet pour le troubleshooting
- **Monitoring** : Suivi en temps réel de l'utilisation et des performances

## 📋 Prérequis

- **WordPress** : 5.8 ou supérieur
- **PHP** : 8.0 ou supérieur
- **WooCommerce** : Dernière version
- **WooCommerce Subscriptions** : Pour la gestion des abonnements
- **Compte Dolby.io** : Clé API Media Enhancement
- **Node.js** : 16+ (pour le build des assets)

## 🚀 Installation

### Méthode 1 : Installation manuelle

1. **Télécharger le plugin**
   ```bash
   git clone https://github.com/votre-repo/teknup-ai-mastering.git
   cd teknup-ai-mastering
   ```

2. **Installer les dépendances NPM**
   ```bash
   npm install
   ```

3. **Builder les assets**
   ```bash
   npm run build
   ```

4. **Uploader sur WordPress**
   - Compresser le dossier en ZIP
   - Aller dans Plugins > Ajouter
   - Uploader et activer

### Méthode 2 : Via FTP

1. Builder les assets localement (voir étapes ci-dessus)
2. Uploader le dossier complet dans `/wp-content/plugins/`
3. Activer le plugin dans WordPress

## ⚙️ Configuration

### 1. Configuration de base

Après activation, aller dans **Teknup > Settings** :

1. **Dolby.io API Key**
   - Obtenir votre clé sur [dolby.io](https://dolby.io)
   - Coller la clé et cliquer sur "Test Connection"

2. **Paramètres avancés**
   - Taille maximum de fichier : 500 MB par défaut
   - Intensité par défaut : Medium
   - LUFS par défaut : -14.0
   - Jours de rétention : 30 jours
   - Mode debug : Activer pour le développement

### 2. Configuration WooCommerce

#### Produits d'abonnement (création automatique)

**Les produits sont créés automatiquement lors de l'activation du plugin !**

Le plugin crée automatiquement 4 produits WooCommerce Subscriptions :

1. **Teknup Free Trial** - 0€/mois
   - 3 masters par mois
   - Standard processing
   - Slug : `teknup-free_trial`

2. **Teknup Starter** - 19$/mois
   - 20 masters par mois
   - Contrôles avancés et presets
   - Slug : `teknup-starter`

3. **Teknup Pro** - 39$/mois
   - Masters illimités
   - File d'attente prioritaire
   - Slug : `teknup-pro`

4. **Teknup Label** - 99$/mois (tarification personnalisable)
   - Tout du Pro + API, white-label, support dédié
   - Slug : `teknup-label`

Tous les produits sont automatiquement configurés avec :
- Le meta `_teknup_plan_slug` correct
- Les descriptions et features
- La catégorie "Teknup AI Mastering"
- Les paramètres d'abonnement appropriés

**Note** : Si WooCommerce Subscriptions n'est pas installé lors de l'activation, les produits seront créés automatiquement quand vous l'installerez.

### 3. Configuration des pages

Le plugin ajoute automatiquement des sections dans WooCommerce My Account :
- Teknup Dashboard
- Upload Track
- Mastering History

#### Shortcodes disponibles

**Shortcode principal (recommandé)** :
- `[teknup_mastering]` - **Interface complète avec onglets** (Upload + Dashboard)
  - Affiche une interface avec tabs pour basculer entre l'upload et le dashboard
  - Design moderne avec le système d'onglets Teknup
  - **C'est le shortcode à utiliser sur votre page principale !**

**Shortcodes individuels** :
- `[teknup_upload]` - Page d'upload uniquement
- `[teknup_dashboard]` - Dashboard utilisateur uniquement
- `[teknup_history]` - Historique des jobs uniquement

**Exemple d'utilisation** :

Créez une page "Mon Studio" et ajoutez simplement :
```
[teknup_mastering]
```

Les utilisateurs pourront alors uploader leurs tracks et voir leur dashboard sur une seule page !

## 🎨 Personnalisation

### Design System

Le plugin utilise le design system Teknup avec :
- **Couleurs** : Rouge dominant (#DC143C), fond sombre (#0A0A0A)
- **Effets** : Glass morphism, ombres lumineuses
- **Typographie** : Majuscules avec letter-spacing

### Hooks disponibles

#### Actions
```php
// Après création d'un job
do_action( 'teknup_job_created', $job_id, $data );

// Après mise à jour du statut
do_action( 'teknup_job_status_updated', $job_id, $status, $data );

// Après complétion
do_action( 'teknup_job_completed', $job_id );

// Après échec
do_action( 'teknup_job_failed', $job_id );

// Après suppression
do_action( 'teknup_job_deleted', $job_id );

// Après réinitialisation du compteur mensuel
do_action( 'teknup_monthly_counter_reset', $user_id, $subscription );
```

#### Filtres
```php
// Modifier les paramètres de mastering
apply_filters( 'teknup_mastering_params', $params, $job );

// Modifier les limites de plan
apply_filters( 'teknup_plan_limits', $limits, $plan_slug );
```

### Personnaliser les templates

Copiez les templates dans votre thème :
```
votre-theme/
  ├── teknup/
  │   ├── upload.php
  │   ├── dashboard.php
  │   └── history.php
```

## 🔧 Développement

### Structure du projet

```
teknup-ai-mastering/
├── assets/
│   ├── src/
│   │   ├── css/
│   │   │   └── teknup-styles.css
│   │   └── js/
│   │       ├── components/
│   │       │   ├── Upload.jsx
│   │       │   ├── Dashboard.jsx
│   │       │   └── JobsList.jsx
│   │       ├── upload.jsx
│   │       └── dashboard.jsx
│   └── dist/ (fichiers buildés)
├── includes/
│   ├── Core/
│   │   ├── class-installer.php
│   │   ├── class-database.php
│   │   ├── class-storage.php
│   │   ├── class-jobs.php
│   │   ├── class-subscriptions.php
│   │   └── class-cron.php
│   ├── API/
│   │   ├── class-dolby.php
│   │   └── class-rest.php
│   ├── Admin/
│   │   ├── class-admin.php
│   │   ├── class-settings.php
│   │   └── class-dashboard.php
│   ├── Public/
│   │   ├── class-public-controller.php
│   │   ├── class-upload.php
│   │   └── class-account.php
│   ├── Emails/
│   │   └── class-notifications.php
│   ├── class-autoloader.php
│   └── class-teknup-ai-mastering.php
├── templates/
│   ├── admin/
│   ├── public/
│   └── emails/
├── package.json
├── webpack.config.js
├── teknup-ai-mastering.php
├── uninstall.php
└── README.md
```

### Commandes de développement

```bash
# Installer les dépendances
npm install

# Build pour la production
npm run build

# Mode watch pour le développement
npm run dev

# ou
npm run watch
```

### Debugging

Activer le mode debug dans les réglages pour :
- Logging détaillé de tous les événements
- Logs des appels API Dolby.io
- Webhooks avec payload complet
- Erreurs avec stack trace

Accéder aux logs : **Teknup > Logs** (visible uniquement en mode debug)

## 🔐 Sécurité

Le plugin implémente plusieurs mesures de sécurité :

- **Validation des fichiers** : Vérification du MIME type réel
- **Protection des uploads** : .htaccess pour bloquer l'accès direct
- **URLs temporaires** : Tokens avec expiration pour les téléchargements
- **Prepared statements** : Toutes les requêtes SQL sont sécurisées
- **Nonces WordPress** : Protection CSRF sur toutes les requêtes AJAX
- **Capabilities** : Vérification des permissions utilisateur
- **Sanitization** : Toutes les entrées utilisateur sont nettoyées

## 📊 API REST

Le plugin expose plusieurs endpoints REST :

### Endpoints utilisateur (authentifiés)

```
GET  /wp-json/teknup/v1/quota
GET  /wp-json/teknup/v1/jobs
GET  /wp-json/teknup/v1/jobs/{id}
POST /wp-json/teknup/v1/upload
DELETE /wp-json/teknup/v1/jobs/{id}
POST /wp-json/teknup/v1/jobs/{id}/retry
```

### Endpoints admin

```
GET /wp-json/teknup/v1/admin/stats
GET /wp-json/teknup/v1/admin/jobs
GET /wp-json/teknup/v1/admin/logs
```

### Endpoints publics

```
POST /wp-json/teknup/v1/dolby/callback (webhook Dolby.io)
```

## 🐛 Troubleshooting

### Le mastering ne démarre pas
1. Vérifier la clé API Dolby.io dans Settings
2. Tester la connexion API
3. Vérifier les logs en mode debug
4. S'assurer que l'abonnement est actif

### Les fichiers ne se téléchargent pas
1. Vérifier les permissions du dossier `/wp-content/teknup-uploads/`
2. S'assurer que .htaccess est présent
3. Vérifier que le fichier existe toujours (rétention de 30 jours)

### Les emails ne sont pas envoyés
1. Vérifier que les notifications sont activées dans Settings
2. Tester l'envoi d'email WordPress avec un plugin comme WP Mail SMTP
3. Vérifier les logs pour les erreurs d'email

### Erreur "Limit reached"
1. Vérifier le plan actif de l'utilisateur
2. Vérifier l'usage mensuel dans le dashboard
3. S'assurer que le compteur est réinitialisé au renouvellement

## 🔄 Mise à jour

1. Sauvegarder la base de données
2. Désactiver le plugin
3. Remplacer les fichiers
4. Réactiver le plugin
5. Vérifier les réglages

## 📝 Changelog

### Version 1.0.1
- **Nouveau** : Shortcode principal `[teknup_mastering]` avec système d'onglets
- **Nouveau** : Création automatique des produits WooCommerce à l'installation
- **Nouveau** : Composant React MasteringApp avec navigation par tabs
- **Amélioration** : Interface utilisateur unifiée sur une seule page
- **Amélioration** : Design des tabs avec effet glass morphism
- Les 4 produits d'abonnement sont maintenant créés automatiquement avec toutes leurs configurations

### Version 1.0.0
- Version initiale
- Upload et mastering via Dolby.io
- Intégration WooCommerce Subscriptions
- Dashboard utilisateur et admin
- Système de notifications email
- Gestion des quotas par plan
- Logs de debug
- Tâches cron automatiques

## 👨‍💻 Support

Pour toute question ou problème :
- Créer une issue sur GitHub
- Consulter la documentation : [docs.teknup.com](https://docs.teknup.com)
- Contacter le support : support@teknup.com

## 📄 Licence

GPL v2 or later

## 🙏 Crédits

- Dolby.io pour l'API Media Enhancement
- WooCommerce pour l'intégration e-commerce
- L'équipe WordPress pour le CMS

---

Développé avec ❤️ par Teknup
