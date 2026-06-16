/**
 * telescope-ext.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Telescope User Column Extender
 *
 * Adds a "User" column to the Requests list page showing the authenticated
 * user's name and role — without modifying any Telescope vendor files.
 *
 * Strategy:
 *  1. Intercept XHR calls to /telescope-api/requests to cache entry tags.
 *  2. Use MutationObserver to watch for table rows rendered by Vue.
 *  3. For each new row, extract the entry ID from the detail <a> href,
 *     look up the cached tags, and inject a <td> after the Path column.
 *  4. Also inject a matching <th> into the thead on first render.
 *
 * Survives Telescope version updates — no vendor files touched.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    // ── Tag Cache ────────────────────────────────────────────────────────────
    // { "uuid": { user: "Muhammad Fayiz", role: "student" } }
    const tagCache = {};

    // ── XHR Interceptor ──────────────────────────────────────────────────────
    // Hooks into every XHR response from /telescope-api/requests and fills
    // tagCache with { user, role } extracted from the entry tags array.
    const _open = XMLHttpRequest.prototype.open;
    const _send = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
        this._telescopeUrl = url;
        return _open.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function () {
        this.addEventListener('load', function () {
            if (!this._telescopeUrl) return;
            if (!this._telescopeUrl.includes('telescope-api/requests')) return;
            if (this.status !== 200) return;

            try {
                const json = JSON.parse(this.responseText);
                const entries = json.entries || [];

                entries.forEach(function (entry) {
                    const tags = entry.tags || [];

                    // User name: any tag that is NOT "Auth:ID" and NOT "role:*"
                    const userTag = tags.find(function (t) {
                        return !t.startsWith('Auth:') && !t.startsWith('role:') && t !== 'guest';
                    }) || null;

                    const roleTag = tags.find(function (t) {
                        return t.startsWith('role:');
                    });

                    const isGuest = tags.includes('guest');

                    tagCache[entry.id] = {
                        user: userTag,
                        role: roleTag ? roleTag.replace('role:', '') : null,
                        guest: isGuest,
                    };
                });
            } catch (_) {
                // Silently ignore parse errors
            }
        });

        return _send.apply(this, arguments);
    };

    // ── DOM Helpers ──────────────────────────────────────────────────────────

    /**
     * Build the <td> cell content for a given entry ID.
     */
    function buildUserCell(id) {
        const td = document.createElement('td');
        td.setAttribute('data-tele-user', '1');
        td.style.cssText = 'white-space:nowrap; vertical-align:middle;';

        const info = tagCache[id];

        if (!info || info.guest) {
            const dash = document.createElement('span');
            dash.style.cssText = 'font-size:11px; color:#9ca3af;';
            dash.textContent = info && info.guest ? 'guest' : '—';
            td.appendChild(dash);
            return td;
        }

        if (info.user) {
            const nameSpan = document.createElement('span');
            nameSpan.style.cssText = 'font-size:11px; font-weight:600; color:#374151;';
            nameSpan.textContent = info.user;
            td.appendChild(nameSpan);
        }

        if (info.role) {
            const roleSpan = document.createElement('span');
            roleSpan.style.cssText = [
                'margin-left:5px',
                'font-size:10px',
                'background:#e0f2fe',
                'color:#0369a1',
                'padding:1px 7px',
                'border-radius:999px',
                'font-weight:600',
                'display:inline-block',
            ].join(';');
            roleSpan.textContent = info.role;
            td.appendChild(roleSpan);
        }

        return td;
    }

    /**
     * Inject a User <th> into the table header (idempotent).
     */
    function injectHeader(thead) {
        const headerRow = thead && thead.querySelector('tr');
        if (!headerRow) return;
        if (headerRow.querySelector('th[data-tele-user]')) return; // Already injected

        const pathTh = headerRow.querySelectorAll('th')[1]; // "Path" is the 2nd <th>
        if (!pathTh) return;

        const th = document.createElement('th');
        th.setAttribute('data-tele-user', '1');
        th.scope = 'col';
        th.style.cssText = 'white-space:nowrap; font-size:12px;';
        th.textContent = 'User';

        // Insert after Path column
        headerRow.insertBefore(th, pathTh.nextSibling);
    }

    /**
     * Inject a User <td> into a single table row (idempotent).
     */
    function injectRowCell(row) {
        if (row.querySelector('td[data-tele-user]')) return; // Already done

        // The detail arrow link href is "#/requests/{uuid}"
        const link = row.querySelector('a[href*="/requests/"]');
        if (!link) return;

        const match = (link.getAttribute('href') || '').match(/\/requests\/([a-f0-9-]{36})/);
        if (!match) return;

        const id = match[1];

        const pathCell = row.querySelectorAll('td')[1]; // Path is 2nd <td>
        if (!pathCell) return;

        const td = buildUserCell(id);
        row.insertBefore(td, pathCell.nextSibling);
    }

    /**
     * Process an entire table — header + all rows.
     */
    function processTable(table) {
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');

        if (thead) injectHeader(thead);

        if (tbody) {
            tbody.querySelectorAll('tr').forEach(injectRowCell);
        }
    }

    /**
     * Check whether we are currently on the Requests LIST page
     * (not a detail page like /requests/uuid).
     */
    function isRequestsListPage() {
        const hash = window.location.hash || '';
        // List page: "#/requests" or "#/requests?" (paginated)
        // Detail page: "#/requests/some-uuid"
        return /^#\/requests(\?|$)/.test(hash) || hash === '#/requests';
    }

    /**
     * Scan all visible tables and patch them if on the requests list.
     */
    function patchVisibleTables() {
        if (!isRequestsListPage()) return;

        document.querySelectorAll('table').forEach(processTable);
    }

    // ── MutationObserver ─────────────────────────────────────────────────────
    // Watches the entire Telescope SPA container for DOM mutations so we
    // patch rows as Vue renders or updates them (pagination, auto-refresh).
    function startObserver() {
        const app = document.getElementById('telescope');
        if (!app) {
            // Telescope SPA not mounted yet — retry shortly
            setTimeout(startObserver, 200);
            return;
        }

        const observer = new MutationObserver(function () {
            patchVisibleTables();
        });

        observer.observe(app, { childList: true, subtree: true });

        // Also patch on hash changes (navigation between sections)
        window.addEventListener('hashchange', patchVisibleTables);

        // Initial patch in case the page is already rendered
        patchVisibleTables();
    }

    // ── Boot ─────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserver);
    } else {
        startObserver();
    }

}());
