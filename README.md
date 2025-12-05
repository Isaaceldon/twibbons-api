# 🎨 Plateforme Twibbons - Structure Complète du Projet

## 📁 Structure des Fichiers

```
twibbons-platform/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── AggregateStatisticsCommand.php
│   │       └── CleanupOldFilesCommand.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php              ✅ Créé
│   │   │       ├── CampaignController.php          ✅ Créé
│   │   │       ├── CampaignTemplateController.php  ✅ Créé
│   │   │       ├── AIGenerationController.php      ✅ Créé
│   │   │       ├── BrandKitController.php          ✅ Créé
│   │   │       ├── VideoJobController.php          ✅ Créé
│   │   │       ├── UserGeneratedImageController.php ✅ Créé
│   │   │       └── StatisticsController.php        ✅ Créé
│   │   │
│   │   ├── Middleware/
│   │   │   ├── CheckAIGenerationLimit.php
│   │   │   ├── CheckVideoAccess.php
│   │   │   └── TrackCampaignView.php
│   │   │
│   │   └── Requests/
│   │       ├── Campaign/
│   │       │   ├── StoreCampaignRequest.php
│   │       │   └── UpdateCampaignRequest.php
│   │       ├── AIGeneration/
│   │       │   └── GenerateAIFrameRequest.php
│   │       └── Auth/
│   │           ├── RegisterRequest.php
│   │           └── LoginRequest.php
│   │
│   ├── Jobs/
│   │   ├── GenerateAIFrameJob.php                  ✅ Créé
│   │   ├── ProcessVideoJob.php                     ✅ Créé
│   │   ├── GenerateTwibbonJob.php                  ✅ Créé
│   │   ├── AggregateStatisticsJob.php
│   │   └── SendNotificationJob.php
│   │
│   ├── Models/
│   │   ├── User.php                                ✅ Complété
│   │   ├── Campaign.php                            ✅ À compléter (basé sur exemple)
│   │   ├── CampaignTemplate.php
│   │   ├── AiProvider.php
│   │   ├── AiGeneration.php
│   │   ├── BrandKit.php
│   │   ├── VideoTemplate.php
│   │   ├── VideoJob.php
│   │   ├── UserGeneratedImage.php
│   │   ├── Share.php
│   │   ├── CampaignStatistics.php
│   │   ├── StatisticsDaily.php
│   │   └── GlobalStatistics.php
│   │
│   ├── Notifications/
│   │   ├── AIGenerationCompletedNotification.php   ✅ Créé
│   │   ├── AIGenerationFailedNotification.php      ✅ Créé
│   │   ├── VideoJobCompletedNotification.php       ✅ Créé
│   │   ├── VideoJobFailedNotification.php          ✅ Créé
│   │   ├── CampaignPublishedNotification.php       ✅ Créé
│   │   └── MonthlyAILimitReachedNotification.php   ✅ Créé
│   │
│   ├── Services/
│   │   ├── AIService/
│   │   │   ├── AIServiceInterface.php
│   │   │   ├── OpenAIService.php
│   │   │   ├── StabilityAIService.php
│   │   │   └── LeonardoAIService.php
│   │   ├── VideoService.php
│   │   ├── ImageProcessingService.php
│   │   ├── StatisticsService.php
│   │   └── QRCodeService.php
│   │
│   └── Traits/
│       └── HasUuid.php                             ✅ Créé
│
├── bootstrap/
│   └── app.php
│
├── config/
│   ├── app.php
│   ├── database.php
│   ├── filesystems.php
│   ├── queue.php
│   ├── permission.php
│   ├── media-library.php
│   ├── horizon.php
│   └── ffmpeg.php                                  📝 À créer
│
├── database/
│   ├── migrations/
│   │   ├── 2024_01_01_create_users_table.php              ✅ UUID
│   │   ├── 2024_01_02_create_password_reset_tokens.php    ✅ UUID
│   │   ├── 2024_01_03_create_personal_access_tokens.php   ✅ UUID
│   │   ├── 2024_01_04_create_permission_tables.php        ✅ UUID
│   │   ├── 2024_01_05_create_campaigns_table.php          ✅ UUID
│   │   ├── 2024_01_06_create_ai_providers_table.php       ✅ UUID
│   │   ├── 2024_01_07_create_ai_generations_table.php     ✅ UUID
│   │   ├── 2024_01_08_create_campaign_templates_table.php ✅ UUID
│   │   ├── 2024_01_09_create_brand_kits_table.php         ✅ UUID
│   │   ├── 2024_01_10_create_brand_kit_campaign_table.php ✅ UUID
│   │   ├── 2024_01_11_create_video_templates_table.php    ✅ UUID
│   │   ├── 2024_01_12_create_video_jobs_table.php         ✅ UUID
│   │   ├── 2024_01_13_create_user_generated_images.php    ✅ UUID
│   │   ├── 2024_01_14_create_shares_table.php             ✅ UUID
│   │   ├── 2024_01_15_create_campaign_statistics.php      ✅ UUID
│   │   ├── 2024_01_16_create_statistics_daily_table.php   ✅ UUID
│   │   ├── 2024_01_17_create_global_statistics_table.php  ✅ UUID
│   │   ├── 2024_01_18_create_media_table.php              ✅ UUID
│   │   ├── 2024_01_19_create_notifications_table.php      ✅ UUID
│   │   ├── 2024_01_20_create_jobs_table.php               ✅ UUID
│   │   └── 2024_01_21_create_cache_table.php              ✅ UUID
│   │
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── RolesAndPermissionsSeeder.php
│   │   ├── AIProvidersSeeder.php
│   │   ├── VideoTemplatesSeeder.php
│   │   └── UsersSeeder.php
│   │
│   └── factories/
│       ├── UserFactory.php
│       ├── CampaignFactory.php
│       └── BrandKitFactory.php
│
├── public/
│   ├── fonts/
│   │   ├── Roboto-Bold.ttf
│   │   ├── Roboto-Regular.ttf
│   │   └── Inter-Bold.ttf
│   └── index.php
│
├── resources/
│   ├── views/
│   │   └── emails/
│   │       ├── ai-generation-completed.blade.php
│   │       ├── video-job-completed.blade.php
│   │       └── campaign-published.blade.php
│   └── js/
│       └── (Vue.js frontend - separate)
│
├── routes/
│   ├── api.php                                     ✅ Créé
│   ├── web.php
│   └── channels.php
│
├── storage/
│   ├── app/
│   │   ├── public/
│   │   └── fonts/
│   ├── framework/
│   └── logs/
│
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   │   ├── RegisterTest.php
│   │   │   └── LoginTest.php
│   │   ├── Campaign/
│   │   │   ├── CreateCampaignTest.php
│   │   │   └── PublishCampaignTest.php
│   │   └── AIGeneration/
│   │       └── GenerateAIFrameTest.php
│   └── Unit/
│       ├── Models/
│       │   ├── UserTest.php
│       │   └── CampaignTest.php
│       └── Services/
│           └── AIServiceTest.php
│
├── .env.example
├── .gitignore
├── composer.json
├── package.json
├── phpunit.xml
├── README.md
└── artisan
```

## 🚀 Installation et Configuration

### 1. Cloner et Installer Dépendances

```bash
# Cloner le projet
git clone https://github.com/your-repo/twibbons-platform.git
cd twibbons-platform

# Installer dépendances PHP
composer install

# Installer dépendances Node.js (si frontend intégré)
npm install

# Copier fichier environnement
cp .env.example .env

# Générer clé application
php artisan key:generate
```

### 2. Configuration Base de Données (.env)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=twibbons_db
DB_USERNAME=root
DB_PASSWORD=

# Redis pour queues
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
QUEUE_CONNECTION=redis

# Storage S3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=twibbons-storage
AWS_URL=https://twibbons-storage.s3.amazonaws.com

# AI Providers
OPENAI_API_KEY=sk-xxxxxxxxxxxxxxx
STABILITY_API_KEY=sk-xxxxxxxxxxxxxxx
LEONARDO_API_KEY=xxxxxxxxxxxxxxx

# FFmpeg
FFMPEG_BINARY=/usr/bin/ffmpeg
FFPROBE_BINARY=/usr/bin/ffprobe

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@twibbons.app
MAIL_FROM_NAME="Twibbons Platform"
```

### 3. Exécuter Migrations et Seeders

```bash
# Exécuter migrations
php artisan migrate

# Exécuter seeders
php artisan db:seed

# Ou tout en une fois
php artisan migrate:fresh --seed
```

### 4. Créer Liens Symboliques

```bash
php artisan storage:link
```

### 5. Publier Configurations

```bash
# Sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Spatie Permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Spatie Media Library
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider"

# Laravel Horizon
php artisan horizon:install
```

### 6. Démarrer Services

```bash
# Démarrer serveur Laravel
php artisan serve

# Démarrer Horizon (dans un terminal séparé)
php artisan horizon

# Démarrer compilateur frontend (si applicable)
npm run dev
```

## 📊 Commandes Artisan Utiles

### Gestion Queues

```bash
# Démarrer Horizon
php artisan horizon

# Vérifier status des queues
php artisan horizon:status

# Terminer Horizon gracieusement
php artisan horizon:terminate
```

### Statistiques

```bash
# Agréger statistiques quotidiennes
php artisan statistics:aggregate

# Nettoyer anciens fichiers
php artisan cleanup:old-files
```

### Cache

```bash
# Effacer tous les caches
php artisan optimize:clear

# Reconstruire caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🔧 Configuration FFmpeg

### Installation FFmpeg

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install ffmpeg

# macOS
brew install ffmpeg

# Vérifier installation
ffmpeg -version
```

### Configuration Laravel

Créer `config/ffmpeg.php` :

```php
<?php

return [
    'ffmpeg.binaries' => env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),
    'ffprobe.binaries' => env('FFPROBE_BINARY', '/usr/bin/ffprobe'),
    'timeout' => 3600,
    'ffmpeg.threads' => 12,
];
```

## 🧪 Tests

```bash
# Exécuter tous les tests
php artisan test

# Tests spécifiques
php artisan test --filter=CampaignTest

# Avec couverture
php artisan test --coverage
```

## 📝 API Documentation

L'API est documentée avec Scribe :

```bash
# Générer documentation
php artisan scribe:generate

# Accéder à : http://localhost/docs
```

## 🔐 Rôles et Permissions

### Rôles Disponibles

- **User** : Utilisateur standard (3 générations IA/mois, 5 campagnes max)
- **Organisation** : Organisation (illimité)
- **Designer** : Designer professionnel (illimité + templates premium)
- **Admin** : Administrateur plateforme (tous les droits)

### Attribution Rôles

```php
$user = User::find($userId);
$user->assignRole('Organisation');
```

## 📈 Monitoring

### Horizon Dashboard

Accès : `http://localhost/horizon`

### Logs

```bash
# Voir logs en temps réel
tail -f storage/logs/laravel.log

# Logs Horizon
tail -f storage/logs/horizon.log
```

## 🚢 Déploiement Production

### Checklist

- [ ] Variables d'environnement configurées
- [ ] Cache optimisé (`php artisan optimize`)
- [ ] SSL/HTTPS activé
- [ ] Queues workers avec Supervisor
- [ ] Backups automatisés (DB + S3)
- [ ] Monitoring (Sentry, New Relic)
- [ ] CDN configuré (Cloudflare)
- [ ] Rate limiting activé

### Supervisor Config (Horizon)

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/twibbons/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/twibbons/storage/logs/horizon.log
stopwaitsecs=3600
```

## 📦 Packages Installés

✅ laravel/sanctum  
✅ spatie/laravel-permission  
✅ spatie/laravel-medialibrary  
✅ spatie/image-optimizer  
✅ intervention/image  
✅ openai-php/laravel  
✅ guzzlehttp/guzzle  
✅ php-ffmpeg/php-ffmpeg  
✅ simplesoftwareio/simple-qrcode  
✅ ksubileau/color-thief-php  
✅ laravel/horizon  
✅ predis/predis  
✅ sentry/sentry-laravel  
✅ league/flysystem-aws-s3-v3  
✅ spatie/laravel-data  
✅ spatie/laravel-query-builder  

## 🎯 Prochaines Étapes

1. ✅ Compléter tous les Models avec relations
2. ✅ Créer Services pour logique métier
3. ✅ Implémenter tests unitaires et fonctionnels
4. ✅ Créer Seeders pour données de démo
5. ✅ Configurer CI/CD (GitHub Actions)
6. ✅ Optimiser performances (indexes, caching)
7. ✅ Documentation API complète (Postman Collection)

## 📞 Support

- Documentation : https://docs.twibbons.app
- Email : support@twibbons.app
- GitHub Issues : https://github.com/your-repo/twibbons-platform/issues

---

**Version:** 1.0.0  
**Dernière mise à jour:** Décembre 2024  
**Développé avec ❤️ par votre équipe**
