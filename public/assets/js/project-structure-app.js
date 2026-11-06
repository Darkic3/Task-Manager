/* Project structure Vue island (planner pattern: vendored build, no build step).
 *
 * Owns ONLY #st-body (zones + empty state) on the project show page.
 * Rows stay server-rendered HTML (same _structure-node partial the page boots
 * with, harvested from the live DOM): first paint is identical, and every
 * delegated vanilla handler keeps working (collapse, bulk, quick-add,
 * popover, drag&drop all bind on #st-manager itself, which Vue never replaces).
 *
 * Bridge: window.ProjectStructure.sync() — vanilla mutation flows call it
 * instead of location.reload(). Server stays source of truth (structure data
 * + batch row HTML in one sync), so counts/progress can never drift.
 *
 * REACTIVITY RULE: all state mutations MUST go through the mounted component
 * instance (vm.tree / vm.html). Never mutate raw seed objects.
 *
 * If Vue fails to load/mount for any reason, the Blade rows + vanilla
 * handlers keep working with the legacy reload behaviour untouched.
 */
(function () {
    'use strict';

    var bridge = { mounted: false, sync: function () { return Promise.resolve(false); } };
    window.ProjectStructure = bridge;

    var mgr = document.getElementById('st-manager');
    var bodyEl = document.getElementById('st-body');
    var seed = window.__PJ_STRUCTURE_INITIAL__;

    if (!mgr || !bodyEl || !window.Vue || !seed || !seed.children) {
        return;
    }

    var rootId = mgr.dataset.rootId;
    var structureUrl = mgr.dataset.structureUrl;
    var rowsUrl = mgr.dataset.rowsUrl;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var headers = {
        'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };
    var i18n = window.__PJ_I18N__ || {};
    var T = {
        chapter: i18n.chapter || 'Chapter',
        lesson: i18n.lesson || 'Lesson',
        chaptersGroups: i18n.chaptersGroups || 'Chapters & groups',
        ungroupedLessons: i18n.ungroupedLessons || 'Ungrouped lessons',
        ungroupedHint: i18n.ungroupedHint || '',
    };
    var CONTAINER_TYPES = { chapter: 1, section: 1, project: 1, subproject: 1 };
    var FA = document.documentElement.lang === 'fa';

    function faNum(n) {
        if (FA) {
            try { return Number(n).toLocaleString('fa-IR'); } catch (_) { /* fall through */ }
        }
        return String(n);
    }

    function isContainer(node) {
        return ((node.children && node.children.length) > 0) || !!CONTAINER_TYPES[node.type];
    }

    function countTypes(tree, types) {
        var total = 0;
        var walk = function (nodes) {
            (nodes || []).forEach(function (n) {
                if (types.indexOf(n.type) >= 0) total++;
                if (n.children && n.children.length) walk(n.children);
            });
        };
        walk(tree ? tree.children : []);
        return total;
    }

    /* Harvest server-rendered markup from the live DOM (single source of
       truth stays Blade; zero duplication, zero new translations). */
    function harvestBlocks() {
        var out = {};
        bodyEl.querySelectorAll('.st-node[data-node-id]').forEach(function (el) {
            var depth = 0;
            var p = el.parentElement;
            while (p && p !== bodyEl) {
                if (p.hasAttribute && p.hasAttribute('data-kids-body')) depth++;
                p = p.parentElement;
            }
            if (depth === 0) out[el.getAttribute('data-node-id')] = el.outerHTML;
        });
        return out;
    }

    function snapshotChecks() {
        var ids = [];
        mgr.querySelectorAll('.st-check:checked').forEach(function (c) {
            ids.push(c.getAttribute('data-check-id'));
        });
        return ids;
    }

    function restoreChecks(ids) {
        (ids || []).forEach(function (id) {
            var c = mgr.querySelector('.st-check[data-check-id="' + id + '"]');
            if (c) c.checked = true;
        });
        /* The bulk bar itself lives outside the island: if boxes were restored
           but the bar got out of sync, converge it from live state. */
        var bar = document.getElementById('st-bulkbar');
        var count = document.getElementById('st-bulkcount');
        if (bar && count) {
            var n = mgr.querySelectorAll('.st-check:checked').length;
            bar.classList.toggle('on', n > 0);
            try {
                count.textContent = FA ? Number(n).toLocaleString('fa-IR') : String(n);
            } catch (_) { count.textContent = String(n); }
        }
    }

    function afterRender(vm) {
        try {
            if (typeof window.__stApplyCollapsed === 'function') window.__stApplyCollapsed();
        } catch (_) { /* collapse is best-effort */ }
        updateCounts(vm);
    }

    function updateCounts(vm) {
        if (!vm) return;
        var setPill = function (zoneId, n) {
            var z = document.getElementById(zoneId);
            if (!z) return;
            var pill = z.querySelector('.st-zone-pill .n');
            if (pill) pill.textContent = faNum(n);
        };
        setPill('st-zone-containers', vm.containers.length);
        setPill('st-zone-ungrouped', vm.leaves.length);
        var stats = mgr.querySelector('.st-hero-stats');
        var chapters = countTypes(vm.tree, ['chapter', 'section']);
        var lessons = countTypes(vm.tree, ['lesson']);
        var applyStat = function (id, icon, n, label) {
            var el = document.getElementById(id);
            if (n <= 0) {
                if (el) el.hidden = true;
                return;
            }
            if (!el && stats) {
                el = document.createElement('span');
                el.className = 'st-stat';
                el.id = id;
                stats.appendChild(el);
            }
            if (el) {
                el.hidden = false;
                el.textContent = icon + ' ' + faNum(n) + ' ' + label;
            }
        };
        applyStat('st-stat-chapters', '📁', chapters, T.chapter);
        applyStat('st-stat-lessons', '📄', lessons, T.lesson);
    }

    async function fetchJSON(url) {
        var res = await fetch(url, { headers: headers });
        var j = await res.json().catch(function () { return {}; });
        if (!res.ok || !j.ok) throw new Error((j && j.message) || ('HTTP ' + res.status));
        return j;
    }

    function toastErr(msg) {
        if (window.alertSwal) window.alertSwal(msg, null, 'error');
        else if (window.console && console.error) console.error('[ProjectStructure]', msg);
    }

    var seedChildren = (seed.children || []).slice();
    var seedHtml = harvestBlocks();
    var seedEmpty = (function () {
        var e = document.getElementById('st-empty');
        return e ? e.innerHTML : '';
    })();

    var syncSeq = 0;
    var vm = null;

    var app = window.Vue.createApp({
        data: function () {
            return {
                tree: seed,
                html: seedHtml,
                emptyHtml: seedEmpty,
                t: T,
                rootId: rootId,
            };
        },
        computed: {
            top: function () { return (this.tree && this.tree.children) || []; },
            containers: function () {
                return this.top.filter(isContainer).map(function (n) { return n.id; });
            },
            leaves: function () {
                return this.top.filter(function (n) { return !isContainer(n); }).map(function (n) { return n.id; });
            },
            isEmpty: function () { return this.top.length === 0; },
        },
        methods: {
            fan: faNum,
            block: function (id) { return this.html[id] || ''; },
        },
        template: [
            '<div class="st-zone" id="st-zone-containers" v-show="containers.length">',
            '  <div class="st-zone-head"><span class="st-zone-pill">📁 {{ t.chaptersGroups }} <span class="n">{{ fan(containers.length) }}</span></span></div>',
            '  <div id="st-blocks-containers" data-blocks="containers">',
            '    <div v-for="id in containers" :key="\'c\'+id" v-html="block(id)"></div>',
            '  </div>',
            '</div>',
            '<div class="st-zone" id="st-zone-ungrouped" v-show="leaves.length">',
            '  <div class="st-zone-head"><span class="st-zone-pill amber">📄 {{ t.ungroupedLessons }} <span class="n">{{ fan(leaves.length) }}</span></span> <span class="st-zone-hint" v-if="t.ungroupedHint">{{ t.ungroupedHint }}</span></div>',
            '  <div class="st-zone-box">',
            '    <div id="st-blocks-ungrouped" data-blocks="ungrouped">',
            '      <div v-for="id in leaves" :key="\'u\'+id" v-html="block(id)"></div>',
            '    </div>',
            '  </div>',
            '</div>',
            '<div class="st-empty" id="st-empty" v-show="isEmpty">',
            '  <div v-if="emptyHtml" v-html="emptyHtml"></div>',
            '  <div v-else><div class="st-empty-ico">📂</div><div class="st-empty-t">{{ t.emptyTitle }}</div>',
            '    <div class="st-empty-cta">',
            '      <button type="button" class="st-btn primary" data-st-add="chapter" :data-st-parent="rootId"><i class="bi bi-folder-plus"></i> {{ t.newChapter }}</button>',
            '      <button type="button" class="st-btn plain" data-st-add="lesson" :data-st-parent="rootId"><i class="bi bi-file-earmark-plus"></i> {{ t.newLesson }}</button>',
            '    </div></div>',
            '</div>',
        ].join('\n'),
        mounted: function () { afterRender(this); },
        updated: function () { afterRender(this); },
    });

    /* Full reconcile from the server: exact data + exact row markup, then
       re-apply ephemeral UI state (collapse, bulk checks). Token-guarded so
       overlapping syncs cannot resurrect stale state. */
    bridge.sync = async function () {
        if (!vm) return false;
        var my = ++syncSeq;
        var checks = snapshotChecks();
        try {
            var t = await fetchJSON(structureUrl);
            if (my !== syncSeq) return true;
            var ids = (t.tree.children || []).map(function (c) { return c.id; });
            var rows = {};
            if (ids.length) {
                var q = ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
                var r = await fetchJSON(rowsUrl + '?' + q);
                if (my !== syncSeq) return true;
                rows = r.rows || {};
            }
            vm.tree = t.tree;
            vm.html = rows;
            await window.Vue.nextTick();
            return true;
        } catch (err) {
            toastErr(err.message);
            return false;
        } finally {
            restoreChecks(checks);
        }
    };

    try {
        vm = app.mount(bodyEl);
        bridge.mounted = true;
        window.__PJ_STRUCTURE_MOUNTED__ = true;
    } catch (e) {
        vm = null;
        bridge.mounted = false;
        if (window.console && console.error) console.error('[ProjectStructure] Vue mount failed, vanilla fallback active', e);
    }
})();
