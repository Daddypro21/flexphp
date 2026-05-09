<?php
/**
 * Welcome page template.
 *
 * Extends the main layout and renders a hero section with a live async demo.
 * The demo button uses flex-async to load a partial response from /demo/partial
 * into #demo-result without a full page reload.
 */

// Inherit the main layout — all output from this file is captured into sections
$this->extend('layouts/main');
?>

<?php $this->section('title') ?>
Welcome &mdash; FlexPHP
<?php $this->endSection() ?>

<?php $this->section('meta_description') ?>
Welcome to FlexPHP — a lightweight, async-first PHP framework.
<?php $this->endSection() ?>

<?php $this->section('content') ?>

<!-- ========================================================================== -->
<!-- Hero section                                                                 -->
<!-- ========================================================================== -->
<section class="hero" style="
    background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 60%, #e94560 100%);
    color: #ffffff;
    padding: 5rem 2rem;
    text-align: center;
    border-radius: 8px;
    margin-bottom: 3rem;
">
    <h1 style="font-size: 3rem; font-weight: 800; margin-bottom: 1rem; letter-spacing: -.02em;">
        Welcome to <span style="color: #e94560;">FlexPHP</span>
    </h1>

    <p style="font-size: 1.25rem; max-width: 600px; margin: 0 auto 2rem; opacity: .85;">
        A lightweight, async-first PHP framework built for speed, simplicity,
        and modern development workflows.
    </p>

    <!-- CTA buttons -->
    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="/docs" class="btn btn-primary" style="font-size: 1rem; padding: .7rem 1.75rem;">
            Get Started
        </a>
        <a href="https://github.com/flexphp/flexphp"
           class="btn btn-outline"
           style="font-size: 1rem; padding: .7rem 1.75rem; border-color: #fff; color: #fff;"
           target="_blank" rel="noopener">
            View on GitHub
        </a>
    </div>
</section>

<!-- ========================================================================== -->
<!-- Version / feature highlights                                                 -->
<!-- ========================================================================== -->
<section style="margin-bottom: 3rem;">
    <h2 style="font-size: 1.6rem; margin-bottom: 1.5rem; color: #0f3460;">
        Framework Highlights
    </h2>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">

        <!-- Feature card: async -->
        <div style="background:#fff; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <h3 style="color:#e94560; margin-bottom:.5rem;">Async-First</h3>
            <p style="font-size:.9rem; color:#555;">
                Built-in declarative async updates via the
                <code style="background:#f0f0f0; padding:0 .3rem; border-radius:3px;">flex-async</code>
                HTML attribute. No custom JS required.
            </p>
        </div>

        <!-- Feature card: routing -->
        <div style="background:#fff; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <h3 style="color:#e94560; margin-bottom:.5rem;">Expressive Routing</h3>
            <p style="font-size:.9rem; color:#555;">
                Fluent route definitions with parameter binding, middleware
                groups, and named routes for clean URL generation.
            </p>
        </div>

        <!-- Feature card: ORM -->
        <div style="background:#fff; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <h3 style="color:#e94560; margin-bottom:.5rem;">Cycle ORM</h3>
            <p style="font-size:.9rem; color:#555;">
                First-class Cycle ORM integration with PHP 8 attribute-based
                entity declarations and auto-generated migrations.
            </p>
        </div>

        <!-- Feature card: PSR -->
        <div style="background:#fff; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <h3 style="color:#e94560; margin-bottom:.5rem;">PSR Compliant</h3>
            <p style="font-size:.9rem; color:#555;">
                Implements PSR-3 (Logging), PSR-7/15 (HTTP), PSR-11 (Container),
                and PSR-14 (Events) out of the box.
            </p>
        </div>

        <!-- Feature card: CLI -->
        <div style="background:#fff; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <h3 style="color:#e94560; margin-bottom:.5rem;">Powerful CLI</h3>
            <p style="font-size:.9rem; color:#555;">
                Code generators, migration runner, development server, and route
                inspector — all from a single <code style="background:#f0f0f0; padding:0 .3rem; border-radius:3px;">php flex</code> command.
            </p>
        </div>

        <!-- Feature card: version -->
        <div style="background:#fff; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <h3 style="color:#e94560; margin-bottom:.5rem;">Version Info</h3>
            <p style="font-size:.9rem; color:#555;">
                FlexPHP <strong>v1.0.0</strong><br>
                PHP <strong><?= $this->e(PHP_VERSION) ?></strong><br>
                Released <?= date('Y') ?>
            </p>
        </div>

    </div>
</section>

<!-- ========================================================================== -->
<!-- Async demo section                                                           -->
<!-- ========================================================================== -->
<section style="background:#fff; border-radius:8px; padding:2rem; box-shadow:0 2px 8px rgba(0,0,0,.08); margin-bottom:3rem;">

    <h2 style="font-size:1.4rem; margin-bottom:.75rem; color:#0f3460;">
        Live Async Demo
    </h2>

    <p style="color:#555; margin-bottom:1.25rem;">
        Click the button below. FlexPHP will fetch only the server-rendered
        fragment — no full-page reload. Open your browser's Network tab to
        confirm: the request carries the <code style="background:#f0f0f0;padding:0 .3rem;border-radius:3px;">X-Flex-Async: true</code> header.
    </p>

    <!--
        flex-async      — marks this button as an async element
        flex-target     — selector of the element to update with the response
        flex-trigger    — "click" fires on button click (default for non-links/forms)
        flex-loading    — show the global loading bar while fetching
        flex-swap       — replace the inner HTML of the target element
    -->
    <button
        type="button"
        class="btn btn-primary"
        flex-async
        flex-target="#demo-result"
        flex-trigger="click"
        flex-method="GET"
        flex-loading="#flex-global-loading"
        flex-swap="innerHTML"
        data-href="/demo/partial"
        onclick="this.setAttribute('href', this.dataset.href)"
        style="margin-bottom:1.25rem;">
        Load Async Fragment
    </button>

    <!--
        A simpler approach: use an <a> tag so flex-async can read href directly.
        The button above uses a data-href trick as an alternative pattern.
    -->
    <a href="/demo/partial"
       class="btn btn-secondary"
       flex-async
       flex-target="#demo-result"
       flex-trigger="click"
       flex-loading="#flex-global-loading"
       flex-swap="innerHTML"
       style="margin-left:.5rem; margin-bottom:1.25rem;">
        Load (link variant)
    </a>

    <!-- Target container where the async response HTML is injected -->
    <div id="demo-result"
         style="
            margin-top:1rem;
            padding:1.25rem;
            border:2px dashed #dee2e6;
            border-radius:6px;
            min-height:80px;
            color:#6c757d;
            font-style:italic;
         ">
        <!-- Placeholder shown before the first async load -->
        The async response will appear here…
    </div>

    <!-- Example: auto-load on page render (flex-trigger="load") -->
    <!--
    <div flex-async
         flex-target="#auto-loaded"
         flex-trigger="load"
         data-href="/demo/stats"
         style="display:none;">
    </div>
    <div id="auto-loaded"></div>
    -->

</section>

<!-- ========================================================================== -->
<!-- Quick-start code snippet                                                     -->
<!-- ========================================================================== -->
<section style="margin-bottom:3rem;">
    <h2 style="font-size:1.4rem; margin-bottom:1rem; color:#0f3460;">Quick Start</h2>

    <pre style="
        background:#1a1a2e;
        color:#ccd6f6;
        padding:1.5rem;
        border-radius:8px;
        overflow-x:auto;
        font-size:.875rem;
        line-height:1.7;
    "><code># Clone and install
git clone https://github.com/flexphp/flexphp my-app
cd my-app
composer install

# Start the dev server
php flex serve

# Generate a controller
php flex make:controller PostController --resource

# Run migrations
php flex migrate</code></pre>
</section>

<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
    /**
     * Listen for FlexPHP async events on the demo target element.
     * These fire regardless of which trigger or swap method was used.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var demoResult = document.getElementById('demo-result');
        if (!demoResult) return;

        // flex:before — request is about to be sent
        demoResult.addEventListener('flex:before', function () {
            demoResult.style.borderColor = '#e94560';
            demoResult.style.opacity     = '0.6';
        });

        // flex:after — response has been injected
        demoResult.addEventListener('flex:after', function () {
            demoResult.style.borderColor = '#28a745';
            demoResult.style.opacity     = '1';
        });

        // flex:error — something went wrong
        demoResult.addEventListener('flex:error', function (e) {
            demoResult.innerHTML = '<strong style="color:#dc3545;">Error:</strong> ' + e.detail.error.message;
            demoResult.style.borderColor = '#dc3545';
            demoResult.style.opacity     = '1';
        });
    });
</script>
<?php $this->endSection() ?>
