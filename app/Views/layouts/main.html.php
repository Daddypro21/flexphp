<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title: child templates can override this via $this->section('title') -->
    <title><?= $this->yield('title', 'FlexPHP App') ?></title>

    <!-- Meta description: optionally overridden by child templates -->
    <meta name="description" content="<?= $this->yield('meta_description', 'A FlexPHP application') ?>">

    <!-- Minimal inline CSS — no external framework required -->
    <style>
        /* ------------------------------------------------------------------ */
        /* Reset & base                                                         */
        /* ------------------------------------------------------------------ */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 16px;
            scroll-behavior: smooth;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                         Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ------------------------------------------------------------------ */
        /* Navigation                                                           */
        /* ------------------------------------------------------------------ */
        .navbar {
            background-color: #1a1a2e;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
            box-shadow: 0 2px 4px rgba(0,0,0,.25);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-brand {
            color: #e94560;
            font-size: 1.25rem;
            font-weight: 700;
            text-decoration: none;
            letter-spacing: .03em;
        }

        .navbar-nav {
            list-style: none;
            display: flex;
            gap: 0.25rem;
        }

        .navbar-nav a {
            color: #ccd6f6;
            text-decoration: none;
            padding: 0.4rem 0.85rem;
            border-radius: 4px;
            font-size: 0.9rem;
            transition: background-color 0.2s, color 0.2s;
        }

        .navbar-nav a:hover,
        .navbar-nav a.active {
            background-color: #e94560;
            color: #ffffff;
        }

        /* ------------------------------------------------------------------ */
        /* Main content area                                                    */
        /* ------------------------------------------------------------------ */
        .container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1.25rem;
        }

        main#main-content {
            flex: 1;
            padding: 2rem 1.25rem;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
        }

        /* ------------------------------------------------------------------ */
        /* Footer                                                               */
        /* ------------------------------------------------------------------ */
        footer {
            background-color: #1a1a2e;
            color: #8892b0;
            text-align: center;
            padding: 1rem;
            font-size: 0.8rem;
        }

        footer a {
            color: #e94560;
            text-decoration: none;
        }

        /* ------------------------------------------------------------------ */
        /* Loading indicator (shown/hidden by FlexPHP JS)                      */
        /* ------------------------------------------------------------------ */
        #flex-global-loading {
            display: none;           /* Hidden by default; JS toggles visibility */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #e94560 0%, #0f3460 100%);
            z-index: 9999;
            animation: loading-slide 1.2s ease-in-out infinite;
        }

        @keyframes loading-slide {
            0%   { transform: scaleX(0); transform-origin: left; }
            50%  { transform: scaleX(0.6); transform-origin: left; }
            100% { transform: scaleX(1); transform-origin: left; opacity: 0; }
        }

        /* ------------------------------------------------------------------ */
        /* Utility classes                                                      */
        /* ------------------------------------------------------------------ */
        .text-center { text-align: center; }
        .mt-1 { margin-top: 0.5rem; }
        .mt-2 { margin-top: 1rem; }
        .mt-3 { margin-top: 1.5rem; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
        .mb-3 { margin-bottom: 1.5rem; }
        .btn {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary   { background-color: #e94560; color: #fff; }
        .btn-secondary { background-color: #0f3460; color: #fff; }
        .btn-outline   { background-color: transparent; border: 2px solid #e94560; color: #e94560; }
    </style>

    <!-- Allow child templates to inject additional head content (e.g. page-specific CSS) -->
    <?= $this->yield('head') ?>
</head>
<body>

    <!-- Top-of-page loading bar (controlled by the FlexPHP JS library) -->
    <div id="flex-global-loading" aria-hidden="true"></div>

    <!-- -------------------------------------------------------------------- -->
    <!-- Navigation bar                                                          -->
    <!-- -------------------------------------------------------------------- -->
    <nav class="navbar" aria-label="Main navigation">
        <!-- Brand / logo link — async-navigates to home -->
        <a href="/"
           class="navbar-brand"
           flex-async
           flex-target="#main-content"
           flex-trigger="click"
           flex-loading="#flex-global-loading">
            FlexPHP
        </a>

        <!-- Navigation links using flex-async for smooth partial page updates -->
        <ul class="navbar-nav">
            <li>
                <a href="/"
                   flex-async
                   flex-target="#main-content"
                   flex-trigger="click"
                   flex-loading="#flex-global-loading">
                    Home
                </a>
            </li>
            <li>
                <a href="/about"
                   flex-async
                   flex-target="#main-content"
                   flex-trigger="click"
                   flex-loading="#flex-global-loading">
                    About
                </a>
            </li>
            <li>
                <a href="/demo"
                   flex-async
                   flex-target="#main-content"
                   flex-trigger="click"
                   flex-loading="#flex-global-loading">
                    Demo
                </a>
            </li>
        </ul>
    </nav>

    <!-- -------------------------------------------------------------------- -->
    <!-- Main content — async responses are injected here                       -->
    <!-- -------------------------------------------------------------------- -->
    <main id="main-content" role="main">
        <?= $this->yield('content') ?>
    </main>

    <!-- -------------------------------------------------------------------- -->
    <!-- Footer                                                                  -->
    <!-- -------------------------------------------------------------------- -->
    <footer>
        <p>
            &copy; <?= date('Y') ?> FlexPHP &mdash;
            Built with <a href="https://github.com/flexphp/flexphp" target="_blank" rel="noopener">FlexPHP Framework</a>
        </p>
    </footer>

    <!-- FlexPHP async JS library — loaded at end of body for best performance -->
    <script src="/js/flex.js"></script>

    <!-- Allow child templates to inject page-specific scripts -->
    <?= $this->yield('scripts') ?>

</body>
</html>
