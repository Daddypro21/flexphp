# FlexPHP Async System

FlexPHP ships with a zero-dependency async system that lets you load, update, and replace any part of the page without a full reload — and without writing a single line of custom JavaScript.

---

## 1. How It Works

The system has two halves that work together:

```
Browser                          Server
──────                           ──────
1. User clicks / form submits
2. flex.js intercepts the event
3. Adds X-Flex-Async header
4. fetch() → server               5. Router dispatches normally
                                  6. Controller runs as usual
                                  7. Request::isAsyncRequest() → true
                                  8. ViewEngine renders only the
                                     requested fragment, not the layout
9. Response body = HTML fragment
10. flex.js injects it into the
    target element per flex-swap
```

The server-side code never changes between a full-page request and an async request. The only difference is what the view engine renders.

### The JavaScript library (`public/js/flex.js`)

`flex.js` is approximately 2 kb minified. It:

- Scans the DOM on `DOMContentLoaded` for elements with `flex-async`.
- Attaches event listeners matching the `flex-trigger` attribute.
- On trigger: reads `flex-method`, `flex-target`, `flex-swap`, `flex-loading` and the element's `action`/`href`/`data-url`.
- Fires a `fetch()` request with header `X-Flex-Async: 1`.
- On response: finds the target element and applies the swap strategy.
- Dispatches custom DOM events throughout the lifecycle.

---

## 2. All `flex-*` HTML Attributes

### `flex-async`

Required on any element you want the library to manage. Acts as a marker.

```html
<button flex-async ...>Click me</button>
<form flex-async ...>...</form>
<div flex-async flex-trigger="load" ...></div>
```

### `flex-target="#selector"`

CSS selector of the element whose content will be replaced by the async response.

```html
<button flex-async flex-target="#results">Search</button>
<div id="results"><!-- content goes here --></div>
```

If omitted, the triggering element itself is the target.

### `flex-swap="innerHTML|outerHTML|append|prepend"`

Controls how the response HTML is inserted:

| Value       | Behaviour                                         |
|-------------|---------------------------------------------------|
| `innerHTML` | Replace only the inner HTML of the target (default) |
| `outerHTML` | Replace the entire target element including its tag |
| `append`    | Insert response after the last child of the target  |
| `prepend`   | Insert response before the first child of the target |

```html
<!-- Infinite scroll: append new items to the list -->
<button flex-async flex-target="#item-list" flex-swap="append"
        data-url="/items?page=2">Load more</button>
```

### `flex-trigger="click|submit|load|hover"`

When to fire the async request:

| Value    | Fires when …                                       |
|----------|----------------------------------------------------|
| `click`  | User clicks the element (default for non-form elements) |
| `submit` | Form is submitted (default for `<form>`)           |
| `load`   | Element enters the DOM (fires immediately)         |
| `hover`  | Mouse enters the element                           |

```html
<!-- Auto-load a user card on page load -->
<div flex-async flex-trigger="load" flex-target="#user-card"
     data-url="/users/42/card"></div>
<div id="user-card"></div>
```

### `flex-method="GET|POST|PUT|PATCH|DELETE"`

Override the HTTP method. Defaults to the element's natural method (`GET` for links/divs, `POST` for forms).

```html
<button flex-async flex-method="DELETE" flex-target="#item-5"
        flex-swap="outerHTML" data-url="/items/5">Delete</button>
```

### `flex-loading="#selector"`

CSS selector of an element to show while the request is in flight, and hide afterward.

```html
<button flex-async flex-target="#list" flex-loading="#spinner">Reload</button>
<div id="spinner" style="display:none">Loading…</div>
```

### `flex-confirm="message"`

Shows a native `confirm()` dialog before sending the request. Useful for destructive actions.

```html
<button flex-async flex-method="DELETE" flex-confirm="Delete this post?"
        data-url="/posts/7" flex-target="#post-7" flex-swap="outerHTML">
    Delete
</button>
```

### `flex-headers='{"X-Custom":"value"}'`

JSON-encoded extra request headers.

```html
<button flex-async flex-headers='{"X-CSRF-Token":"abc123"}' ...>Submit</button>
```

---

## 3. Server-Side: `Request::isAsyncRequest()`

The `Request` class checks for the presence of the `X-Flex-Async` header:

```php
// src/Http/Request.php
public function isAsyncRequest(): bool
{
    return $this->getHeaderLine('X-Flex-Async') === '1';
}
```

You can use this anywhere in your code:

```php
public function index(Request $request): Response
{
    if ($request->isAsyncRequest()) {
        // Return only the data fragment.
        return $this->view('posts._list', compact('posts'));
    }

    // Return the full page.
    return $this->view('posts.index', compact('posts'));
}
```

---

## 4. Server-Side: View Fragments in Async Mode

When `ViewEngine` detects an async request (via `Request::isAsyncRequest()` or the
`X-Flex-Fragment` header), it skips the layout wrapper and renders only the specified
partial or fragment view.

### Using view fragments directly

Pass a dot-notation path to a partial:

```php
return $this->view('posts._list', ['posts' => $posts]);
```

The `_list` naming convention (leading underscore) signals that a view is a fragment/partial.

### Fragment header (auto-detection)

`flex.js` automatically sends `X-Flex-Fragment: 1` alongside `X-Flex-Async: 1`. The ViewEngine checks:

```php
// Inside ViewEngine::render()
if ($request->isAsyncRequest()) {
    $this->renderWithoutLayout($view, $data);
} else {
    $this->renderWithLayout($view, $data);
}
```

You do not have to split the view into two files unless you want to. A single view can contain both the layout wrapper (ignored on async) and the fragment.

---

## 5. Using `AsyncResponse` in Controllers

`FlexPHP\Async\AsyncResponse` is a helper that simplifies returning different content for async vs full-page requests.

```php
use FlexPHP\Async\AsyncResponse;

public function index(Request $request, PostRepository $repo): Response
{
    $posts = $repo->findLatest(20);

    return AsyncResponse::make($request,
        fragment: $this->view('posts._list', compact('posts')),
        full:     $this->view('posts.index',  compact('posts')),
    );
}
```

`AsyncResponse::make()` returns the `fragment` response when the request is async, otherwise returns the `full` response.

### Lazy evaluation

Pass closures to defer rendering until needed:

```php
return AsyncResponse::make($request,
    fragment: fn() => $this->view('posts._list', compact('posts')),
    full:     fn() => $this->view('posts.index',  compact('posts')),
);
```

---

## 6. Example: Async Search

A live search that filters results as the user types.

### Route

```php
$router->get('/search', [SearchController::class, 'index'])->name('search');
```

### Controller

```php
public function index(Request $request, PostRepository $repo): Response
{
    $query = $request->query('q', '');
    $posts = $query ? $repo->search($query) : [];

    return AsyncResponse::make($request,
        fragment: $this->view('search._results', compact('posts', 'query')),
        full:     $this->view('search.index',    compact('posts', 'query')),
    );
}
```

### View (`views/search/index.php`)

```html
<?php $this->extends('layouts.app') ?>
<?php $this->startSection('content') ?>

<input
    type="search"
    name="q"
    id="search-input"
    placeholder="Search posts…"
    flex-async
    flex-trigger="input"
    flex-target="#search-results"
    flex-swap="innerHTML"
    data-url="/search">

<div id="search-results">
    <?php $this->include('search._results', compact('posts', 'query')) ?>
</div>

<?php $this->endSection() ?>
```

### Partial (`views/search/_results.php`)

```html
<?php if (empty($posts)): ?>
    <p>No results for "<?= $this->e($query) ?>".</p>
<?php else: ?>
    <?php foreach ($posts as $post): ?>
        <article>
            <a href="/posts/<?= $post->getId() ?>"><?= $this->e($post->getTitle()) ?></a>
        </article>
    <?php endforeach ?>
<?php endif ?>
```

`flex.js` debounces `input` events by 300 ms automatically, preventing a request on every keystroke.

---

## 7. Example: Async Form Submission with Feedback

### View

```html
<form
    id="contact-form"
    flex-async
    flex-trigger="submit"
    flex-target="#form-feedback"
    flex-swap="innerHTML"
    action="/contact"
    method="POST">

    <input type="text"   name="name"    placeholder="Your name">
    <input type="email"  name="email"   placeholder="Email">
    <textarea name="message" placeholder="Message"></textarea>

    <button type="submit">Send</button>
</form>

<div id="form-feedback"></div>
```

### Controller

```php
public function store(Request $request): Response
{
    $data = $request->validated([
        'name'    => 'required|string|max:100',
        'email'   => 'required|email',
        'message' => 'required|string',
    ]);

    if ($request->isAsyncRequest() && $request->hasValidationErrors()) {
        return $this->view('contact._errors', [
            'errors' => $request->validationErrors(),
        ]);
    }

    // Process …
    $this->mailer->send($data);

    if ($request->isAsyncRequest()) {
        return $this->view('contact._success');
    }

    return new RedirectResponse('/contact?sent=1');
}
```

---

## 8. Example: Infinite Scroll / Async Pagination

```html
<!-- views/posts/index.php -->

<ul id="post-list">
    <?php foreach ($posts as $post): ?>
        <li><?= $this->e($post->getTitle()) ?></li>
    <?php endforeach ?>
</ul>

<?php if ($nextPage): ?>
<button
    flex-async
    flex-trigger="click"
    flex-target="#post-list"
    flex-swap="append"
    data-url="/posts?page=<?= $nextPage ?>">
    Load more
</button>
<?php endif ?>
```

### Controller fragment view (`views/posts/_page.php`)

```html
<?php foreach ($posts as $post): ?>
    <li><?= $this->e($post->getTitle()) ?></li>
<?php endforeach ?>

<?php if ($nextPage): ?>
<!-- Replace the "Load more" button itself so it points to the next page -->
<button
    flex-async
    flex-trigger="click"
    flex-target="#post-list"
    flex-swap="append"
    data-url="/posts?page=<?= $nextPage ?>">
    Load more
</button>
<?php endif ?>
```

> Tip: return the `outerHTML` swap on the button element so it updates itself with the next page number automatically.

---

## 9. Custom JS Events

`flex.js` dispatches DOM events at each lifecycle stage. Listen to them for custom analytics, animations, or integration with third-party libraries.

| Event          | Fired on         | `event.detail` keys             |
|----------------|------------------|---------------------------------|
| `flex:before`  | trigger element  | `url`, `method`, `target`       |
| `flex:after`   | trigger element  | `url`, `response`, `html`       |
| `flex:error`   | trigger element  | `url`, `status`, `error`        |
| `flex:swap`    | target element   | `swap`, `html`                  |

### Example: show a toast on success

```javascript
document.addEventListener('flex:after', (event) => {
    const { url, response } = event.detail;
    if (response.ok) {
        showToast('Loaded successfully!');
    }
});
```

### Example: log errors

```javascript
document.addEventListener('flex:error', (event) => {
    console.error('Async request failed:', event.detail);
});
```

---

## 10. Manually Calling `FlexPHP.request()`

For cases where the declarative HTML attributes are not enough, call the JavaScript API directly.

```javascript
// Full signature
FlexPHP.request({
    url:     '/api/posts',       // required
    method:  'GET',              // default: 'GET'
    target:  '#results',         // CSS selector or element reference
    swap:    'innerHTML',        // default: 'innerHTML'
    body:    null,               // FormData, string, or object
    headers: {},                 // extra headers merged with defaults
    before:  (opts) => {},       // called before fetch
    after:   (html, res) => {},  // called on success
    error:   (err, res) => {},   // called on failure
});
```

### Example: programmatic load on a custom event

```javascript
document.getElementById('refresh-btn').addEventListener('click', () => {
    FlexPHP.request({
        url:    '/dashboard/stats',
        target: '#stats-widget',
        swap:   'innerHTML',
        after:  () => console.log('Stats refreshed.'),
    });
});
```

### Example: submit JSON via API

```javascript
FlexPHP.request({
    url:     '/api/items',
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ name: 'New item' }),
    after:   (html, response) => {
        if (response.ok) location.reload();
    },
});
```

---

## Summary

| Concern                      | Solution                                      |
|------------------------------|-----------------------------------------------|
| Mark an element as async     | Add `flex-async` attribute                    |
| Choose where response goes   | `flex-target="#selector"`                     |
| Choose how it is inserted    | `flex-swap="innerHTML\|outerHTML\|append\|prepend"` |
| Control when request fires   | `flex-trigger="click\|submit\|load\|hover"`   |
| Detect async on server       | `$request->isAsyncRequest()`                  |
| Return fragment vs full page | `AsyncResponse::make($request, $fragment, $full)` |
| Custom JS integration        | Listen to `flex:before`, `flex:after`, `flex:error` |
| Manual JS requests           | `FlexPHP.request({ url, method, target, … })` |
