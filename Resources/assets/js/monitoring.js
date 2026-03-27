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

    var pingCache = {
        _cache: {},
        _key: function (sLat, sLon, uLat, uLon) {
            return sLat + ',' + sLon + ',' + uLat + ',' + uLon;
        },
        get: function (sLat, sLon, uLat, uLon) {
            return this._cache[this._key(sLat, sLon, uLat, uLon)];
        },
        set: function (sLat, sLon, uLat, uLon, val) {
            this._cache[this._key(sLat, sLon, uLat, uLon)] = val;
        }
    };

    function calculateGeoPing(serverLat, serverLon, userLat, userLon) {
        if (!userLat || !userLon) return 999;

        var cachedPing = pingCache.get(serverLat, serverLon, userLat, userLon);
        if (cachedPing !== undefined) return cachedPing;

        var R = 6371e3;
        var a1 = serverLat * Math.PI / 180;
        var a2 = userLat * Math.PI / 180;
        var dt = (userLat - serverLat) * Math.PI / 180;
        var da = (userLon - serverLon) * Math.PI / 180;

        var a = Math.sin(dt / 2) * Math.sin(dt / 2) +
            Math.cos(a1) * Math.cos(a2) *
            Math.sin(da / 2) * Math.sin(da / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        var distanceInMeters = R * c;
        var ping = Math.floor(((1.92 * (distanceInMeters / 1000)) / 100));

        pingCache.set(serverLat, serverLon, userLat, userLon, ping);
        return ping;
    }

    function getUserGeo() {
        var container = document.querySelector('.monitoring-container[data-user-lat]');
        if (!container) return null;
        var lat = parseFloat(container.dataset.userLat);
        var lon = parseFloat(container.dataset.userLon);
        if (isNaN(lat) || isNaN(lon)) return null;
        return { lat: lat, lon: lon };
    }

    function initializeServerPing() {
        var els = document.querySelectorAll('[data-server-ping]:not([data-ping-loaded])');
        if (!els.length) return;

        var user = getUserGeo();
        if (!user) return;

        var idx = 0;

        els.forEach(function (el) {
            var serverLat = parseFloat(el.dataset.serverLat);
            var serverLon = parseFloat(el.dataset.serverLon);

            if (isNaN(serverLat) || isNaN(serverLon)) return;

            var ping = calculateGeoPing(serverLat, serverLon, user.lat, user.lon);

            (function (element, value, delay) {
                setTimeout(function () {
                    element.dataset.pingLoaded = '1';
                    showPing(element, value);
                }, delay);
            })(el, ping, idx * 80);

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
            document.querySelectorAll('[data-server-ping]').forEach(function (el) {
                delete el.dataset.pingLoaded;
            });

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
