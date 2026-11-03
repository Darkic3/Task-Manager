/* Planner day task list — Vue 3 island (vendored, no build step).
 *
 * Owns ONLY the contents of #plPendingBody (the "Today's Tasks" groups).
 * Rows stay server-rendered HTML (same partial the page boots with), so every
 * existing behaviour keeps working: inline onclick/onchange handlers fire,
 * document-delegated handlers (toggle, postpone, fail, rename, estimate,
 * checklist) see the same data-attributes, and the drag&drop listeners bound
 * on the #plPendingBody container itself survive (Vue keeps the mount element).
 *
 * REACTIVITY RULE: all state mutations MUST go through the mounted component
 * instance (`vm.items`), never the raw seed array — raw mutations bypass
 * Vue 3's reactive proxy and silently skip rendering.
 *
 * If Vue fails to load or mount for any reason, the Blade-rendered rows stay
 * in place and the legacy vanilla insert/remove paths keep working untouched.
 */
(function () {
    'use strict';

    var mountEl = document.getElementById('plPendingBody');
    var initial = window.__PL_DAY_INITIAL__;

    if (!mountEl || !window.Vue || !initial || !Array.isArray(initial.items)) {
        return;
    }

    /* Empty-state markup (.pl-empty + .pl-qadd CTAs): read from the inert
       <template id="plEmptyTpl"> the day view always renders (single source:
       planner/_empty-day). Falls back to live nodes for pages rendered
       without the template. Shown whenever zero OPEN tasks remain. */
    var emptyHtml = '';
    var emptyTpl = document.getElementById('plEmptyTpl');
    if (emptyTpl && emptyTpl.innerHTML) {
        emptyHtml = emptyTpl.innerHTML;
    } else {
        mountEl.querySelectorAll('.pl-empty, .pl-qadd').forEach(function (el) {
            emptyHtml += el.outerHTML;
        });
    }

    var seedItems = initial.items.slice();
    seedItems.forEach(function (t) {
        if (t.done === undefined) t.done = false;
        if (!t.group) t.group = 'anytime';
    });
    var verifyTimer = null;
    var vm = null;

    function liveItems() {
        return vm ? vm.items : seedItems;
    }

    function refreshChrome() {
        try {
            if (typeof window.refreshCounters === 'function') window.refreshCounters();
        } catch (_) { /* counters are best-effort */ }
        try {
            if (typeof window.refreshNextUp === 'function') window.refreshNextUp();
        } catch (_) { /* next-up is best-effort */ }
    }

    function findIndex(id) {
        var items = liveItems();
        var key = String(id);
        for (var i = 0; i < items.length; i++) {
            if (String(items[i].id) === key) return i;
        }
        return -1;
    }

    function flashRow(id) {
        var node = mountEl.querySelector('[data-task-item][data-id="' + id + '"]');
        if (!node) return;
        node.classList.add('pl-flash');
        setTimeout(function () { node.classList.remove('pl-flash'); }, 1700);
    }

    var app = window.Vue.createApp({
        data: function () {
            return {
                items: seedItems,
                periods: initial.periods || {},
                order: initial.order || ['anytime'],
                emptyHtml: emptyHtml,
            };
        },
        computed: {
            openCount: function () {
                return this.items.filter(function (t) { return !t.done; }).length;
            },
            groups: function () {
                var self = this;
                var out = [];
                self.order.forEach(function (key) {
                    var tasks = self.items.filter(function (t) {
                        return (t.group || 'anytime') === key;
                    });
                    if (!tasks.length) return;
                    var meta = self.periods[key] || { label: key, icon: 'bi-clock', color: '#64748b' };
                    out.push({ key: key, label: meta.label, icon: meta.icon, color: meta.color, tasks: tasks });
                });
                return out;
            },
        },
        template: [
            '<div v-if="!openCount" v-html="emptyHtml"></div>',
            '<div v-for="g in groups" :key="g.key" class="pl-period-group" :data-period-group="g.key">',
            '  <div class="pl-period-head"><i :class="\'bi \' + g.icon" :style="{ color: g.color }"></i><span>{{ g.label }}</span></div>',
            '  <div class="pl-period-body" :data-period-body="g.key">',
            '    <div v-for="t in g.tasks" :key="t.id" class="pl-vue-row" v-html="t.html"></div>',
            '  </div>',
            '</div>',
        ].join('\n'),
    });

    /* Bridge used by the legacy vanilla flows (quick-add, backlog add,
       postpone, unschedule, undo-delete, drag&drop). Every mutation goes
       through the mounted instance so the template actually re-renders,
       then refreshes counters / day-progress / next-up after Vue flushes. */
    window.PlannerDay = {
        mounted: false,
        hasOpen: function () {
            return liveItems().some(function (t) { return !t.done; });
        },
        addTask: function (payload) {
            if (!vm || !payload || payload.id === undefined || !payload.html) return false;
            var ix = findIndex(payload.id);
            if (ix >= 0) vm.items.splice(ix, 1);
            vm.items.push({
                id: payload.id,
                group: payload.group || 'anytime',
                html: String(payload.html),
                done: false,
            });
            window.Vue.nextTick(function () {
                /* Verify the row really landed in the DOM. If Vue's render
                   didn't take for any reason, force a plain DOM insert so
                   the user never has to refresh to see the new task. */
                var node = mountEl.querySelector('[data-task-item][data-id="' + payload.id + '"]');
                if (!node) {
                    try {
                        var gb = (window.plEnsureGroup && window.plEnsureGroup(payload.group || 'anytime')) || null;
                        if (gb) {
                            var wrap = document.createElement('div');
                            wrap.innerHTML = String(payload.html).trim();
                            node = wrap.firstElementChild;
                            if (node) gb.appendChild(node);
                        } else {
                            mountEl.insertAdjacentHTML('afterbegin', String(payload.html).trim());
                            node = mountEl.querySelector('[data-task-item][data-id="' + payload.id + '"]');
                        }
                    } catch (_) {
                        node = null;
                    }
                }
                refreshChrome();
                flashRow(payload.id);
            });
            return true;
        },
        /* Debounced DOM←store reconciliation: runs once after a burst of sync
           mutations (e.g. pull-to-today = removeTask immediately followed by
           addTask), after Vue flushed, so intermediate states never fight the
           render. Any row in the box that the store doesn't know is removed. */
        scheduleVerify: function () {
            if (verifyTimer) return;
            verifyTimer = setTimeout(function () {
                verifyTimer = null;
                window.Vue.nextTick(function () {
                    var known = {};
                    liveItems().forEach(function (t) { known[String(t.id)] = true; });
                    mountEl.querySelectorAll('[data-task-item]').forEach(function (row) {
                        if (!Object.prototype.hasOwnProperty.call(known, String(row.getAttribute('data-id')))) {
                            row.remove();
                        }
                    });
                    refreshChrome();
                });
            }, 0);
        },
        removeTask: function (id) {
            if (!vm) return false;
            var ix = findIndex(id);
            if (ix >= 0) vm.items.splice(ix, 1);
            this.scheduleVerify();
            return true;
        },
        /* Reflect a done/undone tick in state so the empty block appears as
           soon as zero open tasks remain (and hides again on reopen). */
        setCompleted: function (id, done) {
            if (!vm) return false;
            var ix = findIndex(id);
            if (ix < 0) return false;
            vm.items[ix].done = !!done;
            window.Vue.nextTick(refreshChrome);
            return true;
        },
        /* Re-reads group membership + order from the live DOM (used after the
           vanilla drag&drop moves nodes directly). State is aligned without
           touching row markup, so no visual jump occurs. */
        syncOrderFromDom: function () {
            if (!vm) return;
            var ordered = [];
            mountEl.querySelectorAll('[data-period-group]').forEach(function (g) {
                var key = g.getAttribute('data-period-group');
                g.querySelectorAll('[data-task-item]').forEach(function (row) {
                    var ix = findIndex(row.getAttribute('data-id'));
                    if (ix >= 0) {
                        vm.items[ix].group = key;
                        if (ordered.indexOf(vm.items[ix]) < 0) ordered.push(vm.items[ix]);
                    }
                });
            });
            liveItems().forEach(function (t) {
                if (ordered.indexOf(t) < 0) ordered.push(t);
            });
            vm.items.splice.apply(vm.items, [0, vm.items.length].concat(ordered));
        },
    };

    try {
        /* mount() returns the component public instance — all mutations must
           go through it (see REACTIVITY RULE above). */
        vm = app.mount(mountEl);
        window.PlannerDay.mounted = true;
        window.__PL_DAY_MOUNTED__ = true;
    } catch (e) {
        vm = null;
        window.PlannerDay.mounted = false;
        if (window.console && console.error) console.error('[PlannerDay] Vue mount failed, vanilla fallback active', e);
    }
})();
