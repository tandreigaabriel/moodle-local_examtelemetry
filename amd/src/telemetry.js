// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy-minimised browser performance telemetry for quiz transitions.
 *
 * @module local_examtelemetry/telemetry
 * @copyright 2026 Andrei Toma
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const MAX_TEXT = 500;
const BATCH_SIZE = 10;
const FLUSH_DELAY = 1500;
const MAX_EVENTS_PER_PAGE = 100;

const sanitiseText = value => String(value ?? '')
    .replace(/sesskey=[^&\s]+/gi, 'sesskey=[redacted]')
    .replace(/\s+/g, ' ')
    .slice(0, MAX_TEXT);

const createSender = config => {
    let queue = [];
    let timer = null;
    let eventCount = 0;
    const recent = new Map();
    const nativeFetch = window.fetch.bind(window);

    const flush = useBeacon => {
        if (!queue.length) {
            return;
        }
        const events = queue.splice(0, BATCH_SIZE);
        const body = JSON.stringify({
            sesskey: config.sesskey,
            cmid: config.cmid,
            attemptid: config.attemptid,
            pagekind: config.pagekind,
            events,
        });
        if (useBeacon && navigator.sendBeacon) {
            const blob = new Blob([body], {type: 'application/json'});
            if (navigator.sendBeacon(config.endpoint, blob)) {
                return;
            }
        }
        nativeFetch(config.endpoint, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            keepalive: true,
            headers: {'Content-Type': 'application/json'},
        }).catch(() => {});
        if (queue.length) {
            timer = window.setTimeout(() => flush(false), FLUSH_DELAY);
        }
    };
    const send = (eventtype, durationms = null, details = {}, immediate = false) => {
        if (eventCount >= MAX_EVENTS_PER_PAGE) {
            return;
        }
        const now = Date.now();
        const signature = `${eventtype}:${Math.round(Number(durationms) || 0)}:${JSON.stringify(details)}`;
        if (recent.has(signature) && now - recent.get(signature) < 2000) {
            return;
        }
        recent.set(signature, now);
        eventCount++;
        queue.push({
            eventtype,
            durationms,
            timeclient: Math.floor(now / 1000),
            clientid: `${now.toString(36)}-${Math.random().toString(36).slice(2, 10)}`,
            details,
        });
        if (timer) {
            window.clearTimeout(timer);
        }
        if (immediate || queue.length >= BATCH_SIZE) {
            flush(immediate);
        } else {
            timer = window.setTimeout(() => flush(false), FLUSH_DELAY);
        }
    };
    window.addEventListener('pagehide', () => flush(true));
    return send;
};

const endpointDetails = rawUrl => {
    try {
        const url = new URL(String(rawUrl), window.location.href);
        if (!['http:', 'https:'].includes(url.protocol)) {
            return null;
        }
        return {
            category: url.origin === window.location.origin ? 'moodle' : 'external',
            host: sanitiseText(url.hostname).slice(0, 100),
            path: sanitiseText(url.pathname).slice(0, 160),
        };
    } catch (error) {
        return null;
    }
};

const captureRequestFailures = (config, send) => {
    if (!window.fetch) {
        return;
    }
    const nativeFetch = window.fetch.bind(window);
    window.fetch = async(...args) => {
        const requestUrl = args[0]?.url || args[0];
        const details = endpointDetails(requestUrl);
        const isCollector = details && new URL(config.endpoint, window.location.href).pathname === details.path;
        try {
            const response = await nativeFetch(...args);
            if (!isCollector && details && response.status >= 400) {
                send('http_failure', null, {...details, status: response.status});
            }
            return response;
        } catch (error) {
            if (!isCollector && details) {
                send('http_failure', null, {...details, status: 0});
            }
            throw error;
        }
    };
};

const captureEventLoopLag = (config, send) => {
    const interval = 5000;
    let expected = performance.now() + interval;
    let lastReported = 0;
    window.setInterval(() => {
        const now = performance.now();
        const lag = Math.max(0, now - expected);
        expected = now + interval;
        if (!document.hidden && lag >= config.delaythreshold && now - lastReported >= 30000) {
            lastReported = now;
            send('event_loop_lag', lag, {detector: 'interval'});
        }
    }, interval);
};

const captureIntegrationFailures = send => {
    window.addEventListener('examtelemetry:integration', event => {
        const detail = event.detail || {};
        const integration = sanitiseText(detail.integration).toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 50);
        const integrationEvent = sanitiseText(detail.event).toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 50);
        const reason = sanitiseText(detail.reason).toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 80);
        if (integration && ['initialisation_failed', 'connection_failed', 'required_service_unavailable']
                .includes(integrationEvent)) {
            send('integration_failure', null, {integration, event: integrationEvent, reason}, true);
        }
    });
};

const captureNavigation = send => {
    window.addEventListener('load', () => {
        window.setTimeout(() => {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (!navigation) {
                return;
            }
            send('navigation_timing', navigation.duration, {
                serverwaitms: Math.max(0, navigation.responseStart - navigation.requestStart),
                downloadms: Math.max(0, navigation.responseEnd - navigation.responseStart),
                renderms: Math.max(0, navigation.domContentLoadedEventEnd - navigation.responseEnd),
                loadms: Math.max(0, navigation.loadEventEnd - navigation.startTime),
            });
        }, 0);
    }, {once: true});
};

const capturePreviousTransition = (config, send) => {
    const key = `local_examtelemetry_transition_${config.attemptid}`;
    try {
        const previous = JSON.parse(sessionStorage.getItem(key) || 'null');
        if (previous && previous.started && previous.eventtype && config.pagekind === 'summary') {
            send('transition_timing', Math.max(0, Date.now() - previous.started), {
                sourceevent: previous.eventtype,
            });
            sessionStorage.removeItem(key);
        }
    } catch (error) {
        sessionStorage.removeItem(key);
    }
    return key;
};

const identifySubmissionAction = target => {
    const button = target.closest('button, input[type="submit"], a[role="button"]');
    if (!button) {
        return null;
    }
    const form = button.closest('form');
    const action = form?.getAttribute('action') || '';
    const name = button.getAttribute('name') || '';
    const actionName = button.dataset.action || '';
    if (action.includes('processattempt.php') && form.querySelector('[name="finishattempt"][value="1"]')) {
        return 'confirmation_dialog_opened';
    }
    if (action.includes('processattempt.php') && name === 'next' &&
            form.querySelector('[name="nextpage"][value="-1"]')) {
        return 'finish_attempt_clicked';
    }
    const text = button.tagName === 'INPUT' ? button.value : button.textContent;
    const signature = `${name} ${actionName} ${text} ${action}`.toLowerCase();

    if (signature.includes('submit all and finish') ||
            signature.includes('submitallandfinish') ||
            signature.includes('final submit')) {
        return 'confirmation_dialog_opened';
    }
    if (signature.includes('finish attempt') ||
            signature.includes('finishattempt')) {
        return 'finish_attempt_clicked';
    }
    return null;
};

export const init = config => {
    if (!config?.endpoint || !config?.sesskey || !config?.attemptid || !config?.cmid) {
        return;
    }

    const send = createSender(config);
    const transitionKey = capturePreviousTransition(config, send);
    send('telemetry_start', null, {
        online: navigator.onLine,
        viewportwidth: window.innerWidth,
        viewportheight: window.innerHeight,
    });

    captureNavigation(send);
    captureRequestFailures(config, send);
    captureEventLoopLag(config, send);
    captureIntegrationFailures(send);

    if ('PerformanceObserver' in window) {
        try {
            const observer = new PerformanceObserver(list => {
                list.getEntries().forEach(entry => {
                    if (entry.duration >= config.longtaskthreshold) {
                        send('long_task', entry.duration, {
                            startms: Math.round(entry.startTime),
                            name: sanitiseText(entry.name),
                        });
                    }
                });
            });
            observer.observe({entryTypes: ['longtask']});
        } catch (error) {
            // Long Tasks API is optional and unsupported browsers remain usable.
        }
    }

    window.addEventListener('error', event => {
        if (event.target && event.target !== window) {
            const details = endpointDetails(event.target.src || event.target.href);
            if (details) {
                send('resource_load_error', null, {
                    ...details,
                    element: sanitiseText(event.target.tagName).toLowerCase().slice(0, 20),
                });
            }
            return;
        }
        send('js_error', null, {
            message: sanitiseText(event.message),
            filename: sanitiseText(event.filename),
            line: Number(event.lineno || 0),
            column: Number(event.colno || 0),
        });
    }, true);

    document.addEventListener('securitypolicyviolation', event => {
        const details = endpointDetails(event.blockedURI) || {};
        send('csp_violation', null, {
            ...details,
            directive: sanitiseText(event.effectiveDirective).slice(0, 80),
        });
    });

    window.addEventListener('unhandledrejection', event => {
        send('unhandled_rejection', null, {
            message: sanitiseText(event.reason?.message || event.reason),
        });
    });

    window.addEventListener('offline', () => send('offline'));
    window.addEventListener('online', () => send('online'));
    if (navigator.connection?.addEventListener) {
        navigator.connection.addEventListener('change', () => send('network_change', null, {
            effective: sanitiseText(navigator.connection.effectiveType).slice(0, 20),
            rtt: Number(navigator.connection.rtt || 0),
            downlink: Number(navigator.connection.downlink || 0),
        }));
    }
    document.addEventListener('visibilitychange', () => {
        send(document.hidden ? 'page_hidden' : 'page_visible');
    });

    let confirmationOpen = false;
    document.addEventListener('click', event => {
        const eventtype = identifySubmissionAction(event.target);
        if (!eventtype) {
            if (confirmationOpen) {
                const button = event.target.closest('button, input[type="submit"]');
                const signature = `${button?.name || ''} ${button?.value || ''} ${button?.textContent || ''} ` +
                    `${button?.dataset.action || ''}`.toLowerCase();
                if (button && (button.matches('.modal [data-action="save"], .modal .modal-footer .btn-primary') ||
                        signature.includes('submit') || signature.includes('confirm') ||
                        signature.includes('save'))) {
                    send('final_submission_confirmed', null, {}, true);
                    confirmationOpen = false;
                }
            }
            return;
        }
        if (eventtype === 'confirmation_dialog_opened' && confirmationOpen) {
            send('final_submission_confirmed', null, {}, true);
            confirmationOpen = false;
            return;
        }
        confirmationOpen = eventtype === 'confirmation_dialog_opened';
        try {
            sessionStorage.setItem(transitionKey, JSON.stringify({
                eventtype,
                started: Date.now(),
            }));
        } catch (error) {
            // Storage can be blocked without affecting the quiz.
        }
        send(eventtype, null, {}, eventtype !== 'finish_attempt_clicked');
    }, true);

    document.addEventListener('submit', event => {
        if (!confirmationOpen) {
            return;
        }
        const action = event.target?.getAttribute('action') || '';
        if (action.includes('/mod/quiz/processattempt.php')) {
            send('final_submission_confirmed', null, {}, true);
            confirmationOpen = false;
        }
    }, true);
};
