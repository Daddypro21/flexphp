<?php

declare(strict_types=1);

namespace FlexPHP\Async;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use FlexPHP\View\ViewEngine;

/**
 * Helper for returning async-aware responses.
 *
 * When a request carries the X-Flex-Async header, the server returns
 * only the HTML fragment requested instead of the full page. This allows
 * the FlexPHP JS library to swap in just the changed portion of the DOM.
 */
class AsyncResponse
{
    public function __construct(
        private readonly ViewEngine $view,
        private readonly Request $request
    ) {
    }

    /**
     * Determine if the current request is an async flex request.
     */
    public function isAsync(): bool
    {
        return $this->request->isAsyncRequest();
    }

    /**
     * Return a full-page or fragment response depending on request type.
     *
     * @param string $template  Template name (e.g. 'users/index')
     * @param array  $data      Template variables
     * @param int    $status    HTTP status code
     */
    public function respond(string $template, array $data = [], int $status = 200): Response
    {
        $html = $this->view->render($template, $data, $this->isAsync());
        return Response::html($html, $status);
    }

    /**
     * Return only a specific fragment/section of a template.
     * Useful when you want to return a named section regardless of async state.
     *
     * @param string $template  Template name
     * @param array  $data      Template variables
     * @param string $section   Section name to extract
     */
    public function fragment(string $template, array $data = [], string $section = 'content'): Response
    {
        $html = $this->view->renderSection($template, $data, $section);
        return Response::html($html);
    }
}
