<?php

declare(strict_types=1);

namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use FlexPHP\Routing\Attributes\Get;
use FlexPHP\Routing\Attributes\Prefix;

#[Prefix('/docs')]
class DocsController extends BaseController
{
    private array $pages = [
        'getting-started' => 'Prise en main',
        'routing'         => 'Routage',
        'controllers'     => 'Contrôleurs',
        'requests'        => 'Requêtes HTTP',
        'responses'       => 'Réponses HTTP',
        'views'           => 'Vues & Templates',
        'database'        => 'Base de données',
        'orm'             => 'ORM & Entités',
        'migrations'      => 'Migrations',
        'middleware'       => 'Middleware',
        'events'          => 'Événements',
        'logging'         => 'Logging',
        'container'       => 'Conteneur DI',
        'providers'       => 'Service Providers',
        'async'           => 'Système Async',
        'cli'             => 'CLI',
        'routing-attrs'   => 'Annotations de routes',
    ];

    #[Get('/', name: 'docs.index')]
    public function index(Request $request): Response
    {
        return $this->view('docs/index', array_merge(
            $this->buildNavData(null),
            ['pages' => $this->pages, 'currentPage' => null]
        ));
    }

    #[Get('/{page}', name: 'docs.page')]
    public function page(Request $request, string $page): Response
    {
        if (!array_key_exists($page, $this->pages)) {
            return $this->abort(404, 'Page de documentation introuvable.');
        }

        return $this->view('docs/index', array_merge(
            $this->buildNavData($page),
            ['pages' => $this->pages, 'currentPage' => $page, 'pageTitle' => $this->pages[$page]]
        ));
    }

    private function buildNavData(?string $current): array
    {
        $keys  = array_keys($this->pages);
        $index = $current !== null ? array_search($current, $keys, true) : -1;

        $prevKey = $index > 0 ? $keys[$index - 1] : null;
        $nextKey = $current === null
            ? $keys[0]
            : ($index !== false && $index < count($keys) - 1 ? $keys[$index + 1] : null);

        return [
            'prevKey'   => $prevKey,
            'prevLabel' => $prevKey !== null ? $this->pages[$prevKey] : null,
            'nextKey'   => $nextKey,
            'nextLabel' => $nextKey !== null ? $this->pages[$nextKey] : null,
        ];
    }
}
