# FlexPHP — Documentation complète

> Framework PHP léger, async-capable, conforme aux standards PSR.

---

## Table des matières

1. [Introduction](#1-introduction)
2. [Prérequis & Installation](#2-prérequis--installation)
3. [Structure des répertoires](#3-structure-des-répertoires)
4. [Configuration](#4-configuration)
   - [Variables d'environnement (.env)](#variables-denvironnement-env)
   - [Fichiers de configuration](#fichiers-de-configuration)
   - [Accès à la configuration](#accès-à-la-configuration)
5. [Cycle de vie d'une requête](#5-cycle-de-vie-dune-requête)
6. [Routage](#6-routage)
   - [Routes simples](#routes-simples)
   - [Paramètres d'URL](#paramètres-durl)
   - [Routes nommées & génération d'URL](#routes-nommées--génération-durl)
   - [Groupes de routes](#groupes-de-routes)
   - [Routes API](#routes-api)
6b. [Annotations de routes (attributs PHP 8)](#6b-annotations-de-routes-attributs-php-8)
   - [Attributs disponibles](#attributs-disponibles)
   - [Prefix & Middleware de classe](#prefix--middleware-de-classe)
   - [Coexistence avec web.php](#coexistence-avec-webphp)
   - [Scanner d'autres répertoires](#scanner-dautres-répertoires)
7. [Contrôleurs](#7-contrôleurs)
   - [Créer un contrôleur](#créer-un-contrôleur)
   - [Contrôleur ressource](#contrôleur-ressource)
   - [Injection de dépendances](#injection-de-dépendances)
8. [Requêtes HTTP](#8-requêtes-http)
9. [Réponses HTTP](#9-réponses-http)
10. [Middleware](#10-middleware)
    - [Créer un middleware](#créer-un-middleware)
    - [Enregistrer un middleware](#enregistrer-un-middleware)
    - [Middleware CSRF intégré](#middleware-csrf-intégré)
11. [Vues & Templates](#11-vues--templates)
    - [Rendre une vue](#rendre-une-vue)
    - [Layouts & sections](#layouts--sections)
    - [Partiels](#partiels)
    - [Helper CSRF](#helper-csrf)
12. [Base de données & ORM](#12-base-de-données--orm)
    - [Configuration de la base](#configuration-de-la-base)
    - [Créer une entité (modèle)](#créer-une-entité-modèle)
    - [Migrations](#migrations)
    - [Repository de base](#repository-de-base)
    - [Repository personnalisé](#repository-personnalisé)
    - [Transactions](#transactions)
13. [Système d'événements](#13-système-dévénements)
    - [Créer un événement](#créer-un-événement)
    - [Écouter un événement](#écouter-un-événement)
    - [Abonnés (Subscribers)](#abonnés-subscribers)
    - [Arrêter la propagation](#arrêter-la-propagation)
14. [Logging](#14-logging)
15. [Conteneur d'injection de dépendances](#15-conteneur-dinjection-de-dépendances)
    - [Liaisons (Bindings)](#liaisons-bindings)
    - [Singletons](#singletons)
    - [Instances](#instances)
    - [Auto-wiring](#auto-wiring)
16. [Service Providers](#16-service-providers)
17. [Système Async](#17-système-async)
    - [Côté serveur](#côté-serveur)
    - [Côté client — flex.js](#côté-client--flexjs)
    - [Attributs HTML disponibles](#attributs-html-disponibles)
    - [Exemples concrets](#exemples-concrets)
18. [CLI — `php flex`](#18-cli--php-flex)
    - [Commandes intégrées](#commandes-intégrées)
    - [Créer une commande personnalisée](#créer-une-commande-personnalisée)
    - [Enregistrer une commande](#enregistrer-une-commande)
19. [Tests](#19-tests)
20. [Standards PSR respectés](#20-standards-psr-respectés)
21. [Référence rapide](#21-référence-rapide)

---

## 1. Introduction

**FlexPHP** est un framework web PHP conçu pour être à la fois léger et complet. Il suit strictement les standards PSR de la communauté PHP et introduit un système **async** original : sans changer votre code serveur, n'importe quelle partie de l'interface peut être mise à jour sans rechargement de page, simplement en ajoutant des attributs HTML.

**Points forts :**

- Routage rapide via `nikic/fast-route`
- Conteneur PSR-11 avec auto-wiring complet
- ORM via Cycle ORM 2.x (annotations PHP 8)
- Moteur de templates PHP natif (layouts, sections, partiels)
- Dispatcher d'événements PSR-14
- Logger PSR-3 avec rotation automatique
- Pipeline middleware PSR-15
- Système async déclaratif (2 Ko de JS, zéro dépendance)
- CLI artisan-style (`php flex <commande>`)
- PHP 8.1+ requis

---

## 2. Prérequis & Installation

**Prérequis :**

- PHP 8.1 ou supérieur
- Extensions : `pdo`, `pdo_mysql` (ou `pdo_pgsql` / `pdo_sqlite`), `json`, `mbstring`
- Composer

**Installation :**

```bash
# Cloner le projet
git clone <url-du-repo> mon-projet
cd mon-projet

# Installer les dépendances
composer install

# Copier le fichier d'environnement
cp .env.example .env

# Éditer .env avec vos paramètres (base de données, etc.)
```

**Démarrer le serveur de développement :**

```bash
php flex serve
# Le serveur écoute sur http://localhost:8000
```

**Configuration du serveur web (production) :**

Tous les appels doivent être redirigés vers `public/index.php`. Exemple pour Apache :

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/mon-projet/public
    DirectoryIndex index.php

    <Directory /var/www/mon-projet/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Exemple pour Nginx :

```nginx
server {
    listen 80;
    root /var/www/mon-projet/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

---

## 3. Structure des répertoires

```
mon-projet/
├── app/                        # Code applicatif (votre code)
│   ├── Controllers/            # Contrôleurs HTTP
│   ├── Models/                 # Entités Cycle ORM
│   ├── Middleware/             # Middleware PSR-15 personnalisés
│   └── Providers/              # Service providers applicatifs
├── bootstrap/
│   └── app.php                 # Bootstrapper (point d'entrée interne)
├── config/
│   ├── app.php                 # Paramètres principaux, providers, middleware
│   ├── database.php            # Configuration DBAL / ORM
│   ├── commands.php            # Enregistrement des commandes CLI
│   ├── logging.php             # Configuration du logger PSR-3
│   └── view.php                # Paramètres du moteur de templates
├── database/
│   ├── migrations/             # Fichiers de migration
│   └── seeders/                # Seeders de base de données
├── public/
│   ├── index.php               # Front controller (point d'entrée HTTP)
│   └── js/
│       └── flex.js             # Bibliothèque async (~2 Ko)
├── routes/
│   ├── web.php                 # Routes web (HTML, session)
│   └── api.php                 # Routes API (JSON, sans session)
├── src/                        # Cœur du framework (namespace FlexPHP\)
├── storage/
│   ├── cache/views/            # Cache des vues compilées
│   └── logs/                   # Fichiers de logs
├── tests/
│   ├── Unit/                   # Tests unitaires PHPUnit
│   └── Feature/                # Tests d'intégration
├── views/                      # Templates PHP
│   ├── layouts/
│   │   └── app.php             # Layout principal
│   └── errors/
│       ├── 404.php
│       └── 500.php
├── .env                        # Variables d'environnement (ne pas committer)
├── .env.example                # Template des variables d'environnement
├── composer.json
├── flex                        # Exécutable CLI
└── phpunit.xml
```

---

## 4. Configuration

### Variables d'environnement (.env)

Toutes les valeurs sensibles et spécifiques à l'environnement sont stockées dans `.env`. Ce fichier **ne doit jamais être commité** dans votre dépôt git.

```dotenv
# Application
APP_NAME=MonApplication
APP_ENV=local          # local | production | testing
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base de données
DB_DRIVER=mysql        # mysql | pgsql | sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ma_base
DB_USERNAME=root
DB_PASSWORD=secret

# Sessions
SESSION_LIFETIME=120

# Logs
LOG_CHANNEL=file
LOG_LEVEL=debug        # debug | info | warning | error | critical
```

Les variables sont accessibles via `$_ENV['APP_NAME']` ou la fonction `env()` si vous l'avez définie.

### Fichiers de configuration

Les fichiers dans `config/` retournent un tableau PHP :

```php
// config/app.php
return [
    'name'     => $_ENV['APP_NAME']  ?? 'FlexPHP',
    'env'      => $_ENV['APP_ENV']   ?? 'production',
    'debug'    => $_ENV['APP_DEBUG'] ?? false,
    'url'      => $_ENV['APP_URL']   ?? 'http://localhost',
    'timezone' => 'UTC',

    'providers' => [
        FlexPHP\View\ViewServiceProvider::class,
        FlexPHP\Database\DatabaseServiceProvider::class,
        FlexPHP\Events\EventServiceProvider::class,
        FlexPHP\Log\LogServiceProvider::class,
        // Vos providers applicatifs :
        App\Providers\AppServiceProvider::class,
    ],

    'middleware' => [
        FlexPHP\Http\Middleware\CsrfMiddleware::class,
        // Votre middleware global :
        App\Middleware\AuthMiddleware::class,
    ],
];
```

### Accès à la configuration

Dans n'importe quelle classe qui reçoit l'application par injection :

```php
// Notation pointée : fichier.clé.sous-clé
$name  = $config->get('app.name');
$host  = $config->get('database.connections.mysql.host');
$debug = $config->get('app.debug', false); // valeur par défaut
```

---

## 5. Cycle de vie d'une requête

Voici ce qui se passe à chaque appel HTTP :

```
public/index.php
    └── Application::bootstrap()
            ├── Chargement de .env
            ├── Chargement des fichiers config/
            ├── Enregistrement des bindings core (Container, Config, Router)
            ├── register() sur chaque ServiceProvider
            ├── boot() sur chaque ServiceProvider
            └── Chargement de routes/web.php

    └── Application::run()
            ├── Request::fromGlobals()        — crée la requête PSR-7
            ├── MiddlewareStack::handle()      — pipeline PSR-15
            │       ├── Middleware 1 (avant)
            │       ├── Middleware 2 (avant)
            │       ├── Router::dispatch()     — correspond à une route
            │       │       └── Controller@method($request, ...$vars)
            │       ├── Middleware 2 (après)
            │       └── Middleware 1 (après)
            └── Response::send()              — envoie headers + body
```

---

## 6. Routage

Les routes sont définies dans `routes/web.php`. La variable `$router` est automatiquement disponible dans ce fichier.

### Routes simples

```php
// routes/web.php
use App\Controllers\HomeController;
use App\Controllers\UserController;
use FlexPHP\Http\Request;
use FlexPHP\Http\Response;

// Méthodes HTTP disponibles
$router->get('/accueil', [HomeController::class, 'index']);
$router->post('/utilisateurs', [UserController::class, 'store']);
$router->put('/utilisateurs/{id}', [UserController::class, 'update']);
$router->patch('/utilisateurs/{id}', [UserController::class, 'patch']);
$router->delete('/utilisateurs/{id}', [UserController::class, 'destroy']);

// Toutes les méthodes HTTP
$router->any('/webhook', [WebhookController::class, 'handle']);

// Closure directement dans la route
$router->get('/ping', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});
```

### Paramètres d'URL

```php
// Paramètre simple : {nom}
$router->get('/articles/{slug}', [ArticleController::class, 'show']);

// Paramètre avec contrainte regex : {nom:regex}
$router->get('/utilisateurs/{id:\d+}', [UserController::class, 'show']);

// Plusieurs paramètres
$router->get('/categories/{category}/{post:\d+}', [PostController::class, 'show']);
```

Les paramètres sont passés en arguments à la méthode du contrôleur, dans l'ordre d'apparition :

```php
public function show(Request $request, string $category, string $post): Response
{
    // $category = valeur de {category}
    // $post = valeur de {post}
}
```

### Routes nommées & génération d'URL

```php
// Déclarer une route nommée (3e paramètre)
$router->get('/utilisateurs/{id:\d+}', [UserController::class, 'show'], 'users.show');
$router->get('/articles/{slug}', [ArticleController::class, 'show'], 'articles.show');
```

Générer une URL depuis un contrôleur ou une vue :

```php
// Injection du Router
public function __construct(private Router $router) {}

public function index(Request $request): Response
{
    $url = $this->router->url('users.show', ['id' => 42]);
    // => '/utilisateurs/42'

    $url = $this->router->url('articles.show', ['slug' => 'mon-article', 'ref' => 'newsletter']);
    // => '/articles/mon-article?ref=newsletter'
}
```

### Groupes de routes

Les groupes permettent de partager un préfixe URI et/ou des middleware :

```php
// Groupe avec préfixe uniquement
$router->group('/admin', function (Router $r) {
    $r->get('/tableau-de-bord', [AdminController::class, 'dashboard']);
    $r->get('/utilisateurs', [AdminController::class, 'users']);
});

// Groupe avec préfixe + middleware
$router->group('/admin', function (Router $r) {
    $r->get('/tableau-de-bord', [AdminController::class, 'dashboard']);
}, [AuthMiddleware::class, AdminMiddleware::class]);

// Groupes imbriqués (les préfixes s'accumulent)
$router->group('/api', function (Router $r) {
    $r->group('/v1', function (Router $r) {
        $r->get('/articles', [ArticleController::class, 'index']);
        // URI finale : /api/v1/articles
    });
});
```

### Routes API

Le fichier `routes/api.php` est prévu pour les routes JSON (sans session, sans CSRF). Vous pouvez le charger manuellement dans votre bootstrap si besoin, ou l'intégrer dans `routes/web.php` sous un groupe `/api`.

---

---

## 6b. Annotations de routes (attributs PHP 8)

En plus des routes déclarées dans `routes/web.php`, FlexPHP permet de définir les routes **directement sur les méthodes des contrôleurs** via des attributs PHP 8. Le `RouteScanner` parcourt automatiquement `app/Controllers/` au démarrage.

### Attributs disponibles

| Attribut | HTTP | Cible |
|---|---|---|
| `#[Get($path, name?, middleware?)]` | GET, HEAD | Méthode |
| `#[Post($path, name?, middleware?)]` | POST | Méthode |
| `#[Put($path, name?, middleware?)]` | PUT | Méthode |
| `#[Patch($path, name?, middleware?)]` | PATCH | Méthode |
| `#[Delete($path, name?, middleware?)]` | DELETE | Méthode |
| `#[Any($path, name?, middleware?)]` | Toutes | Méthode |
| `#[Route($path, methods: [...], name?, middleware?)]` | Configurable | Méthode |
| `#[Prefix($path)]` | — | Classe |
| `#[Middleware(Foo::class, ...)]` | — | Classe ou méthode |

### Exemple complet

```php
<?php
declare(strict_types=1);
namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use FlexPHP\Routing\Attributes\Delete;
use FlexPHP\Routing\Attributes\Get;
use FlexPHP\Routing\Attributes\Middleware;
use FlexPHP\Routing\Attributes\Post;
use FlexPHP\Routing\Attributes\Prefix;
use FlexPHP\Routing\Attributes\Put;

#[Prefix('/articles')]
class ArticleController extends BaseController
{
    #[Get('/', name: 'articles.index')]
    public function index(Request $request): Response
    {
        return $this->view('articles/index');
    }

    #[Get('/{id:\d+}', name: 'articles.show')]
    public function show(Request $request, string $id): Response
    {
        return $this->view('articles/show', ['id' => $id]);
    }

    #[Post('/', name: 'articles.store')]
    public function store(Request $request): Response
    {
        // ... créer l'article
        return $this->redirect('/articles');
    }

    #[Put('/{id:\d+}', name: 'articles.update')]
    public function update(Request $request, string $id): Response
    {
        return $this->redirect('/articles/' . $id);
    }

    #[Delete('/{id:\d+}', name: 'articles.destroy')]
    public function destroy(Request $request, string $id): Response
    {
        return $this->redirect('/articles');
    }
}
```

### #[Route] — méthodes multiples sur une action

```php
use FlexPHP\Routing\Attributes\Route;

#[Route('/profil', methods: ['GET', 'HEAD'], name: 'profil.show')]
#[Route('/profil', methods: ['POST'],        name: 'profil.update')]
public function profil(Request $request): Response
{
    if ($request->isPost()) { /* traitement formulaire */ }
    return $this->view('profil');
}
```

### Prefix & Middleware de classe

```php
// Le préfixe s'ajoute devant toutes les routes du contrôleur.
// Le middleware de classe s'applique à toutes ses routes.
#[Prefix('/admin')]
#[Middleware(AuthMiddleware::class, AdminMiddleware::class)]
class AdminController extends BaseController
{
    #[Get('/dashboard', name: 'admin.dashboard')]
    public function dashboard(Request $request): Response { ... }
    // → GET /admin/dashboard + AuthMiddleware + AdminMiddleware

    // Middleware supplémentaire sur une méthode spécifique
    #[Get('/secret', name: 'admin.secret')]
    #[Middleware(SuperAdminMiddleware::class)]
    public function secret(Request $request): Response { ... }
    // → AuthMiddleware + AdminMiddleware + SuperAdminMiddleware
}
```

### Coexistence avec web.php

Les deux approches fonctionnent ensemble. `routes/web.php` est chargé en premier, les attributs ensuite. En cas de conflit de nom de route, la première déclaration gagne.

```php
// routes/web.php — toujours valide
$router->get('/page-statique', fn($r) => Response::html('<h1>Page</h1>'));

// app/Controllers/ArticleController.php — coexiste librement
#[Get('/articles', name: 'articles.index')]
public function index(Request $request): Response { ... }
```

### Scanner d'autres répertoires

Par défaut, `app/Controllers/` est scanné. Pour ajouter des sources :

```php
// config/app.php
'route_scan_paths' => [
    ['dir' => __DIR__ . '/../app/Controllers', 'namespace' => 'App\\Controllers'],
    ['dir' => __DIR__ . '/../modules/Blog',    'namespace' => 'Modules\\Blog'],
],
```

---

## 7. Contrôleurs

### Créer un contrôleur

```bash
php flex make:controller ArticleController
```

Cela génère `app/Controllers/ArticleController.php` :

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;

class ArticleController
{
    public function index(Request $request): Response
    {
        return Response::html('<h1>Liste des articles</h1>');
    }
}
```

Un contrôleur est une simple classe PHP. La méthode reçoit toujours un `Request` en premier argument, suivi des éventuels paramètres de route, et doit retourner une `Response`.

### Contrôleur ressource

```bash
php flex make:controller ArticleController --resource
```

Génère un contrôleur avec les 7 méthodes CRUD standards :

| Méthode    | URI                    | Action  |
|------------|------------------------|---------|
| `index`    | GET /articles          | Lister  |
| `create`   | GET /articles/create   | Formulaire création |
| `store`    | POST /articles         | Créer   |
| `show`     | GET /articles/{id}     | Afficher |
| `edit`     | GET /articles/{id}/edit | Formulaire édition |
| `update`   | PUT /articles/{id}     | Modifier |
| `destroy`  | DELETE /articles/{id}  | Supprimer |

Déclarez ces routes dans `routes/web.php` :

```php
$router->get('/articles',             [ArticleController::class, 'index'],   'articles.index');
$router->get('/articles/create',      [ArticleController::class, 'create'],  'articles.create');
$router->post('/articles',            [ArticleController::class, 'store'],   'articles.store');
$router->get('/articles/{id:\d+}',    [ArticleController::class, 'show'],    'articles.show');
$router->get('/articles/{id:\d+}/edit',[ArticleController::class, 'edit'],   'articles.edit');
$router->put('/articles/{id:\d+}',    [ArticleController::class, 'update'],  'articles.update');
$router->delete('/articles/{id:\d+}', [ArticleController::class, 'destroy'], 'articles.destroy');
```

### Injection de dépendances

Le conteneur résout automatiquement les dépendances déclarées dans le constructeur :

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ArticleRepository;
use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use FlexPHP\View\ViewEngine;

class ArticleController
{
    public function __construct(
        private ArticleRepository $articles,
        private ViewEngine $view,
    ) {}

    public function index(Request $request): Response
    {
        $articles = $this->articles->findAll();

        return Response::html(
            $this->view->render('articles/index', ['articles' => $articles])
        );
    }

    public function show(Request $request, string $id): Response
    {
        $article = $this->articles->findById((int) $id);

        if ($article === null) {
            return Response::notFound('Article introuvable');
        }

        return Response::html(
            $this->view->render('articles/show', ['article' => $article])
        );
    }

    public function store(Request $request): Response
    {
        $data = $request->input(); // tous les champs POST

        $article = $this->articles->create($data);

        return Response::redirect('/articles/' . $article->id);
    }
}
```

---

## 8. Requêtes HTTP

La classe `FlexPHP\Http\Request` encapsule la requête courante (PSR-7).

```php
// Méthode HTTP
$request->getMethod();         // 'GET', 'POST', etc.
$request->isMethod('POST');    // bool

// URI & chemin
$request->getUri();            // URI complète
$request->getPath();           // '/articles/42'

// Paramètres GET (query string)
$request->query('page');           // valeur ou null
$request->query('page', 1);        // valeur ou défaut
$request->queryAll();              // tableau complet

// Paramètres POST (corps de la requête)
$request->input('titre');          // valeur ou null
$request->input('titre', '');      // valeur ou défaut
$request->input();                 // tous les champs POST

// Corps brut
$request->getBody();               // string brut

// Corps JSON (API)
$request->json('clé');             // valeur parsée depuis JSON
$request->json();                  // tableau complet

// En-têtes
$request->header('Accept');        // valeur ou null
$request->hasHeader('Authorization'); // bool

// Cookies
$request->cookie('session_id');    // valeur ou null

// Fichiers uploadés
$request->file('avatar');          // $_FILES['avatar'] ou null
$request->hasFile('avatar');       // bool

// Détection du type de requête
$request->isAsyncRequest();        // true si X-Flex-Async: true
$request->expectsJson();           // true si Accept: application/json
$request->isSecure();              // true si HTTPS
$request->ip();                    // IP du client
```

---

## 9. Réponses HTTP

```php
use FlexPHP\Http\Response;
use FlexPHP\Http\JsonResponse;
use FlexPHP\Http\RedirectResponse;

// Réponse HTML
return Response::html('<h1>Bonjour</h1>');
return Response::html('<h1>Non trouvé</h1>', 404);

// Réponse JSON
return Response::json(['clé' => 'valeur']);
return Response::json(['erreur' => 'Non autorisé'], 401);

// Réponse JSON avec headers personnalisés
return new JsonResponse(
    ['data' => $articles],
    200,
    ['X-Total-Count' => '42']
);

// Redirection
return Response::redirect('/accueil');
return Response::redirect('/login', 302);

// Réponse 404
return Response::notFound();
return Response::notFound('Page introuvable');

// Réponse personnalisée
return new Response('Corps de la réponse', 200, [
    'Content-Type' => 'text/plain',
    'X-Custom'     => 'valeur',
]);

// API fluide
return (new Response())
    ->withStatus(201)
    ->withHeader('Location', '/articles/42')
    ->withBody('Créé');
```

---

## 10. Middleware

Un middleware intercepte la requête avant et/ou après qu'elle atteigne le contrôleur.

### Créer un middleware

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Logique AVANT le contrôleur
        if (!isset($_SESSION['user_id'])) {
            return Response::redirect('/login');
        }

        // Passer au middleware suivant (ou au contrôleur)
        $response = $handler->handle($request);

        // Logique APRÈS le contrôleur (optionnel)
        return $response->withHeader('X-Authenticated', 'true');
    }
}
```

### Enregistrer un middleware

**Global** (s'applique à toutes les routes) — dans `config/app.php` :

```php
'middleware' => [
    FlexPHP\Http\Middleware\CsrfMiddleware::class,
    App\Middleware\LogRequestMiddleware::class,
],
```

**Par groupe de routes** :

```php
$router->group('/admin', function (Router $r) {
    $r->get('/dashboard', [AdminController::class, 'dashboard']);
}, [App\Middleware\AuthMiddleware::class]);
```

**Par route individuelle** :

```php
$router->get(
    '/profil',
    [ProfileController::class, 'show'],
    'profile.show',
    [App\Middleware\AuthMiddleware::class]
);
```

### Middleware CSRF intégré

`FlexPHP\Http\Middleware\CsrfMiddleware` est activé par défaut. Il vérifie la présence d'un token valide sur toutes les requêtes `POST`, `PUT`, `PATCH`, `DELETE`.

Le token se transmet soit par le champ de formulaire `_token`, soit par l'en-tête `X-CSRF-Token`.

Dans vos formulaires, utilisez le helper de vue :

```php
<form method="POST" action="/articles">
    <?= $this->csrf() ?>
    <!-- génère : <input type="hidden" name="_token" value="..."> -->
    <button type="submit">Envoyer</button>
</form>
```

---

## 11. Vues & Templates

FlexPHP utilise un moteur de templates PHP natif avec héritage de layouts et sections.

### Rendre une vue

```php
// Dans un contrôleur
public function __construct(private ViewEngine $view) {}

public function index(Request $request): Response
{
    $html = $this->view->render('articles/index', [
        'titre'    => 'Mes articles',
        'articles' => $this->articles->findAll(),
    ]);

    return Response::html($html);
}
```

Le nom `'articles/index'` correspond au fichier `views/articles/index.php`.

### Layouts & sections

**Définir un layout** (`views/layouts/app.php`) :

```php
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $this->section('title', 'FlexPHP App') ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <nav>
        <a href="/">Accueil</a>
        <a href="/articles">Articles</a>
    </nav>

    <main>
        <?= $this->section('content') ?>
    </main>

    <footer>
        <p>Mon application FlexPHP</p>
    </footer>

    <script src="/js/flex.js"></script>
    <?= $this->section('scripts') ?>
</body>
</html>
```

**Utiliser le layout dans une vue** (`views/articles/index.php`) :

```php
<?php $this->layout('layouts/app') ?>

<?php $this->start('title') ?>
    Liste des articles
<?php $this->end() ?>

<?php $this->start('content') ?>
    <h1>Articles</h1>

    <?php if (empty($articles)): ?>
        <p>Aucun article pour l'instant.</p>
    <?php else: ?>
        <ul>
        <?php foreach ($articles as $article): ?>
            <li>
                <a href="/articles/<?= htmlspecialchars((string) $article->id) ?>">
                    <?= htmlspecialchars($article->titre) ?>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php $this->end() ?>

<?php $this->start('scripts') ?>
    <script>console.log('Page articles chargée');</script>
<?php $this->end() ?>
```

### Partiels

Inclure un sous-template depuis une vue :

```php
<?= $this->partial('components/alert', ['type' => 'success', 'message' => 'Sauvegardé !']) ?>
```

Fichier `views/components/alert.php` :

```php
<div class="alert alert-<?= htmlspecialchars($type) ?>">
    <?= htmlspecialchars($message) ?>
</div>
```

### Helper CSRF

```php
// Dans un template
<form method="POST" action="/articles">
    <?= $this->csrf() ?>
    <input type="text" name="titre" placeholder="Titre">
    <button type="submit">Créer</button>
</form>
```

---

## 12. Base de données & ORM

FlexPHP utilise **Cycle ORM 2.x** pour la couche de persistance.

### Configuration de la base

`config/database.php` :

```php
return [
    'default' => $_ENV['DB_DRIVER'] ?? 'mysql',

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
            'port'     => $_ENV['DB_PORT']     ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'flexphp',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset'  => 'utf8mb4',
        ],
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => __DIR__ . '/../database/database.sqlite',
        ],
        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
            'port'     => $_ENV['DB_PORT']     ?? 5432,
            'database' => $_ENV['DB_DATABASE'] ?? 'flexphp',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ],
    ],

    // Répertoires où Cycle ORM scanne les entités
    'entity_directories' => [
        __DIR__ . '/../app/Models',
    ],
];
```

### Créer une entité (modèle)

```bash
php flex make:model Article
```

Génère `app/Models/Article.php`. Complétez-le :

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Column;

#[Entity(table: 'articles')]
class Article
{
    #[Column(type: 'primary')]
    public int $id;

    #[Column(type: 'string', length: 255)]
    public string $titre;

    #[Column(type: 'text')]
    public string $contenu;

    #[Column(type: 'string', nullable: true)]
    public ?string $slug = null;

    #[Column(type: 'boolean', default: false)]
    public bool $publie = false;

    #[Column(type: 'datetime')]
    public \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime', nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;
}
```

### Migrations

```bash
# Générer un fichier de migration vide
php flex make:migration create_articles_table

# Appliquer les migrations en attente
php flex migrate

# Annuler le dernier batch
php flex migrate --rollback

# Réinitialiser complètement et réappliquer
php flex migrate --fresh
```

Structure d'une migration (`database/migrations/YYYYMMDD_HHMMSS_create_articles_table.php`) :

```php
<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;

return new class {
    public function up(DatabaseInterface $db): void
    {
        $schema = $db->table('articles')->getSchema();

        $schema->primary('id');
        $schema->string('titre', 255);
        $schema->text('contenu');
        $schema->string('slug', 255)->nullable();
        $schema->boolean('publie')->defaultValue(false);
        $schema->datetime('created_at');
        $schema->datetime('updated_at')->nullable();

        $schema->save();
    }

    public function down(DatabaseInterface $db): void
    {
        $db->table('articles')->getSchema()->declareDropped()->save();
    }
};
```

### Repository de base

`BaseRepository` fournit les opérations CRUD courantes. Créez un repository en l'étendant :

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Article;
use FlexPHP\Database\BaseRepository;

class ArticleRepository extends BaseRepository
{
    protected string $entityClass = Article::class;
}
```

Méthodes disponibles :

```php
$repo = new ArticleRepository($databaseManager);

// Trouver par ID
$article = $repo->findById(42);          // Article|null

// Tous les enregistrements
$articles = $repo->findAll();            // Article[]

// Filtrer
$articles = $repo->findBy(['publie' => true]);

// Pagination
$page = $repo->paginate(page: 2, perPage: 15);
// retourne ['data' => [...], 'total' => 100, 'page' => 2, 'perPage' => 15]

// Créer / modifier
$article = new Article();
$article->titre = 'Mon article';
$article->contenu = 'Contenu...';
$article->createdAt = new \DateTimeImmutable();
$repo->save($article);

// Supprimer
$repo->delete($article);
```

### Repository personnalisé

Ajoutez vos propres méthodes de requête :

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Article;
use FlexPHP\Database\BaseRepository;

class ArticleRepository extends BaseRepository
{
    protected string $entityClass = Article::class;

    /** @return Article[] */
    public function findPublished(): array
    {
        return $this->orm
            ->getRepository(Article::class)
            ->select()
            ->where('publie', true)
            ->orderBy('created_at', 'DESC')
            ->fetchAll();
    }

    public function findBySlug(string $slug): ?Article
    {
        return $this->orm
            ->getRepository(Article::class)
            ->select()
            ->where('slug', $slug)
            ->fetchOne();
    }
}
```

### Transactions

```php
$db = $databaseManager->getDatabase();

$db->transaction(function () use ($db, $articleRepo, $tagRepo) {
    $article = new Article();
    $article->titre = 'Article avec tags';
    $articleRepo->save($article);

    $tag = new Tag();
    $tag->nom = 'PHP';
    $tagRepo->save($tag);
    // Si une exception est levée, tout est annulé automatiquement
});
```

---

## 13. Système d'événements

FlexPHP implémente le dispatcher d'événements PSR-14.

### Créer un événement

Un événement est une simple classe PHP :

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Article;

class ArticlePublié
{
    public function __construct(
        public readonly Article $article,
        public readonly \DateTimeImmutable $publishedAt = new \DateTimeImmutable(),
    ) {}
}
```

### Écouter un événement

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticlePublié;

class EnvoyerNotification
{
    public function __invoke(ArticlePublié $event): void
    {
        // Envoyer un email, une notification push, etc.
        mail(
            'admin@exemple.fr',
            'Nouvel article publié',
            'L\'article "' . $event->article->titre . '" vient d\'être publié.'
        );
    }
}
```

Enregistrez l'écouteur dans un Service Provider :

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    $dispatcher = $this->app->make(EventDispatcher::class);

    $dispatcher->addListener(
        ArticlePublié::class,
        new EnvoyerNotification()
    );
}
```

Déclencher l'événement depuis un contrôleur :

```php
public function __construct(
    private ArticleRepository $articles,
    private EventDispatcher $events,
) {}

public function publish(Request $request, string $id): Response
{
    $article = $this->articles->findById((int) $id);
    $article->publie = true;
    $this->articles->save($article);

    $this->events->dispatch(new ArticlePublié($article));

    return Response::redirect('/articles/' . $id);
}
```

### Abonnés (Subscribers)

Un abonné regroupe plusieurs écouteurs pour un même domaine :

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticleCréé;
use App\Events\ArticlePublié;
use App\Events\ArticleSupprimé;
use FlexPHP\Events\EventDispatcher;

class ArticleSubscriber
{
    public function subscribe(EventDispatcher $dispatcher): void
    {
        $dispatcher->addListener(ArticleCréé::class, [$this, 'onCréé']);
        $dispatcher->addListener(ArticlePublié::class, [$this, 'onPublié']);
        $dispatcher->addListener(ArticleSupprimé::class, [$this, 'onSupprimé']);
    }

    public function onCréé(ArticleCréé $event): void { /* ... */ }
    public function onPublié(ArticlePublié $event): void { /* ... */ }
    public function onSupprimé(ArticleSupprimé $event): void { /* ... */ }
}
```

```php
// Dans un Service Provider
$subscriber = new ArticleSubscriber();
$subscriber->subscribe($dispatcher);
```

### Arrêter la propagation

```php
use Psr\EventDispatcher\StoppableEventInterface;

class ArticlePublié implements StoppableEventInterface
{
    private bool $stopped = false;

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
    // ...
}
```

---

## 14. Logging

FlexPHP fournit un logger PSR-3 accessible via injection de dépendances.

```php
use Psr\Log\LoggerInterface;

class ArticleController
{
    public function __construct(private LoggerInterface $logger) {}

    public function store(Request $request): Response
    {
        try {
            // ... création article
            $this->logger->info('Article créé', ['id' => $article->id]);
            return Response::redirect('/articles');
        } catch (\Throwable $e) {
            $this->logger->error('Échec création article', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return Response::html('Erreur serveur', 500);
        }
    }
}
```

**Niveaux disponibles** (ordre croissant de sévérité) :

```php
$logger->debug('Message de debug détaillé');
$logger->info('Information générale');
$logger->notice('Événement notable mais normal');
$logger->warning('Avertissement — quelque chose d\'inattendu');
$logger->error('Erreur — une fonctionnalité ne fonctionne pas');
$logger->critical('Condition critique');
$logger->alert('Action immédiate requise');
$logger->emergency('Système inutilisable');
```

Les logs sont écrits dans `storage/logs/`. Le fichier est automatiquement rotaté au-delà de 10 Mo.

**Configuration** (`config/logging.php`) :

```php
return [
    'channel'  => $_ENV['LOG_CHANNEL'] ?? 'file',
    'level'    => $_ENV['LOG_LEVEL']   ?? 'debug',
    'path'     => __DIR__ . '/../storage/logs/app.log',
];
```

---

## 15. Conteneur d'injection de dépendances

Le conteneur PSR-11 gère toutes les dépendances du framework et de votre application.

### Liaisons (Bindings)

Lier une interface à une implémentation concrète :

```php
// Dans un Service Provider
$this->app->bind(
    MailerInterface::class,
    SmtpMailer::class
);

// Avec une factory closure
$this->app->bind(PaymentGateway::class, function () {
    return new StripeGateway(
        apiKey: $_ENV['STRIPE_SECRET'],
        webhook: $_ENV['STRIPE_WEBHOOK']
    );
});
```

### Singletons

Une seule instance partagée pour toute l'application :

```php
$this->app->singleton(CacheInterface::class, RedisCache::class);

$this->app->singleton(DatabaseConnection::class, function () {
    return new DatabaseConnection($_ENV['DATABASE_URL']);
});
```

### Instances

Enregistrer une instance déjà créée :

```php
$config = new MyConfig(['debug' => true]);
$this->app->getContainer()->instance(MyConfig::class, $config);
```

### Auto-wiring

Le conteneur résout automatiquement les dépendances d'une classe en inspectant son constructeur via Reflection. Vous n'avez pas à déclarer explicitement les classes concrètes :

```php
class ArticleController
{
    // Ces dépendances sont résolues automatiquement
    public function __construct(
        private ArticleRepository $articles,  // résolu automatiquement
        private ViewEngine $view,             // résolu automatiquement
        private LoggerInterface $logger,      // résolu via binding explicite
    ) {}
}
```

Résoudre manuellement depuis le conteneur :

```php
$controller = $app->make(ArticleController::class);

// Avec des paramètres supplémentaires
$service = $app->make(ReportService::class, ['format' => 'pdf']);
```

---

## 16. Service Providers

Les Service Providers sont le mécanisme central pour enregistrer des services, des liaisons, et exécuter du code au démarrage.

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use FlexPHP\Core\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Enregistrer les liaisons dans le conteneur.
     * Appelé avant boot() sur tous les providers.
     */
    public function register(): void
    {
        $this->app->bind(MailerInterface::class, SmtpMailer::class);

        $this->app->singleton(CacheInterface::class, function () {
            return new FileCache(storage_path('cache'));
        });
    }

    /**
     * Exécuté après que tous les providers ont été enregistrés.
     * Idéal pour les actions qui dépendent d'autres services.
     */
    public function boot(): void
    {
        $dispatcher = $this->app->make(EventDispatcher::class);
        $dispatcher->addListener(UserRegistered::class, new SendWelcomeEmail());
    }
}
```

Enregistrez votre provider dans `config/app.php` :

```php
'providers' => [
    // Providers du framework (ne pas retirer)
    FlexPHP\View\ViewServiceProvider::class,
    FlexPHP\Database\DatabaseServiceProvider::class,
    FlexPHP\Events\EventServiceProvider::class,
    FlexPHP\Log\LogServiceProvider::class,

    // Vos providers applicatifs
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
],
```

**Ordre d'exécution :**
1. `register()` est appelé sur **tous** les providers dans l'ordre de déclaration
2. `boot()` est ensuite appelé sur **tous** les providers dans le même ordre

---

## 17. Système Async

FlexPHP propose un système unique pour rendre n'importe quel élément de l'interface dynamique **sans écrire de JavaScript** et sans modifier votre code serveur.

### Côté serveur

Le serveur détecte si la requête est async via l'en-tête `X-Flex-Async: true` :

```php
use FlexPHP\Async\AsyncResponse;

class ArticleController
{
    public function index(Request $request): Response
    {
        $articles = $this->articles->findAll();

        // AsyncResponse retourne soit la page complète (requête normale),
        // soit uniquement le fragment demandé (requête async)
        return AsyncResponse::render(
            $request,
            fullView: 'articles/index',       // vue complète
            fragmentView: 'articles/list',    // fragment injecté
            data: ['articles' => $articles],
        );
    }
}
```

Créez le fragment `views/articles/list.php` (juste le contenu sans layout) :

```php
<ul id="article-list">
    <?php foreach ($articles as $article): ?>
        <li><?= htmlspecialchars($article->titre) ?></li>
    <?php endforeach; ?>
</ul>
```

### Côté client — flex.js

Incluez la bibliothèque dans votre layout :

```html
<script src="/js/flex.js"></script>
```

### Attributs HTML disponibles

| Attribut | Description |
|---|---|
| `flex-async` | Marque l'élément comme déclencheur async |
| `flex-target="#selecteur"` | Sélecteur CSS de l'élément à mettre à jour |
| `flex-swap="innerHTML"` | Mode d'injection : `innerHTML`, `outerHTML`, `append`, `prepend` |
| `flex-trigger="click"` | Événement déclencheur : `click`, `submit`, `load`, `hover` |
| `flex-method="GET"` | Méthode HTTP : `GET`, `POST` |
| `flex-loading="#selecteur"` | Sélecteur de l'indicateur de chargement |

### Exemples concrets

**Charger du contenu au clic :**

```html
<button
    flex-async
    flex-target="#contenu"
    flex-trigger="click"
    flex-method="GET"
    href="/articles/42/commentaires"
>
    Voir les commentaires
</button>

<div id="contenu">
    <!-- Le fragment sera injecté ici -->
</div>
```

**Soumettre un formulaire sans rechargement :**

```html
<form
    flex-async
    flex-target="#messages"
    flex-swap="append"
    flex-loading="#spinner"
    action="/messages"
    method="POST"
>
    <input type="hidden" name="_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="text" name="contenu" placeholder="Votre message">
    <button type="submit">Envoyer</button>
</form>

<div id="spinner" style="display:none">Envoi...</div>
<div id="messages"></div>
```

**Charger automatiquement au démarrage de la page :**

```html
<div
    flex-async
    flex-target="#stats"
    flex-trigger="load"
    flex-method="GET"
    href="/dashboard/stats"
>
</div>

<div id="stats">Chargement des statistiques...</div>
```

**Mise à jour au survol :**

```html
<span
    flex-async
    flex-target="#preview"
    flex-trigger="hover"
    flex-method="GET"
    href="/articles/42/preview"
>
    Article 42
</span>

<div id="preview"></div>
```

---

## 18. CLI — `php flex`

### Commandes intégrées

```bash
# Démarrer le serveur de développement (localhost:8000)
php flex serve

# Générer un contrôleur
php flex make:controller NomController
php flex make:controller NomController --resource   # avec les 7 méthodes CRUD

# Générer un modèle (entité Cycle ORM)
php flex make:model NomModele

# Générer un fichier de migration
php flex make:migration create_nom_table

# Migrations
php flex migrate                  # appliquer les migrations en attente
php flex migrate --rollback       # annuler le dernier batch
php flex migrate --fresh          # tout réinitialiser et réappliquer

# Lister toutes les routes enregistrées
php flex route:list
```

Exemple de sortie de `route:list` :

```
+--------+---------------------------+------------------+-------------------+
| Method | URI                       | Handler          | Name              |
+--------+---------------------------+------------------+-------------------+
| GET    | /                         | HomeController   | home              |
| GET    | /articles                 | ArticleController| articles.index    |
| POST   | /articles                 | ArticleController| articles.store    |
| GET    | /articles/{id}            | ArticleController| articles.show     |
+--------+---------------------------+------------------+-------------------+
```

### Créer une commande personnalisée

```bash
# Structure suggérée : app/Console/Commands/
```

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use FlexPHP\Console\BaseCommand;

class EnvoyerNewsletterCommand extends BaseCommand
{
    public function getName(): string
    {
        return 'newsletter:envoyer';
    }

    public function getDescription(): string
    {
        return 'Envoie la newsletter aux abonnés actifs';
    }

    public function handle(): int
    {
        $limit = (int) $this->getOption('limit', 100);
        $dry   = $this->hasOption('dry-run');

        $this->info("Envoi de la newsletter (limit: {$limit})");

        if ($dry) {
            $this->warn('Mode dry-run : aucun email ne sera envoyé');
        }

        try {
            // Logique d'envoi...
            $count = $this->sendNewsletters($limit, $dry);

            $this->success("Newsletter envoyée à {$count} abonné(s).");
            return 0; // succès
        } catch (\Throwable $e) {
            $this->error('Erreur : ' . $e->getMessage());
            return 1; // échec
        }
    }

    private function sendNewsletters(int $limit, bool $dry): int
    {
        // Accès au conteneur si besoin : $this->app->make(MailerInterface::class)
        return 0;
    }
}
```

**Méthodes disponibles dans BaseCommand :**

```php
// Arguments positionnels
$this->getArgument(2);            // argv[2] — premier argument utilisateur
$this->getArgument(2, 'défaut');

// Options nommées
$this->getOption('format');       // --format=json  → 'json'
$this->getOption('format', 'txt');// valeur par défaut
$this->hasOption('verbose');      // --verbose       → true/false

// Sortie colorisée
$this->info('Message cyan');
$this->success('Message vert');
$this->warn('Avertissement jaune');
$this->error('Erreur rouge (vers STDERR)');
$this->line('Texte brut sans couleur');

// Accès à l'application
$mailer = $this->app->make(MailerInterface::class);
```

### Enregistrer une commande

Dans `config/commands.php` :

```php
<?php

use App\Console\Commands\EnvoyerNewsletterCommand;
use FlexPHP\Console\Commands\MakeControllerCommand;
// ... autres commandes intégrées

return [
    // Commandes intégrées
    'make:controller' => MakeControllerCommand::class,
    'make:model'      => MakeModelCommand::class,
    'make:migration'  => MakeMigrationCommand::class,
    'migrate'         => MigrateCommand::class,
    'route:list'      => RouteListCommand::class,
    'serve'           => ServeCommand::class,

    // Vos commandes
    'newsletter:envoyer' => EnvoyerNewsletterCommand::class,
];
```

Utilisation :

```bash
php flex newsletter:envoyer --limit=50
php flex newsletter:envoyer --limit=50 --dry-run
php flex newsletter:envoyer --help
```

---

## 19. Tests

FlexPHP utilise PHPUnit. Les tests sont dans `tests/Unit/` (tests unitaires) et `tests/Feature/` (tests d'intégration).

```bash
# Lancer toute la suite
./vendor/bin/phpunit

# Uniquement les tests unitaires
./vendor/bin/phpunit --testsuite Unit

# Uniquement les tests de fonctionnalité
./vendor/bin/phpunit --testsuite Feature

# Un test spécifique
./vendor/bin/phpunit --filter ArticleRepositoryTest

# Avec rapport de couverture (nécessite Xdebug)
./vendor/bin/phpunit --coverage-html storage/coverage
```

**Exemple de test unitaire :**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testJsonResponseHasCorrectContentType(): void
    {
        $response = Response::json(['clé' => 'valeur']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'application/json',
            $response->getHeader('Content-Type')[0]
        );
    }

    public function testNotFoundResponseHas404Status(): void
    {
        $response = Response::notFound('Page introuvable');

        $this->assertSame(404, $response->getStatusCode());
    }
}
```

**Exemple de test de fonctionnalité :**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use FlexPHP\Core\Application;
use PHPUnit\Framework\TestCase;

class ArticleRoutesTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->bootstrap();
    }

    public function testListeArticlesRetourne200(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/articles';

        ob_start();
        $this->app->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('articles', strtolower($output));
    }
}
```

---

## 20. Standards PSR respectés

| Standard | Description | Classe concernée |
|---|---|---|
| PSR-1 | Style de codage de base | Tout le codebase |
| PSR-3 | Interface Logger | `FlexPHP\Log\Logger` |
| PSR-4 | Autoloading | `FlexPHP\` → `src/`, `App\` → `app/` |
| PSR-7 | Messages HTTP | `FlexPHP\Http\Request`, `Response` |
| PSR-11 | Interface Container | `FlexPHP\Core\Container` |
| PSR-12 | Style de codage étendu | Tout le codebase |
| PSR-14 | Dispatcher d'événements | `FlexPHP\Events\EventDispatcher` |
| PSR-15 | Middleware HTTP | `FlexPHP\Http\Middleware\MiddlewareStack` |

---

## 21. Référence rapide

### Commandes CLI

```bash
php flex serve                              # Serveur de dev
php flex make:controller NomController     # Créer un contrôleur
php flex make:controller Nom --resource    # Contrôleur CRUD
php flex make:model NomModele              # Créer un modèle
php flex make:migration nom_migration      # Créer une migration
php flex migrate                           # Appliquer les migrations
php flex migrate --rollback                # Annuler le dernier batch
php flex migrate --fresh                   # Réinitialiser tout
php flex route:list                        # Lister les routes
```

### Réponses courantes

```php
Response::html($html);               // 200 HTML
Response::html($html, 404);          // 404 HTML
Response::json($data);               // 200 JSON
Response::json($data, 201);          // 201 JSON
Response::redirect('/url');          // 302 Redirect
Response::notFound();                // 404
```

### Raccourcis Request

```php
$request->input('champ');            // POST
$request->query('param');            // GET
$request->json('clé');               // JSON body
$request->isAsyncRequest();          // X-Flex-Async
$request->expectsJson();             // Accept: application/json
```

### Attributs flex.js

```html
flex-async                           <!-- déclenche le mode async -->
flex-target="#id"                    <!-- cible de l'injection -->
flex-swap="innerHTML|outerHTML|append|prepend"
flex-trigger="click|submit|load|hover"
flex-method="GET|POST"
flex-loading="#spinner"
```

---

*FlexPHP — Léger par conception, puissant par convention.*
