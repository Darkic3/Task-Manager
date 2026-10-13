/*
 * LinaMd — single choke point for turning AI markdown into HTML.
 *
 * WHY THIS FILE EXISTS
 * -------------------
 * AI replies are untrusted text. Rendering them with `marked.parse()` and
 * assigning the result to `innerHTML` lets a model reply (or a project name,
 * task title or note body echoed back by the model) inject scripts, event
 * handlers and `javascript:` URLs into the page.
 *
 * Every markdown render path in the AI surfaces MUST go through
 * `LinaMd.render()`. There is exactly one function that converts AI text to
 * an HTML string, so the sanitisation order is auditable in one place:
 *
 *     raw text  ->  marked.parse()  ->  DOMPurify.sanitize()  ->  string
 *                                                              |
 *                                                only THEN: el.innerHTML = ...
 *
 * The HTML is fully sanitised *before* it ever touches the DOM.
 *
 * FAIL-CLOSED CONTRACT
 * --------------------
 * If the markdown engine or the sanitizer is missing (CDN blocked, offline,
 * script error), `render()` degrades to fully-escaped plain text. It never
 * returns unescaped HTML. This is the property the regression tests assert.
 *
 * Loaded as a plain script (no bundler) from `public/js/lina-markdown.js`,
 * matching the project's CDN-script asset approach. `DOMPurify` must be
 * loaded before the first `render()` call.
 */
(function (window, document) {
    'use strict';

    /**
     * DOMPurify configuration.
     *  - USE_PROFILES.html  : HTML elements only. No <svg>/<math>, which are
     *                         the usual bypass vectors for markdown output.
     *  - FORBID_TAGS        : elements CommonMark/GFM never produce but that
     *                         are dangerous or useful for spoofing inside a
     *                         chat bubble (phishing forms, CSS exfiltration
     *                         via <style>, page hijack via <base>/<meta>,
     *                         external resources via <link>). `input` is kept
     *                         because GFM task lists need it, and all event
     *                         handlers are already stripped.
     *  - FORBID_ATTR        : inline `style` (CSS exfiltration) and the
     *                         form/action resource attributes.
     *  - ADD_ATTR           : attributes CommonMark/GFM legitimately emit
     *                         (task lists, tables, anchors) plus `target`/`rel`.
     */
    var PURIFY_CONFIG = {
        USE_PROFILES: { html: true },
        FORBID_TAGS: [
            'form', 'object', 'embed', 'iframe', 'frame', 'frameset', 'script',
            'style', 'link', 'meta', 'base', 'template', 'noscript', 'title',
            'svg', 'math', 'audio', 'video', 'source', 'track', 'canvas'
        ],
        FORBID_ATTR: [
            'style', 'formaction', 'action', 'background', 'dynsrc', 'lowsrc',
            'poster', 'srcset', 'formtarget', 'srcdoc'
        ],
        ADD_ATTR: [
            'target', 'rel', 'id', 'class', 'name',
            'colspan', 'rowspan', 'align', 'valign', 'headers', 'scope',
            'disabled', 'checked', 'start', 'reversed', 'open'
        ]
    };

    /**
     * Escape untrusted text into a plain-text HTML string.
     * Used whenever we cannot sanitise — the fail-closed branch.
     */
    function escapeHtml(raw) {
        var holder = document.createElement('div');
        holder.textContent = raw == null ? '' : String(raw);
        return holder.innerHTML;
    }

    /**
     * A sanitiser we trust must be present and callable. Anything else and we
     * refuse to emit HTML at all.
     */
    function sanitizerReady() {
        return typeof window.DOMPurify !== 'undefined'
            && typeof window.DOMPurify.sanitize === 'function';
    }

    function markdownReady() {
        return typeof window.marked !== 'undefined'
            && typeof window.marked.parse === 'function';
    }

    /**
     * `target="_blank"` without `rel="noopener"` allows reverse tabnabbing.
     * Applied to already-sanitised markup, on a detached node.
     */
    function hardenLinks(root) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return;
        }
        var links = root.querySelectorAll('a[target="_blank"]');
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            var rel = (a.getAttribute('rel') || '').split(/\s+/).filter(Boolean);
            ['noopener', 'noreferrer'].forEach(function (value) {
                if (rel.indexOf(value) === -1) {
                    rel.push(value);
                }
            });
            a.setAttribute('rel', rel.join(' '));
        }
    }

    /**
     * Render untrusted markdown to a SAFE HTML string.
     *
     * @param  {string} raw   untrusted markdown/model output
     * @return {string}       sanitised HTML (or escaped text, fail-closed)
     */
    function render(raw) {
        var text = raw == null ? '' : String(raw);

        if (text === '') {
            return '';
        }

        // Fail closed: no sanitizer -> never emit HTML.
        if (!sanitizerReady()) {
            return escapeHtml(text);
        }

        // No markdown engine -> treat the reply as plain text.
        if (!markdownReady()) {
            return escapeHtml(text);
        }

        var html;
        try {
            html = window.marked.parse(text);
        } catch (error) {
            return escapeHtml(text);
        }

        if (typeof html !== 'string') {
            return escapeHtml(text);
        }

        var clean;
        try {
            clean = window.DOMPurify.sanitize(html, PURIFY_CONFIG);
        } catch (error) {
            return escapeHtml(text);
        }

        if (typeof clean !== 'string') {
            return escapeHtml(text);
        }

        // `clean` is already sanitised; using a detached holder lets us harden
        // links without a second sanitising pass.
        var holder = document.createElement('div');
        holder.innerHTML = clean;
        hardenLinks(holder);

        return holder.innerHTML;
    }

    window.LinaMd = {
        render: render,
        escape: escapeHtml,
        /** Exposed for tests / diagnostics only. */
        isSanitized: function () { return sanitizerReady(); }
    };

})(window, document);
