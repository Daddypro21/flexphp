/**
 * FlexPHP Async JS Library
 *
 * Provides declarative async page updates using HTML attributes.
 * Elements marked with [flex-async] will intercept their default
 * browser action and instead fetch content from the server, injecting
 * the response into a target element in the DOM.
 *
 * Supported attributes:
 *   flex-async              — marks element as async-capable
 *   flex-target="#sel"      — CSS selector for the element to update
 *   flex-trigger="event"    — click|submit|load|hover (auto-detected if omitted)
 *   flex-method="GET|POST"  — HTTP method override
 *   flex-loading="#sel"     — selector for a loading indicator element
 *   flex-swap="mode"        — innerHTML|outerHTML|append|prepend (default: innerHTML)
 *
 * Public API (window.FlexPHP):
 *   FlexPHP.bind(element)          — bind flex-async behaviour to a single element
 *   FlexPHP.request(url, options)  — low-level fetch wrapper
 *
 * Custom events dispatched on the target element:
 *   flex:before  — fired before the request is sent
 *   flex:after   — fired after the response is injected  (detail: { html, response })
 *   flex:error   — fired when an error occurs            (detail: { error })
 */
(function (window, document) {
    'use strict';

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a CSS selector to a DOM element.
     * Returns null when the selector is empty or no element is found.
     *
     * @param {string} selector
     * @returns {Element|null}
     */
    function resolveTarget(selector) {
        if (!selector) return null;
        return document.querySelector(selector);
    }

    /**
     * Show or hide a loading indicator element.
     *
     * @param {string} selector  CSS selector for the loading element
     * @param {boolean} visible  true = show, false = hide
     */
    function setLoading(selector, visible) {
        var el = resolveTarget(selector);
        if (!el) return;
        el.style.display = visible ? '' : 'none';
    }

    /**
     * Inject HTML content into a target element according to the swap mode.
     *
     * @param {Element} target   Destination DOM element
     * @param {string}  html     HTML string to inject
     * @param {string}  swap     Swap strategy: innerHTML | outerHTML | append | prepend
     */
    function swapContent(target, html, swap) {
        switch (swap) {
            case 'outerHTML':
                target.outerHTML = html;
                break;
            case 'append':
                target.insertAdjacentHTML('beforeend', html);
                break;
            case 'prepend':
                target.insertAdjacentHTML('afterbegin', html);
                break;
            case 'innerHTML':
            default:
                target.innerHTML = html;
                break;
        }
    }

    /**
     * Dispatch a CustomEvent on an element (or document when element is null).
     *
     * @param {Element|Document} element  Target of the event
     * @param {string}           name     Event name
     * @param {object}           detail   Additional data attached to event.detail
     */
    function dispatch(element, name, detail) {
        var node = element || document;
        var evt  = new CustomEvent(name, { bubbles: true, cancelable: true, detail: detail || {} });
        node.dispatchEvent(evt);
    }

    // -------------------------------------------------------------------------
    // Core request function
    // -------------------------------------------------------------------------

    /**
     * Send a fetch request with the FlexPHP async header.
     * Detects Content-Type on the response and returns either an HTML string
     * or a serialised JSON string so callers always receive a string.
     *
     * @param {string} url      Destination URL
     * @param {object} options  Standard fetch init options plus optional `body`
     * @returns {Promise<{html: string, response: Response}>}
     */
    function flexRequest(url, options) {
        // Merge caller options with FlexPHP defaults
        var init = Object.assign({
            method: 'GET',
            headers: {}
        }, options || {});

        // Always advertise that this is an async FlexPHP request
        init.headers['X-Flex-Async'] = 'true';
        init.headers['X-Requested-With'] = 'XMLHttpRequest';

        return fetch(url, init).then(function (response) {
            var contentType = response.headers.get('Content-Type') || '';

            if (contentType.indexOf('application/json') !== -1) {
                // Return serialised JSON as a string so the caller can inject it
                return response.json().then(function (data) {
                    return { html: JSON.stringify(data, null, 2), response: response };
                });
            }

            // Default: treat response body as HTML
            return response.text().then(function (html) {
                return { html: html, response: response };
            });
        });
    }

    // -------------------------------------------------------------------------
    // Element binding
    // -------------------------------------------------------------------------

    /**
     * Read all flex-* attributes from an element and return a configuration object.
     *
     * @param {Element} element
     * @returns {object}
     */
    function readConfig(element) {
        return {
            target:  element.getAttribute('flex-target')  || null,
            trigger: element.getAttribute('flex-trigger') || null,
            method:  (element.getAttribute('flex-method') || 'GET').toUpperCase(),
            loading: element.getAttribute('flex-loading') || null,
            swap:    element.getAttribute('flex-swap')    || 'innerHTML'
        };
    }

    /**
     * Build the request URL and fetch init options for a given element and event.
     *
     * For forms with POST method the body is built from FormData serialised as
     * URLSearchParams; for GET forms the query string is appended to the action.
     *
     * @param {Element} element  The flex-async element
     * @param {string}  method   Resolved HTTP method
     * @param {Event}   evt      The originating DOM event (may be null for load/hover)
     * @returns {{ url: string, init: object }}
     */
    function buildRequest(element, method, evt) {
        var url  = element.href || element.action || window.location.href;
        var init = { method: method, headers: {} };

        if (element.tagName === 'FORM') {
            var formData   = new FormData(element);
            var urlEncoded = new URLSearchParams(formData).toString();

            if (method === 'POST' || method === 'PUT' || method === 'PATCH') {
                init.body = urlEncoded;
                init.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            } else {
                // Append form fields to URL for GET forms
                url = url.split('?')[0] + (urlEncoded ? '?' + urlEncoded : '');
            }
        }

        return { url: url, init: init };
    }

    /**
     * Execute the async fetch cycle for a flex-async element:
     *   1. Show loading indicator
     *   2. Dispatch flex:before
     *   3. Fetch the URL
     *   4. Swap content into target
     *   5. Dispatch flex:after
     *   6. Hide loading indicator
     *   7. Re-bind any new flex-async elements inside the target
     *
     * @param {Element} element  The element that triggered the request
     * @param {Event}   evt      The originating DOM event (may be null)
     */
    function execute(element, evt) {
        var config = readConfig(element);
        var target = resolveTarget(config.target);

        // Show loading indicator before the request begins
        setLoading(config.loading, true);

        // Allow listeners to cancel the request by calling preventDefault()
        dispatch(target, 'flex:before', { element: element, config: config });

        var req = buildRequest(element, config.method, evt);

        flexRequest(req.url, req.init)
            .then(function (result) {
                if (target) {
                    swapContent(target, result.html, config.swap);
                    // Re-bind any flex-async elements that were just injected
                    bindAll(target);
                }
                dispatch(target, 'flex:after', { html: result.html, response: result.response });
            })
            .catch(function (error) {
                dispatch(target, 'flex:error', { error: error });
                console.error('[FlexPHP] Request error:', error);
            })
            .finally(function () {
                // Always hide the loading indicator when done
                setLoading(config.loading, false);
            });
    }

    /**
     * Attach the appropriate DOM event listener to a single flex-async element.
     * Idempotent: a data attribute flag prevents double-binding.
     *
     * @param {Element} element
     */
    function bindElement(element) {
        // Skip elements that have already been bound
        if (element.dataset.flexBound === 'true') return;
        element.dataset.flexBound = 'true';

        var tag     = element.tagName.toUpperCase();
        var config  = readConfig(element);

        // Determine which DOM event should trigger the request
        var trigger = config.trigger;
        if (!trigger) {
            trigger = (tag === 'FORM') ? 'submit' : 'click';
        }

        if (trigger === 'load') {
            // Fire immediately — DOMContentLoaded has already occurred at this point
            execute(element, null);
            return;
        }

        if (trigger === 'hover') {
            element.addEventListener('mouseenter', function (evt) {
                execute(element, evt);
            });
            return;
        }

        // click / submit
        element.addEventListener(trigger, function (evt) {
            // Prevent default browser navigation / form submission
            evt.preventDefault();
            execute(element, evt);
        });
    }

    /**
     * Scan a root element (or the whole document) and bind all
     * [flex-async] descendants found within it.
     *
     * @param {Element|Document} root  Scope for the search (default: document)
     */
    function bindAll(root) {
        root = root || document;
        var elements = root.querySelectorAll('[flex-async]');
        elements.forEach(function (el) {
            bindElement(el);
        });
    }

    // -------------------------------------------------------------------------
    // Initialisation
    // -------------------------------------------------------------------------

    /**
     * Bootstrap: bind all existing [flex-async] elements once the DOM is ready.
     */
    document.addEventListener('DOMContentLoaded', function () {
        bindAll(document);
    });

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Public FlexPHP object exposed on window.
     * Provides methods for programmatic interaction with the library.
     */
    window.FlexPHP = {

        /**
         * Bind flex-async behaviour to an element (or all [flex-async] children
         * inside the provided container element).
         *
         * Useful after dynamically inserting HTML into the page.
         *
         * @param {Element} element  A single [flex-async] element or a container
         */
        bind: function (element) {
            if (!element) return;
            // Bind the element itself if it carries the attribute
            if (element.hasAttribute && element.hasAttribute('flex-async')) {
                bindElement(element);
            }
            // Also bind any descendants
            bindAll(element);
        },

        /**
         * Low-level fetch wrapper.
         * Sends the X-Flex-Async header and resolves with { html, response }.
         *
         * @param {string} url      Request URL
         * @param {object} options  Optional fetch init overrides
         * @returns {Promise<{html: string, response: Response}>}
         */
        request: function (url, options) {
            return flexRequest(url, options);
        }
    };

}(window, document));
