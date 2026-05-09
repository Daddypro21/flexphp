<?php

declare(strict_types=1);

namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;

class HelloController extends BaseController
{
    public function index(Request $request): Response
    {
        return $this->view('hello', ['name' => 'Monde']);
    }
}
