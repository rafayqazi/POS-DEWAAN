/* Instant Navigation (SPA) for POS-DEWAAN
 * Intercepts internal link clicks, fetches the target page, swaps only the
 * #appContent region, re-runs page scripts (incl. DOMContentLoaded handlers),
 * pushes history state and prefetches sidebar pages in the background.
 *
 * Toggle:  window.spaToggle() / spaOn() / spaOff()  or the sidebar button.
 * State:    localStorage 'spa_enabled'  ('0' = classic full reloads).
 * Fallback: any fetch error / missing #appContent -> classic navigation.
 */
(function () {
    'use strict';

    var KEY = 'spa_enabled';
    var TTL = 5 * 60 * 1000;                 // cache lifetime (5 min)
    var ORIG_ADD = document.addEventListener.bind(document);
    var ORIG_REMOVE = document.removeEventListener.bind(document);

    var cache = {};                          // key -> { content, card, title, ts }
    var pageListeners = [];                  // document-level listeners of current page
    var loadedExtScripts = {};               // external src already injected this session
    var barEl = null;

    /* ------------------------- enabled state ------------------------- */

    function enabled() {
        try { return localStorage.getItem(KEY) !== '0'; } catch (e) { return true; }
    }

    function setEnabled(on) {
        try { localStorage.setItem(KEY, on ? '1' : '0'); } catch (e) {}
    }

    window.spaToggle = function () { setEnabled(!enabled()); location.reload(); };
    window.spaOn = function () { setEnabled(true); location.reload(); };
    window.spaOff = function () { setEnabled(false); location.reload(); };
    window.spaState = function () { return enabled() ? 'on' : 'off'; };
    window.spaCache = function () { return Object.keys(cache).map(function (k) { return k + ' (' + Math.round((Date.now() - cache[k].ts) / 1000) + 's old)'; }); };

    function paintToggle() {
        var d = document.getElementById('spaToggleDot');
        if (!d) return;
        d.textContent = enabled() ? 'ON' : 'OFF';
        d.className = 'ml-2 text-[10px] px-2 py-0.5 rounded-full sidebar-text ' +
            (enabled() ? 'bg-teal-500/20 text-teal-300' : 'bg-gray-500/20 text-gray-400');
    }

    /* ------------------------- progress bar ------------------------- */

    function progress(state) {
        barEl = barEl || document.getElementById('spaProgress');
        if (!barEl) return;
        if (state === 'start') {
            barEl.style.opacity = '1';
            barEl.style.width = '70%';
            setTimeout(function () { if (barEl) barEl.style.width = '92%'; }, 400);
        } else {
            barEl.style.width = '100%';
            setTimeout(function () { if (barEl) { barEl.style.opacity = '0'; barEl.style.width = '0'; } }, 300);
        }
    }

    /* ------------------------- navigation rules ------------------------- */

    function isSpaLink(a) {
        if (!a || !enabled()) return false;
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('download')) return false;
        if (a.getAttribute('data-no-spa') !== null) return false;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) return false;
        var u;
        try { u = new URL(a.href, location.href); } catch (e) { return false; }
        if (u.origin !== location.origin) return false;
        var p = u.pathname;
        if (/logout\.php$/i.test(p) || /login\.php$/i.test(p)) return false;
        if (/print_/i.test(p)) return false;
        if (/\/actions\//i.test(p)) return false;
        if (!/\.php$/i.test(p)) return false;
        return true;
    }

    function keyOf(url) {
        return url.pathname + url.search;
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || !isSpaLink(a)) return;
        e.preventDefault();
        navigate(new URL(a.href, location.href), true);
    }, true);

    /* ------------------------- swap pipeline ------------------------- */

    function navigate(url, push) {
        var key = keyOf(url);
        var hit = cache[key];
        if (hit && Date.now() - hit.ts < TTL) {
            apply(hit, key, push);
            return;
        }
        progress('start');
        fetch(url.href, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-SPA': '1' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) {
                var parsed = parsePage(html);
                if (!parsed) { window.location.href = url.href; return; }
                cache[key] = parsed;
                apply(parsed, key, push);
            })
            .catch(function () { window.location.href = url.href; })
            .then(function () { progress('done'); });
    }

    function parsePage(html) {
        var doc;
        try { doc = new DOMParser().parseFromString(html, 'text/html'); } catch (e) { return null; }
        var box = doc.getElementById('appContent');
        if (!box) return null;
        var title = doc.querySelector('title');
        var card = doc.getElementById('appPageTitle');
        return {
            content: box.innerHTML,
            title: title ? title.textContent : null,
            card: card ? card.textContent.trim() : null
        };
    }

    function apply(entry, key, push) {
        var box = document.getElementById('appContent');
        if (!box) { window.location.href = location.origin + key; return; }
        cleanupListeners();
        box.innerHTML = entry.content;
        execScripts(box);

        var t = document.getElementById('appPageTitle');
        if (t && entry.card) t.textContent = entry.card;
        if (entry.title) document.title = entry.title;
        updateSidebar(key);

        var scroller = document.querySelector('main');
        if (scroller) scroller.scrollTop = 0;
        window.scrollTo(0, 0);
        if (document.activeElement && document.activeElement.blur) document.activeElement.blur();

        if (push) history.pushState({ spa: key }, '', key);
        window.dispatchEvent(new CustomEvent('spa:loaded', { detail: { url: key } }));
    }

    window.addEventListener('popstate', function (e) {
        var key = e.state && e.state.spa;
        if (!key) return;                    // non-SPA history entry -> native
        var hit = cache[key];
        if (hit && Date.now() - hit.ts < TTL) { apply(hit, key, false); return; }
        progress('start');
        fetch(location.origin + key, { headers: { 'X-SPA': '1' } })
            .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
            .then(function (html) {
                var parsed = parsePage(html);
                if (!parsed) { window.location.href = location.origin + key; return; }
                cache[key] = parsed;
                apply(parsed, key, false);
            })
            .catch(function () { window.location.href = location.origin + key; })
            .then(function () { progress('done'); });
    });

    /* ------------------------- script execution ------------------------- */

    function cleanupListeners() {
        pageListeners.forEach(function (l) {
            try { ORIG_REMOVE(l.t, l.f, l.o); } catch (e) {}
        });
        pageListeners = [];
    }

    function execScripts(container) {
        var inline = [];
        var external = [];
        container.querySelectorAll('script').forEach(function (s) {
            if (s.src) { external.push(s.src); }
            else if (s.textContent.trim()) { inline.push(s.textContent); }
            s.remove();
        });

        var pendingDcl = [];

        /* During page-script execution, route document-level registrations
         * through our registry so the next swap can unbind them. */
        document.addEventListener = function (type, fn, opt) {
            if (type === 'DOMContentLoaded') { pendingDcl.push(fn); return; }
            pageListeners.push({ t: type, f: fn, o: opt });
            return ORIG_ADD(type, fn, opt);
        };
        document.removeEventListener = function (type, fn, opt) {
            if (type === 'DOMContentLoaded') {
                var i = pendingDcl.indexOf(fn);
                if (i > -1) pendingDcl.splice(i, 1);
                return;
            }
            return ORIG_REMOVE(type, fn, opt);
        };

        inline.forEach(function (code) {
            try {
                var s = document.createElement('script');
                s.textContent = code;
                document.body.appendChild(s);
                s.remove();
            } catch (e) {
                console.error('SPA script error:', e);
            }
        });

        external.forEach(function (src) {
            if (loadedExtScripts[src]) return;
            loadedExtScripts[src] = true;
            var s = document.createElement('script');
            s.src = src;
            document.body.appendChild(s);
        });

        document.addEventListener = ORIG_ADD;
        document.removeEventListener = ORIG_REMOVE;

        pendingDcl.forEach(function (fn) {
            try { fn.call(document); } catch (e) { console.error('SPA init error:', e); }
        });

        /* Pages that init via window.onload (check_inventory, edit_sale). */
        window.dispatchEvent(new Event('load'));
    }

    /* ------------------------- sidebar highlight ------------------------- */

    function updateSidebar(key) {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        sidebar.querySelectorAll('a[href]').forEach(function (a) {
            if (!a.href) return;
            var u = new URL(a.href, location.href);
            var k = keyOf(u);
            if (/\/index\.php$/i.test(k)) k = k.replace(/\/index\.php$/i, '');
            if (/\/index\.php$/i.test(key)) key = key.replace(/\/index\.php$/i, '');
            var active = k === key;
            a.classList.toggle('bg-teal-800', active);
            a.classList.toggle('border-r-4', active);
            a.classList.toggle('border-accent', active);
        });
    }

    /* ------------------------- idle prefetch ------------------------- */

    function prefetchAll() {
        if (!enabled()) return;
        document.querySelectorAll('#sidebar a[href]').forEach(function (a) {
            if (!isSpaLink(a)) return;
            var u = new URL(a.href, location.href);
            var key = keyOf(u);
            if (cache[key]) return;
            fetch(u.href, { headers: { 'X-SPA': '1' } })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    if (!html) return;
                    var parsed = parsePage(html);
                    if (parsed) cache[key] = parsed;
                })
                .catch(function () {});
        });
    }

    /* ------------------------- boot ------------------------- */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            paintToggle();
            window.addEventListener('load', function () {
                setTimeout(function () { if (enabled()) prefetchAll(); }, 1500);
            });
        });
    } else {
        paintToggle();
        setTimeout(function () { if (enabled()) prefetchAll(); }, 1500);
    }
})();
