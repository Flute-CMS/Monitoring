function initializePlayerSearch() {
    const serverDetailsModals = document.querySelectorAll('.server-details, .cs2-details');

    serverDetailsModals.forEach((modal) => {
        const searchInput = modal.querySelector(
            '.server-details-players-search-input',
        );
        if (!searchInput) return;

        const tableBodyElement = modal.querySelector(
            '[id^="playerTableBody-"]',
        );
        if (!tableBodyElement) return;

        const serverId = tableBodyElement.id.split('-')[1];
        const playerRows = modal.querySelectorAll('.player-row');
        const tableBody = document.getElementById(
            `playerTableBody-${serverId}`,
        );
        const noPlayersFound = document.getElementById(
            `noPlayersFound-${serverId}`,
        );

        if (!tableBody || !noPlayersFound) return;

        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            filterPlayers(playerRows, searchTerm, tableBody, noPlayersFound);
        });
    });
}

function filterPlayers(playerRows, searchTerm, tableBody, noPlayersFound, teamFilter = null) {
    let foundPlayers = false;
    let visibleCount = 0;

    playerRows.forEach((row) => {
        const playerName = row
            .querySelector('.player-name')
            .textContent.toLowerCase();

        const matchesSearch = playerName.includes(searchTerm);
        const matchesTeam = teamFilter === null || teamFilter === 'all' || row.dataset.team === teamFilter;

        if (matchesSearch && matchesTeam) {
            row.style.display = '';
            foundPlayers = true;
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (foundPlayers) {
        tableBody.parentElement.style.display = '';
        noPlayersFound.style.display = 'none';
    } else {
        tableBody.parentElement.style.display = 'none';
        noPlayersFound.style.display = '';
    }

    return visibleCount;
}

function initializeAll() {
    initializePlayerSearch();
}

initializeAll();

document.body.addEventListener('htmx:afterSwap', function (evt) {
    initializeAll();

    if (evt.target.matches('#main')) {
        document.querySelectorAll('.server-details-modal').forEach((modal) => {
            closeModal(modal.id);
        });
    }
});
