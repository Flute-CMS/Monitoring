(function () {
    if (window._monitoringInitialized) {
        if (typeof window._monitoringRunInit === 'function') {
            window._monitoringRunInit();
        }
        return;
    }
    window._monitoringInitialized = true;

    function initializePlayerSearch() {
        var searchInputs = document.querySelectorAll('.server-details-players-search-input:not([data-search-bound])');

        searchInputs.forEach(function (searchInput) {
            var container = searchInput.closest('.server-modal-players') || searchInput.closest('.server-modal-split');
            if (!container) return;

            var tableBodyElement = container.querySelector('[id^="playerTableBody-"]');
            if (!tableBodyElement) return;

            var serverId = tableBodyElement.id.split('-')[1];
            var playerRows = container.querySelectorAll('.player-row');
            var tableBody = document.getElementById('playerTableBody-' + serverId);
            var noPlayersFound = document.getElementById('noPlayersFound-' + serverId);

            if (!tableBody || !noPlayersFound) return;

            searchInput.dataset.searchBound = '1';
            searchInput.addEventListener('input', function () {
                filterPlayers(playerRows, this.value.toLowerCase(), tableBody, noPlayersFound);
            });
        });
    }

    function filterPlayers(playerRows, searchTerm, tableBody, noPlayersFound, teamFilter) {
        var foundPlayers = false;

        playerRows.forEach(function (row) {
            var nameEl = row.querySelector('.player-name');
            if (!nameEl) return;
            var name = nameEl.textContent.toLowerCase();
            var matchSearch = name.indexOf(searchTerm) !== -1;
            var matchTeam = !teamFilter || teamFilter === 'all' || row.dataset.team === teamFilter;

            if (matchSearch && matchTeam) {
                row.style.display = '';
                foundPlayers = true;
            } else {
                row.style.display = 'none';
            }
        });

        tableBody.parentElement.style.display = foundPlayers ? '' : 'none';
        noPlayersFound.style.display = foundPlayers ? 'none' : '';
    }

    var pingState = { fetched: false, fetching: false, data: {} };

    function measureRtt(callback) {
        var samples = [];
        var count = 0;
        var total = 3;
        var rttUrl = (document.querySelector('meta[name="site_url"]') || {}).content || '/';
        rttUrl = rttUrl.replace(/\/$/, '') + '/favicon.ico';

        function sample() {
            var t = performance.now();
            fetch(rttUrl, { method: 'HEAD', cache: 'no-store', mode: 'no-cors' })
                .then(function () { samples.push(Math.round(performance.now() - t)); })
                .catch(function () { samples.push(Math.round(performance.now() - t)); })
                .finally(function () {
                    count++;
                    if (count < total) {
                        sample();
                    } else {
                        if (!samples.length) { callback(0); return; }
                        samples.sort(function (a, b) { return a - b; });
                        callback(samples[0]);
                    }
                });
        }
        sample();
    }

    function initializeServerPing() {
        if (typeof u !== 'function') return;

        var els = document.querySelectorAll('[data-server-ping]:not([data-ping-loaded])');
        if (!els.length) return;

        if (pingState.fetched) {
            applyPingToElements(els);
            return;
        }

        if (pingState.fetching) return;
        pingState.fetching = true;

        measureRtt(function (clientRtt) {
            fetch(u('api/monitoring/pings'), { method: 'GET', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (raw) {
                    pingState.fetching = false;
                    pingState.data = {};

                    var keys = Object.keys(raw || {});
                    if (!keys.length) { pingState.fetched = false; return; }

                    pingState.fetched = true;
                    for (var i = 0; i < keys.length; i++) {
                        var v = raw[keys[i]];
                        pingState.data[keys[i]] = (v !== undefined && v !== -1) ? v + clientRtt : -1;
                    }

                    applyPingToElements(
                        document.querySelectorAll('[data-server-ping]:not([data-ping-loaded])')
                    );
                })
                .catch(function () {
                    pingState.fetching = false;
                    document.querySelectorAll('[data-server-ping]:not([data-ping-loaded])').forEach(function (el) {
                        el.dataset.pingLoaded = '1';
                        showPing(el, null);
                    });
                });
        });
    }

    function applyPingToElements(elements) {
        var idx = 0;
        elements.forEach(function (el) {
            var id = el.dataset.serverPing;
            var p = pingState.data[id];
            var val = (p !== undefined && p !== -1) ? p : null;

            (function (element, value, delay) {
                setTimeout(function () {
                    element.dataset.pingLoaded = '1';
                    showPing(element, value);
                }, delay);
            })(el, val, idx * 80);

            idx++;
        });
    }

    function showPing(el, ping) {
        if (ping === null) {
            el.innerHTML = '<span class="server-ping-value offline">\u2014</span>';
            return;
        }
        var cls = ping < 50 ? 'good' : (ping < 100 ? 'medium' : 'bad');
        el.innerHTML = '<span class="server-ping-value ' + cls + '">~' + ping + ' ms</span>';
        el.setAttribute('data-tooltip', 'Ping: ~' + ping + ' ms');
    }

    function runInit() {
        initializePlayerSearch();
        initializeServerPing();
    }

    window._monitoringRunInit = runInit;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runInit);
    } else {
        runInit();
    }

    setTimeout(runInit, 500);
    setTimeout(runInit, 2000);

    document.body.addEventListener('htmx:afterSettle', function (evt) {
        if (evt.target && evt.target.matches && evt.target.matches('#main')) {
            pingState.fetched = false;
            pingState.fetching = false;
            pingState.data = {};

            document.querySelectorAll('.server-details-modal').forEach(function (m) {
                if (typeof closeModal === 'function') closeModal(m.id);
            });
        }

        runInit();
    });

    document.body.addEventListener('htmx:afterSwap', function () {
        runInit();
    });

    document.body.addEventListener('modalContentLoaded', function () {
        runInit();
    });

    var observer = new MutationObserver(function () {
        var unloadedPing = document.querySelectorAll('[data-server-ping]:not([data-ping-loaded])');
        var unboundSearch = document.querySelectorAll('.server-details-players-search-input:not([data-search-bound])');
        if (unloadedPing.length || unboundSearch.length) runInit();
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
