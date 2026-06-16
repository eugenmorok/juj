const applyTheme = (theme) => {
    const normalizedTheme = theme === 'light' ? 'light' : 'dark';

    document.documentElement.dataset.theme = normalizedTheme;

    try {
        localStorage.setItem('rpg-arena-theme', normalizedTheme);
    } catch {
        // Theme still applies for the current page even when storage is unavailable.
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = normalizedTheme === 'light' ? 'Светлая' : 'Тёмная';

        button.setAttribute('aria-pressed', normalizedTheme === 'light' ? 'true' : 'false');
        button.querySelector('[data-theme-toggle-label]').textContent = label;
    });
};

const battleMarker = (state) => [
    state.status,
    state.current_round,
    state.latest_event_id ?? '',
    state.active_round?.actions_count ?? '',
    state.active_round?.own_action_id ?? '',
].join('|');

const formatCountdown = (deadline) => {
    if (!deadline) {
        return '00:00';
    }

    const remainingMs = new Date(deadline).getTime() - Date.now();
    const remainingSeconds = Math.max(0, Math.ceil(remainingMs / 1000));
    const minutes = Math.floor(remainingSeconds / 60).toString().padStart(2, '0');
    const seconds = (remainingSeconds % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
};

const setupBattlePolling = () => {
    const container = document.querySelector('[data-battle-poll]');

    if (!container) {
        return;
    }

    const stateUrl = container.dataset.battleStateUrl;
    const countdown = document.querySelector('[data-battle-countdown]');

    if (!stateUrl) {
        return;
    }

    let currentMarker = container.dataset.battleMarker || '';
    let deadline = container.dataset.battleDeadline || '';
    let pollInFlight = false;

    const updateCountdown = () => {
        if (countdown) {
            countdown.textContent = formatCountdown(deadline);
        }
    };

    const poll = async () => {
        if (pollInFlight) {
            return;
        }

        pollInFlight = true;

        try {
            const response = await fetch(stateUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                return;
            }

            const state = await response.json();
            const nextMarker = battleMarker(state);

            deadline = state.active_round?.deadline_at || state.turn_deadline_at || deadline;
            updateCountdown();

            if (nextMarker !== currentMarker) {
                window.location.reload();
                return;
            }
        } catch {
            // Polling is best-effort; manual refresh still works.
        } finally {
            pollInFlight = false;
        }
    };

    updateCountdown();
    window.setInterval(updateCountdown, 250);
    window.setInterval(poll, 2000);
};

document.addEventListener('DOMContentLoaded', () => {
    let savedTheme = document.documentElement.dataset.theme;

    try {
        savedTheme = localStorage.getItem('rpg-arena-theme') || savedTheme;
    } catch {
        savedTheme = savedTheme || 'dark';
    }

    applyTheme(savedTheme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            applyTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light');
        });
    });

    setupBattlePolling();
});
