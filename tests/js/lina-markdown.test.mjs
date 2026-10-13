/**
 * Behavioural test for public/js/lina-markdown.js â€” the single choke point that
 * turns AI markdown into HTML on the chat surfaces.
 *
 * Run with:   node --test tests/js/
 *
 * Zero dependencies on purpose (the project ships frontend libs by CDN and has
 * no node_modules / build step). A minimal DOM shim is enough to exercise the
 * module's real escape + delegation logic, so this file can run anywhere Node
 * does.
 *
 * What it proves:
 *   - the fail-closed contract: without the sanitizer, NO payload ever comes
 *     back as HTML (scripts, handlers, javascript: URLs, nested markup);
 *   - the module never bypasses the sanitizer when one is present;
 *   - normal text round-trips as text.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const MODULE_PATH = path.resolve(HERE, '../../public/js/lina-markdown.js');

function escape(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/** Minimal DOM shim â€” enough for the module's createElement('div') usage. */
function makeDom() {
    const makeEl = () => ({
        _html: '',
        set textContent(v) { this._html = escape(v); },
        get textContent() { return this._html; },
        set innerHTML(v) { this._html = String(v ?? ''); },
        get innerHTML() { return this._html; },
        querySelectorAll: () => [],
        setAttribute() {},
        getAttribute: () => null,
        appendChild() {},
    });
    return { createElement: makeEl };
}

/** Load the production module into a sandbox with a controllable window. */
function loadLinaMd({ dompurify, marked } = {}) {
    const dom = makeDom();
    const win = { document: dom };
    if (dompurify !== undefined) win.DOMPurify = dompurify;
    if (marked !== undefined) win.marked = marked;

    const code = readFileSync(MODULE_PATH, 'utf8');
    const fn = new Function('window', 'document', code);
    fn(win, dom);

    return win.LinaMd;
}

const PAYLOADS = [
    '<script>alert(1)</script>',
    '<img src=x onerror="alert(1)">',
    '[click](javascript:alert(1))',
    '<a href="jAvAsCrIpT:alert(1)">x</a>',
    '<svg/onload=alert(1)>',
    '<iframe src="data:text/html,<script>alert(1)</script>"></iframe>',
    '<div onclick="alert(1)">nested</div>',
    'ok **bold** <script>alert(1)</script> tail',
    '<math><mtext><script>alert(1)</script></mtext></math>',
    '<style>body{background:url(javascript:alert(1))}</style>',
    '<form><input onfocus="alert(1)" autofocus></form>',
    '<<script>script>alert(1)<</script>/script>',
];

/** True when the string contains a real (unescaped) HTML tag opener. */
function hasRawTag(html) {
    return /<[a-zA-Z!/?]/.test(html);
}

/**
 * True when a dangerous URL appears inside a *live attribute*. On plain,
 * escaped text `](javascript:â€¦)` is inert, so we only test this where real
 * markup is being produced.
 */
function hasDangerousUrl(html) {
    return /["'(]\s*j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t\s*:/i.test(html);
}

test('fails closed to escaped text when the sanitizer is missing', () => {
    const LinaMd = loadLinaMd(); // no DOMPurify, no marked

    for (const payload of PAYLOADS) {
        const out = LinaMd.render(payload);
        assert.equal(typeof out, 'string');
        assert.ok(!hasRawTag(out), `raw HTML tag leaked: ${out}`);
        // Everything must have been escaped into inert text.
        if (payload.includes('<')) {
            assert.ok(out.includes('&lt;'), `payload was not escaped: ${out}`);
            assert.ok(!out.includes('&amp;lt;'), `payload was double-escaped: ${out}`);
        }
    }
});

test('fails closed when marked is missing but a sanitizer is present', () => {
    const called = [];
    const LinaMd = loadLinaMd({
        dompurify: { sanitize: (html) => { called.push(html); return html; } },
    });

    for (const payload of PAYLOADS) {
        const out = LinaMd.render(payload);
        assert.deepEqual(called, [], 'sanitizer must not be asked to clean raw env HTML');
        assert.ok(!hasRawTag(out), `raw HTML tag leaked: ${out}`);
        assert.ok(out.includes('&lt;') || !payload.includes('<'), `payload was not escaped: ${out}`);
    }
});

test('never bypasses the sanitizer when both libraries are present', () => {
    const seen = [];
    const LinaMd = loadLinaMd({
        // A deliberately strict allowlist stand-in: anything it drops is gone.
        dompurify: {
            sanitize: (html) => {
                seen.push(html);
                return html.replace(/<script[\s\S]*?<\/script>/gi, '');
            },
        },
        marked: { parse: (md) => md }, // identity to observe delegation
    });

    const out = LinaMd.render('hello <script>alert(1)</script>');

    assert.equal(seen.length, 1, 'render must call DOMPurify.sanitize exactly once');
    assert.equal(seen[0], 'hello <script>alert(1)</script>');
    assert.ok(!out.includes('<script'), 'sanitizer output must be what is returned');
});

test('degrades to escaped text when marked throws', () => {
    const LinaMd = loadLinaMd({
        dompurify: { sanitize: (h) => h },
        marked: { parse: () => { throw new Error('boom'); } },
    });

    const out = LinaMd.render('<script>alert(1)</script>');
    assert.ok(!out.includes('<script'), `script leaked: ${out}`);
    assert.ok(out.includes('&lt;'), `not escaped: ${out}`);
});

test('empty and non-string input are safe no-ops', () => {
    const LinaMd = loadLinaMd();
    assert.equal(LinaMd.render(''), '');
    assert.equal(LinaMd.render(null), '');
    assert.equal(LinaMd.render(undefined), '');
    assert.ok(!LinaMd.render(12345).includes('<'));
});

test('attacker cannot disable the sanitizer via the payload', () => {
    const calls = [];
    const LinaMd = loadLinaMd({
        dompurify: { sanitize: (h, cfg) => { calls.push(cfg); return ''; } },
        marked: { parse: (md) => md },
    });

    LinaMd.render('</textarea><script>DOMPurify=null</script>');
    assert.equal(calls.length, 1, 'sanitizer must always be invoked');
    assert.ok(calls[0].USE_PROFILES, 'html-only profile must be requested');
    assert.ok(calls[0].FORBID_TAGS, 'FORBID_TAGS must be requested');
    assert.ok(calls[0].FORBID_ATTR, 'FORBID_ATTR must be requested');
    assert.ok(calls[0].FORBID_TAGS.includes('script'), 'script must be forbidden');
    assert.ok(!calls[0].FORBID_TAGS.includes('input'), 'input must stay allowed for GFM task lists');
});

/**
 * Live-markup contract: when real HTML is produced it must never carry a
 * dangerous URL scheme inside an attribute.
 */
test('rendered markup never carries a dangerous URL scheme', () => {
    const LinaMd = loadLinaMd({
        dompurify: {
            sanitize: (html) => html.replace(/(href|src)\s*=\s*["']?\s*javascript:[^"'>\s]*/gi, ''),
        },
        marked: {
            parse: (md) => md.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>'),
        },
    });

    const out = LinaMd.render('[click](javascript:alert(1))');
    assert.ok(!hasDangerousUrl(out), `dangerous URL survived: ${out}`);
    assert.ok(hasRawTag(out), 'expected a real anchor to be produced');
});
