<?php

declare(strict_types=1);

namespace FlexPHP\View;

use RuntimeException;
use InvalidArgumentException;

/**
 * FlexPHP Template Engine.
 *
 * Provides a PHP-based template system with layout inheritance, sections,
 * partials, CSRF helpers, and async (fragment) rendering support.
 *
 * Usage inside a template file:
 *   $this->extend('layouts/main');          // inherit a layout
 *   $this->section('content'); ... $this->endSection();
 *   echo $this->partial('_header');
 *   echo $this->e($untrustedValue);
 *   echo $this->csrfField();
 */
class ViewEngine
{
    /**
     * Absolute path to the directory that contains template files.
     */
    private string $viewsPath;

    /**
     * Absolute path for compiled/cached view files.
     * An empty string disables caching.
     */
    private string $cachePath;

    /**
     * Name of the layout that the current template wants to extend.
     * Null means no layout is used.
     */
    private ?string $layoutName = null;

    /**
     * Collected section buffers: [ sectionName => html ]
     */
    private array $sections = [];

    /**
     * Name of the section currently being buffered (between section/endSection calls).
     */
    private ?string $currentSection = null;

    /**
     * Callable that resolves a named route to a URL string.
     * Injected from outside so the view layer remains decoupled from the router.
     *
     * @var callable|null
     */
    private $urlResolver = null;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param string $viewsPath Absolute path to the views directory.
     * @param string $cachePath Absolute path for view cache (empty = disabled).
     */
    public function __construct(string $viewsPath, string $cachePath = '')
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
        $this->cachePath = rtrim($cachePath, '/\\');
    }

    // -------------------------------------------------------------------------
    // Public API – rendering
    // -------------------------------------------------------------------------

    /**
     * Render a template and return the resulting HTML string.
     *
     * When $asyncMode is true the method returns only the first defined section
     * (or the section named "main") without wrapping it in a layout. This is
     * used to return DOM fragments for XHR requests.
     *
     * @param string $template  Template name relative to $viewsPath, without extension.
     *                          E.g. 'welcome' resolves to 'views/welcome.html.php'.
     * @param array  $data      Variables to extract into template scope.
     * @param bool   $asyncMode Return only the primary fragment when true.
     * @return string Rendered HTML.
     * @throws RuntimeException When the template file cannot be found.
     */
    public function render(string $template, array $data = [], bool $asyncMode = false): string
    {
        // Reset layout and section state for each top-level render call
        $this->layoutName    = null;
        $this->sections      = [];
        $this->currentSection = null;

        // Capture the template's output
        $content = $this->renderFile($template, $data);

        // If the template requested a layout, render it now
        if ($this->layoutName !== null && !$asyncMode) {
            $layoutData            = array_merge($data, ['_content' => $content]);
            $this->currentSection  = null;
            $content               = $this->renderFile($this->layoutName, $layoutData);
        }

        // In async mode: return only the primary section fragment
        if ($asyncMode) {
            return $this->extractAsyncSection();
        }

        return $content;
    }

    /**
     * Render a template and return a specific named section.
     * The layout is never applied; only the named section's HTML is returned.
     *
     * @param string $template Template name.
     * @param array  $data     Template variables.
     * @param string $section  Name of the section to extract.
     * @return string HTML content of the section.
     */
    public function renderSection(string $template, array $data = [], string $section = 'content'): string
    {
        // Reset state
        $this->layoutName     = null;
        $this->sections       = [];
        $this->currentSection = null;

        $this->renderFile($template, $data);

        return $this->sections[$section]
            ?? throw new RuntimeException("Section '{$section}' was not defined in template '{$template}'.");
    }

    // -------------------------------------------------------------------------
    // Template directives – called from inside templates via $this->*()
    // -------------------------------------------------------------------------

    /**
     * Declare that this template extends a layout.
     *
     * Must be called before any output is produced in the template.
     *
     * @param string $layout Layout template name (e.g. 'layouts/main').
     */
    public function extend(string $layout): void
    {
        $this->layoutName = $layout;
    }

    /**
     * Begin capturing output into a named section.
     *
     * @param string $name Section name.
     * @throws RuntimeException When a section is already open.
     */
    public function section(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new RuntimeException(
                "Cannot open section '{$name}' while section '{$this->currentSection}' is still open."
            );
        }

        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End the current open section and store its buffered content.
     *
     * @throws RuntimeException When no section is currently open.
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException('endSection() called without a matching section() call.');
        }

        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    /**
     * Output (yield) the content of a named section from the child template.
     *
     * Called from inside a layout template.
     *
     * @param string $name    Section name.
     * @param string $default Fallback content when the section was not defined.
     * @return string The section's HTML or the default value.
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Render a partial template inline and return its HTML.
     *
     * @param string $name Template name for the partial.
     * @param array  $data Variables passed to the partial.
     * @return string Rendered HTML of the partial.
     */
    public function partial(string $name, array $data = []): string
    {
        return $this->renderFile($name, $data);
    }

    // -------------------------------------------------------------------------
    // Template helpers – called from inside templates via $this->*()
    // -------------------------------------------------------------------------

    /**
     * Escape a value for safe HTML output.
     *
     * Equivalent to htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').
     *
     * @param mixed $value Any scalar value.
     * @return string HTML-escaped string.
     */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Generate a URL for a named route.
     *
     * Requires a URL resolver to be injected via setUrlResolver().
     *
     * @param string $name   Route name.
     * @param array  $params Route parameters.
     * @return string The resolved URL.
     * @throws RuntimeException When no URL resolver has been injected.
     */
    public function url(string $name, array $params = []): string
    {
        if ($this->urlResolver === null) {
            throw new RuntimeException('No URL resolver has been injected into ViewEngine.');
        }

        return ($this->urlResolver)($name, $params);
    }

    /**
     * Return the current CSRF token stored in the session.
     *
     * @return string The CSRF token string.
     */
    public function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }

    /**
     * Return a hidden HTML input containing the CSRF token.
     *
     * @return string HTML input element.
     */
    public function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . $this->e($this->csrfToken()) . '">';
    }

    // -------------------------------------------------------------------------
    // Dependency injection
    // -------------------------------------------------------------------------

    /**
     * Inject a URL resolver callable used by $this->url().
     *
     * The callable receives (string $name, array $params) and must return a string URL.
     *
     * @param callable $resolver
     */
    public function setUrlResolver(callable $resolver): void
    {
        $this->urlResolver = $resolver;
    }

    // -------------------------------------------------------------------------
    // Internal rendering machinery
    // -------------------------------------------------------------------------

    /**
     * Locate and render a template file, binding $this to the ViewEngine instance
     * so that templates can call $this->extend(), $this->section(), etc.
     *
     * @param string $template Template name.
     * @param array  $data     Variables to extract into scope.
     * @return string Rendered output of the template.
     * @throws RuntimeException When the template file is not found.
     */
    private function renderFile(string $template, array $data = []): string
    {
        $path = $this->resolvePath($template);

        if (!file_exists($path)) {
            throw new RuntimeException("View template not found: '{$path}'.");
        }

        // Use a closure bound to $this so templates access ViewEngine methods as $this->*
        $renderer = Closure::bind(
            function (string $__path, array $__data): string {
                // Make all data variables available as local variables
                extract($__data, EXTR_SKIP);

                ob_start();
                require $__path;
                return ob_get_clean();
            },
            $this,    // bind $this to ViewEngine
            static::class
        );

        return $renderer($path, $data);
    }

    /**
     * Resolve a template name to an absolute file path.
     *
     * Checks for both *.html.php and *.php extensions.
     *
     * @param string $template Template name (e.g. 'layouts/main').
     * @return string Absolute file path.
     * @throws RuntimeException When no matching file is found.
     */
    private function resolvePath(string $template): string
    {
        // Try {name}.html.php first, then plain {name}.php
        $candidates = [
            $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.html.php',
            $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Return the first candidate so the caller can produce a meaningful error
        return $candidates[0];
    }

    /**
     * When in async mode, return the most appropriate section content.
     *
     * Priority: 'main' section → first defined section → empty string.
     *
     * @return string HTML fragment.
     */
    private function extractAsyncSection(): string
    {
        if (isset($this->sections['main'])) {
            return $this->sections['main'];
        }

        if (!empty($this->sections)) {
            return reset($this->sections);
        }

        return '';
    }
}
