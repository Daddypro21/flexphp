<?php

declare(strict_types=1);

namespace App\Controllers;

use FlexPHP\Http\Response;

/**
 * Handles the welcome/home page.
 */
class WelcomeController extends BaseController
{
    /**
     * Display the welcome page.
     */
    public function index(): Response
    {
        return $this->view('welcome', [
            'title'   => 'Welcome to FlexPHP',
            'version' => '1.0.0',
        ]);
    }
}
