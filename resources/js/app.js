import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const applyTheme = (theme) => {
    const normalizedTheme = theme === 'light' ? 'light' : 'dark';

    document.documentElement.dataset.theme = normalizedTheme;

    try {
        localStorage.setItem('rpg-arena-theme', normalizedTheme);
    } catch {
        // Theme still applies for the current page even when storage is unavailable.
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = normalizedTheme === 'light' ? 'Светлая' : 'Темная';

        button.setAttribute('aria-pressed', normalizedTheme === 'light' ? 'true' : 'false');
        button.querySelector('[data-theme-toggle-label]').textContent = label;
    });
};

const battleMarker = (state) => [
    state.status,
    state.current_round,
    state.latest_event_id ?? '',
    state.latest_message_id ?? '',
    state.active_round?.actions_count ?? '',
    state.active_round?.own_action_submitted ? 1 : 0,
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

let echoInstance = null;

const initializeEcho = () => {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const scheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '') || 'http';
    const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const port = Number(import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80));

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        },
    });

    return echoInstance;
};

const setupBattleRealtime = () => {
    const container = document.querySelector('[data-battle-poll]');

    if (!container) {
        return;
    }

    const stateUrl = container.dataset.battleStateUrl;
    const channelName = container.dataset.battleChannel;
    const statusBox = container.querySelector('[data-battle-live-status]');
    const actionPanel = container.querySelector('[data-battle-action-panel]');
    const participantsPanel = container.querySelector('[data-battle-participants]');
    const eventsPanel = container.querySelector('[data-battle-events]');
    const chatPanel = container.querySelector('[data-battle-chat]');
    const visualizerElement = container.querySelector('[data-battle-visualizer]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    if (!stateUrl || !actionPanel || !participantsPanel || !eventsPanel || !chatPanel) {
        return;
    }

    let currentMarker = container.dataset.battleMarker || '';
    let deadline = container.dataset.battleDeadline || '';
    let requestInFlight = false;
    let submitInFlight = false;
    let chatInFlight = false;
    let channel = null;
    let pollTimer = null;
    let countdownTimer = null;

    const setStatus = (message, tone = 'info') => {
        if (!statusBox) {
            return;
        }

        const tones = {
            info: 'border-sky-500/40 bg-sky-500/10 text-sky-100',
            success: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
            error: 'border-rose-500/40 bg-rose-500/10 text-rose-100',
        };

        statusBox.className = `rounded-md border px-4 py-3 text-sm ${tones[tone] ?? tones.info}`;
        statusBox.textContent = message;
        statusBox.classList.remove('hidden');
    };

    const clearStatus = () => {
        if (statusBox) {
            statusBox.classList.add('hidden');
            statusBox.textContent = '';
        }
    };

    const updateCountdown = () => {
        container.querySelectorAll('[data-battle-countdown]').forEach((node) => {
            node.textContent = formatCountdown(deadline);
        });
    };

    const applyState = (state, replaceFragments = false) => {
        currentMarker = state.marker || battleMarker(state);
        deadline = state.active_round?.deadline_at || state.turn_deadline_at || '';

        container.dataset.battleMarker = currentMarker;
        container.dataset.battleDeadline = deadline;

        if (replaceFragments && state.fragments) {
            actionPanel.innerHTML = state.fragments.action_panel_html ?? '';
            participantsPanel.innerHTML = state.fragments.participants_html ?? '';
            eventsPanel.innerHTML = state.fragments.events_html ?? '';
            chatPanel.innerHTML = state.fragments.chat_html ?? '';
        }

        visualizerElement?.battleVisualizer?.applyState(state);
        updateCountdown();

        if (state.status !== 'running' && pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    const requestState = async (includeFragments = false) => {
        if (requestInFlight) {
            return null;
        }

        requestInFlight = true;

        try {
            const url = new URL(stateUrl, window.location.origin);

            if (includeFragments) {
                url.searchParams.set('include_fragments', '1');
            }

            const afterEventId = Number(visualizerElement?.dataset.battleLatestEventId || 0);

            if (afterEventId > 0) {
                url.searchParams.set('after_event_id', String(afterEventId));
            }

            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return null;
            }

            return await response.json();
        } catch {
            return null;
        } finally {
            requestInFlight = false;
        }
    };

    const refreshBattle = async (includeFragments = false) => {
        const state = await requestState(includeFragments);

        if (!state) {
            return null;
        }

        applyState(state, includeFragments);

        return state;
    };

    const poll = async () => {
        const previousMarker = currentMarker;
        const state = await refreshBattle(false);

        if (!state) {
            return;
        }

        if ((state.marker || battleMarker(state)) !== previousMarker) {
            await refreshBattle(true);
        }
    };

    const firstError = (payload) => {
        if (!payload || typeof payload !== 'object') {
            return 'Не удалось выполнить действие. Попробуйте еще раз.';
        }

        if (typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message;
        }

        const errors = payload.errors ?? {};
        const firstEntry = Object.values(errors)[0];

        if (Array.isArray(firstEntry) && firstEntry[0]) {
            return firstEntry[0];
        }

        return 'Не удалось выполнить действие. Попробуйте еще раз.';
    };

    container.addEventListener('submit', async (event) => {
        const chatForm = event.target.closest('[data-battle-chat-form]');

        if (chatForm) {
            event.preventDefault();

            if (chatInFlight) {
                return;
            }

            chatInFlight = true;

            const submitButton = chatForm.querySelector('button[type="submit"]');
            const input = chatForm.querySelector('[data-battle-chat-input]');
            const errorBox = chatPanel.querySelector('[data-battle-chat-error]');

            if (errorBox) {
                errorBox.classList.add('hidden');
                errorBox.textContent = '';
            }

            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const formData = new FormData(chatForm);
                formData.append('after_event_id', visualizerElement?.dataset.battleLatestEventId || '0');
                const response = await fetch(chatForm.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    if (errorBox) {
                        errorBox.textContent = firstError(payload);
                        errorBox.classList.remove('hidden');
                    } else {
                        setStatus(firstError(payload), 'error');
                    }

                    return;
                }

                applyState(payload, true);

                const freshInput = chatPanel.querySelector('[data-battle-chat-input]') || input;
                if (freshInput) {
                    freshInput.value = '';
                    freshInput.focus();
                }
            } catch {
                if (errorBox) {
                    errorBox.textContent = 'Не удалось отправить сообщение. Попробуйте еще раз.';
                    errorBox.classList.remove('hidden');
                } else {
                    setStatus('Не удалось отправить сообщение. Попробуйте еще раз.', 'error');
                }
            } finally {
                chatInFlight = false;

                if (submitButton) {
                    submitButton.disabled = false;
                }
            }

            return;
        }

        const form = event.target.closest('[data-battle-action-form]');

        if (!form) {
            return;
        }

        event.preventDefault();

        if (submitInFlight) {
            return;
        }

        submitInFlight = true;
        clearStatus();

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const formData = new FormData(form);
            formData.append('after_event_id', visualizerElement?.dataset.battleLatestEventId || '0');
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                setStatus(firstError(payload), 'error');
                await refreshBattle(true);

                return;
            }

            applyState(payload, true);
            setStatus(payload.message || 'Тактика шага принята.', 'success');
        } catch {
            setStatus('Не удалось отправить тактику. Попробуйте еще раз.', 'error');
        } finally {
            submitInFlight = false;

            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

    const echo = initializeEcho();

    if (echo && channelName) {
        channel = echo.private(channelName);
        channel.listen('.battle.state.updated', async () => {
            await refreshBattle(true);
        });
    }

    updateCountdown();
    countdownTimer = window.setInterval(updateCountdown, 250);
    pollTimer = window.setInterval(poll, 2000);

    window.addEventListener('beforeunload', () => {
        if (countdownTimer) {
            window.clearInterval(countdownTimer);
        }

        if (pollTimer) {
            window.clearInterval(pollTimer);
        }

        if (echo && channelName) {
            echo.leave(channelName);
        }

        visualizerElement?.battleVisualizer?.destroy();
    }, { once: true });
};

document.addEventListener('DOMContentLoaded', async () => {
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

    if (document.querySelector('[data-battle-visualizer]')) {
        const { setupBattleVisualizer } = await import('./battle-visualizer');
        await setupBattleVisualizer();
    }

    setupBattleRealtime();
});
