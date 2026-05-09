<?php

declare(strict_types=1);

namespace FlexPHP\View;

use Closure;
use RuntimeException;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * FlexPHP Template Engine — Twig wrapper.
 *
 * Wraps Twig\Environment and exposes the same render/renderSection API
 * used by the rest of the framework. Templates are .twig files located
 * in the configured views directory.
 *
 * Extra globals available in every template:
 *   {{ csrf_token() }}   — current CSRF token string
 *   {{ csrf_field() }}   — hidden <input> with the CSRF token
 *   {{ url('name', {}) }} — named-route URL generation
 */
class ViewEngine
{
    private Environment $twig;

    /**
     * @param string        $viewsPath Absolute path to the views directory.
     * @param string        $cachePath Absolute path for compiled templates ('' = disabled).
     * @param bool          $debug     Enable Twig debug mode (auto-reload + dump()).
     * @param callable|null $urlResolver fn(string $name, array $params): string
     */
    public function __construct(
        string $viewsPath,
        string $cachePath = '',
        bool $debug = false,
        private $urlResolver = null,
    ) {
        $loader = new FilesystemLoader(rtrim($viewsPath, '/\\'));

        $options = [
            'debug'       => $debug,
            'auto_reload' => $debug,
            'cache'       => $cachePath !== '' ? rtrim($cachePath, '/\\') : false,
            'strict_variables' => false,
        ];

        $this->twig = new Environment($loader, $options);

        if ($debug) {
            $this->twig->addExtension(new DebugExtension());
        }

        $this->registerGlobals();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Render a template and return the HTML string.
     *
     * @param string $template   Template name relative to viewsPath, without extension.
     *                           E.g. 'welcome' → views/welcome.twig
     * @param array  $data       Variables passed to the template.
     * @param bool   $asyncMode  When true, renders the 'async' block only.
     * @return string Rendered HTML.
     */
    public function render(string $template, array $data = [], bool $asyncMode = false): string
    {
        $file = $this->resolveTemplate($template);

        if ($asyncMode) {
            return $this->twig->render($file, array_merge($data, ['_async' => true]));
        }

        return $this->twig->render($file, $data);
    }

    /**
     * Render a specific named block from a template (useful for async fragments).
     *
     * @param string $template Template name.
     * @param string $block    Block name to extract.
     * @param array  $data     Template variables.
     * @return string HTML content of the block.
     */
    public function renderBlock(string $template, string $block, array $data = []): string
    {
        $file = $this->resolveTemplate($template);
        return $this->twig->load($file)->renderBlock($block, $data);
    }

    /**
     * Inject a URL resolver used by the {{ url() }} Twig function.
     *
     * @param callable $resolver fn(string $name, array $params): string
     */
    public function setUrlResolver(callable $resolver): void
    {
        $this->urlResolver = $resolver;
    }

    /**
     * Expose the underlying Twig Environment for advanced configuration.
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Register framework-provided global functions into the Twig environment.
     */
    private function registerGlobals(): void
    {
        // {{ csrf_token() }}
        $this->twig->addFunction(new TwigFunction('csrf_token', function (): string {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (empty($_SESSION['_csrf_token'])) {
                $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['_csrf_token'];
        }));

        // {{ csrf_field() }} — outputs a hidden <input>
        $this->twig->addFunction(new TwigFunction('csrf_field', function (): string {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (empty($_SESSION['_csrf_token'])) {
                $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            }
            $token = htmlspecialchars($_SESSION['_csrf_token'], ENT_QUOTES, 'UTF-8');
            return '<input type="hidden" name="_token" value="' . $token . '">';
        }, ['is_safe' => ['html']]));

        // {{ url('route.name', {id: 42}) }}
        $this->twig->addFunction(new TwigFunction('url', function (string $name, array $params = []): string {
            if ($this->urlResolver === null) {
                throw new RuntimeException('No URL resolver has been injected into ViewEngine.');
            }
            return ($this->urlResolver)($name, $params);
        }));

        // {{ asset('css/app.css') }} — prepends a leading slash
        $this->twig->addFunction(new TwigFunction('asset', function (string $path): string {
            return '/' . ltrim($path, '/');
        }));
    }

    /**
     * Resolve a template name to a filename (with .twig extension).
     */
    private function resolveTemplate(string $template): string
    {
        // Strip any .twig suffix the caller may have included.
        $name = preg_replace('/\.twig$/', '', $template);
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name) . '.twig';
    }
}
