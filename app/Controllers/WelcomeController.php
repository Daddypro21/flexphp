<?php

declare(strict_types=1);

namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use FlexPHP\Routing\Attributes\Get;

class WelcomeController extends BaseController
{
    #[Get('/', name: 'home')]
    public function index(Request $request): Response
    {
        return $this->view('welcome', [
            'title'   => 'Welcome to FlexPHP',
            'version' => '1.0.0',
        ]);
    }
}
