# ☕ Café Maison — Site web complet v2

Torréfacteur artisanal parisien · Site e-commerce + blog + réservation d'ateliers

---

## 🚀 Installation rapide

1. **Copier** le dossier `cafe/` sur votre hébergeur (PHP 8.0+, Apache)
2. **Permissions** : `chmod 755 data/ && chmod 644 data/*.json`
3. **Accéder** : `votre-site.fr/admin/` → login : `admin` / `admin123`
4. **Changer le mot de passe** : Admin → Paramètres → Mot de passe admin
5. **Renommer** `htaccess` en `.htaccess` pour activer la configuration Apache

---

## ✨ Fonctionnalités

### 🏷 Catégories dynamiques
- Création / modification / suppression de catégories depuis l'admin
- Tri par ordre personnalisé, activation/désactivation
- Filtres dynamiques en boutique avec recherche texte
- Association produit ↔ catégorie

### 🛒 E-commerce
- Boutique avec filtres par catégorie
- Panier en temps réel (localStorage)
- **Stripe** + **PayPal** + **SumUp** (terminal sans contact)
- Codes promo (%, montant fixe, portée produit/atelier/tout)
- Prix HT + TTC avec TVA configurable par produit
- Mode livraison à domicile / retrait configurable par produit
- Carte cadeau pour les ateliers

### 🎓 Ateliers & Réservations
- Calendrier admin mensuel avec créneaux (date/heure/places)
- Réservation en ligne avec sélection de créneau
- Ajout direct au panier depuis les créneaux disponibles
- Liste d'attente automatique si complet
- Emails de confirmation automatiques

### 📧 Emails transactionnels
- Confirmation de commande avec **lien de modification 15 minutes**
- Email d'expédition avec numéro de suivi
- Confirmation de réservation atelier
- Demande d'avis J+7 après commande
- Réponse automatique formulaire contact
- Newsletter configurable
- Configuration SMTP (Gmail, Mailtrap, etc.)

### ⭐ Avis clients
- Note 1-5 étoiles avec badge "Achat vérifié"
- Modération avant publication dans l'admin
- Captcha reCAPTCHA v2 optionnel
- Email J+7 pour demander un avis

### 🖼 Upload d'images
- Drag & drop dans l'admin pour produits, ateliers, articles
- Stockage local sécurisé (`assets/uploads/`)
- PHP bloqué dans le dossier uploads
- Vérification MIME réelle côté serveur

### 🔍 Quiz guidés (multi-quiz)
- 5 questions, 4 profils résultats
- Entièrement configurable depuis l'admin
- Oriente vers la boutique sans mapper sur des produits spécifiques
- Widget discret sur la page boutique

### 🔎 Comparateur de cafés
- Comparer 2-3 cafés côte à côte
- Tableau: origine, torréfaction, prix HT/TTC, description

### 📊 Panel d'administration
- **Analytics** : CA/semaine et mois avec graphiques, top produits, avis, réservations
- **Fiches clients** : répertoire complet, recherche, filtres, export CSV, ajout manuel
- **Commandes** : sélection multiple, actions bulk, édition client, suppression
- **Calendrier** : gestion des créneaux, vue des réservations par session
- **Codes promo** : création, activation, compteur d'utilisations
- **Newsletter** : liste abonnés, composition et envoi, export CSV
- **Avis** : modération, approbation, réponse
- **Journal admin** : traçage de toutes les actions avec IP et URL
- **Quiz** : édition complète des questions et profils résultats
- **Pages** : modification de tous les textes du site, footer, SEO

### 🔒 Sécurité
- Authentification bcrypt cost=10 + rate limiting
- CSRF token avec rotation
- Session fingerprinting (IP + UA)
- Clés API chiffrées AES-256-GCM
- reCAPTCHA v2 optionnel sur login admin
- Headers HTTP de sécurité complets
- Journal de sécurité dans `data/.logs/`

### 🔍 SEO
- Meta description, keywords, Open Graph configurable
- Schema.org LocalBusiness automatique
- Sitemap XML dynamique : `api/sitemap.php`
- Robots.txt configuré
- Vérification Google Search Console

---

## 📁 Structure

```
cafe/
├── index.php                    ← Accueil (hero dynamique)
├── robots.txt                   ← Robots.txt SEO
├── htaccess                     ← À renommer en .htaccess
├── admin/                       ← Panel d'administration
│   ├── analytics.php            ← Dashboard analytics
│   ├── calendar.php             ← Calendrier ateliers
│   ├── clients.php              ← Fiches clients
│   ├── orders.php               ← Commandes (bulk actions)
│   ├── products.php             ← Produits + images
│   ├── workshops.php            ← Ateliers
│   ├── articles.php             ← Blog
│   ├── promos.php               ← Codes promo
│   ├── newsletter.php           ← Abonnés + envoi
│   ├── reviews.php              ← Avis clients
│   ├── quiz.php                 ← Éditeur du quiz
│   ├── log.php                  ← Journal admin
│   ├── pages.php                ← Modification des pages
│   ├── settings.php             ← Paramètres complets
│   └── test_order.php           ← Commande test
├── pages/
│   ├── shop.php                 ← Boutique
│   ├── atelier.php              ← Ateliers + créneaux
│   ├── booking.php              ← Réservation
│   ├── blog.php / article.php   ← Journal
│   ├── quiz.php                 ← Quiz café
│   ├── compare.php              ← Comparateur
│   ├── giftcard.php             ← Carte cadeau
│   ├── story.php                ← Notre Histoire
│   ├── modify_order.php         ← Modification commande client (15min)
│   ├── checkout.php             ← Commande + paiement
│   └── legal.php/cgv/shipping/faq/info
├── api/
│   ├── stripe_intent.php        ← Paiement Stripe
│   ├── paypal_capture.php       ← Paiement PayPal
│   ├── check_promo.php          ← Validation codes promo
│   ├── submit_review.php        ← Envoi avis
│   ├── newsletter_sub.php       ← Inscription newsletter
│   ├── send_review_emails.php   ← Emails J+7
│   └── sitemap.php              ← Sitemap SEO
├── includes/
│   ├── config.php               ← Config + sécurité + db
│   ├── header.php               ← En-tête + SEO
│   ├── footer.php               ← Pied de page dynamique
│   ├── mailer.php               ← Emails transactionnels
│   └── admin_log.php            ← Journal admin
└── data/                        ← Base de données JSON
    ├── products.json
    ├── workshops.json
    ├── orders.json
    ├── sessions.json            ← Créneaux ateliers
    ├── bookings.json            ← Réservations
    ├── clients.json             ← Clients manuels
    ├── reviews.json
    ├── newsletter.json
    ├── promos.json
    ├── admin_log.json           ← Journal admin
    └── settings.json            ← Configuration site
```

---

## ⚙️ Configuration paiements

### Stripe
Dashboard : [dashboard.stripe.com/apikeys](https://dashboard.stripe.com/apikeys)
- Admin → Paramètres → Clé publique (`pk_live_...`) + Clé secrète (`sk_live_...`)

### PayPal
Dashboard : [developer.paypal.com/dashboard](https://developer.paypal.com/dashboard/)
- Admin → Paramètres → PayPal Client ID

### SumUp
Dashboard : [developer.sumup.com](https://developer.sumup.com/)
- Admin → Paramètres → Clé API SumUp

---

## 📧 Configuration emails (SMTP)

Admin → Paramètres → Emails automatiques

**Gmail** : activer "Mot de passe d'application" dans votre compte Google
- Serveur : `smtp.gmail.com` · Port : `587`

**Mailtrap** (test) :
- Serveur : `smtp.mailtrap.io` · Port : `587`

---

## 🔐 Sécurité production

1. **HTTPS obligatoire** — Let's Encrypt (gratuit)
2. Renommer `htaccess` en `.htaccess`
3. Décommenter `session.cookie_secure` dans `config.php`
4. Changer le mot de passe admin (Admin → Paramètres)
5. `chmod 700 data/ && chmod 600 data/*.json data/.ek data/.admin_hash`
6. Supprimer `install.php` et les fichiers de debug

---

## 🔍 SEO

- Configurer dans Admin → Paramètres → SEO & Référencement
- Sitemap : `https://votre-domaine.fr/cafe/api/sitemap.php`
- Mettre à jour l'URL dans `robots.txt`
- Soumettre le sitemap dans Google Search Console

---

*Café Maison v2 — Construit avec PHP 8.0+, zéro dépendance externe*
