<?php

declare(strict_types=1);

/**
 * FlexPHP — Public entry point.
 * All HTTP requests are routed through this file.
 */

define('FLEX_START', microtime(true));

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap and run the application
$app = new FlexPHP\Core\Application(dirname(__DIR__));
$app->bootstrap();
$app->run();
