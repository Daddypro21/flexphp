<?php

declare(strict_types=1);

namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use FlexPHP\View\ViewEngine;

/**
 * Base controller providing common helper methods for all controllers.
 * All application controllers should extend this class.
 */
abstract class BaseController
{
    public function __construct(
        protected ViewEngine $view,
        protected Request $request
    ) {
    }

    /**
     * Return a JSON response.
     *
     * @param mixed $data    Data to encode as JSON
     * @param int   $status  HTTP status code
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Render a view template and return a Response.
     * If the request is async, returns only the requested fragment.
     *
     * @param string $template  Template name relative to app/Views/ (e.g. 'users/index')
     * @param array  $data      Data passed to the template
     * @param int    $status    HTTP status code
     */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $html = $this->view->render($template, $data, $this->request->isAsyncRequest());
        return Response::html($html, $status);
    }

    /**
     * Return a redirect response.
     *
     * @param string $url     URL to redirect to
     * @param int    $status  HTTP redirect status code (301, 302, etc.)
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /**
     * Abort the request with a given HTTP status and message.
     *
     * @param int    $status   HTTP status code
     * @param string $message  Error message
     */
    protected function abort(int $status, string $message = ''): Response
    {
        return Response::html($message, $status);
    }
}
