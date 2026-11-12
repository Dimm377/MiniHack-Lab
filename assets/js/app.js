'use strict';

document.querySelectorAll('.toggle-password').forEach((button) => {
    const input = document.getElementById(button.dataset.target);
    if (!(input instanceof HTMLInputElement)) return;
    button.hidden = false;
    const label = input.labels[0]?.textContent.toLowerCase() || 'password';
    button.addEventListener('click', () => {
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.textContent = showing ? 'Show' : 'Hide';
        button.setAttribute('aria-label', (showing ? 'Show ' : 'Hide ') + label);
        button.setAttribute('aria-pressed', String(!showing));
    });
});

document.querySelector('[data-error-summary]')?.focus();

document.querySelectorAll('form[method="post"]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting) {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; });
    });
});
// Back/forward cache can restore a previously submitted form with disabled controls.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = false; });
    });
});

document.querySelectorAll('textarea').forEach((textarea) => {
    const grow = () => {
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(180, textarea.scrollHeight) + 'px';
    };
    textarea.addEventListener('input', grow);
    grow();
});

const noteForm = document.querySelector('.note-form');
if (noteForm) {
    const original = new URLSearchParams(new FormData(noteForm)).toString();
    window.addEventListener('beforeunload', (event) => {
        if (!noteForm.dataset.submitting && new URLSearchParams(new FormData(noteForm)).toString() !== original) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}

const searchForm = document.getElementById('search-form');
const clearSearch = document.getElementById('clear-search');
if (searchForm && clearSearch) {
    const input = document.getElementById('q');
    const update = () => { clearSearch.hidden = input.value === ''; };
    input.addEventListener('input', update);
    update();
    clearSearch.addEventListener('click', () => {
        input.value = '';
        document.getElementById('search-results').replaceChildren();
        history.replaceState(null, '', '/search.php');
        update();
        input.focus();
    });
}

const apiForm = document.getElementById('api-demo-form');
if (apiForm instanceof HTMLFormElement) {
    const status = document.getElementById('api-status');
    const traceEl = document.getElementById('api-trace');
    const button = apiForm.querySelector('button[type="submit"]');
    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    // Mirrors http_trace() in includes/trace.php; text only, never innerHTML.
    const renderTrace = (container, label, exchanges) => {
        const section = el('section', 'http-trace');
        section.setAttribute('aria-label', label);
        const list = el('ol');
        exchanges.forEach((exchange) => {
            const isRequest = exchange.direction === 'request';
            const details = el('details');
            details.open = exchange.open;
            const summary = el('summary');
            const arrow = el('span', 'http-trace__dir', isRequest ? '→' : '←');
            arrow.setAttribute('aria-hidden', 'true');
            summary.append(arrow, el('span', 'visually-hidden', isRequest ? 'Request: ' : 'Response: '),
                el('b', '', String(exchange.summary[0])), ' ' + exchange.summary[1]);
            if (exchange.note) summary.append(el('span', 'http-trace__note', ' · ' + exchange.note));
            const detail = el('div', 'http-trace__detail');
            Object.entries(exchange.sections).forEach(([title, entries]) => {
                const dl = el('dl');
                Object.entries(entries).forEach(([name, value]) => dl.append(el('dt', '', name), el('dd', '', value)));
                detail.append(el('p', 'http-trace__section', title), dl);
            });
            details.append(summary, detail);
            const row = el('li', 'http-trace__row ' + (isRequest ? 'is-request' : 'is-response'));
            row.append(details);
            list.append(row);
        });
        section.append(el('p', 'http-trace__label', label), list);
        container.replaceChildren(section);
    };
    let pending = false;
    apiForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending) return;
        const id = String(new FormData(apiForm).get('id'));
        const path = '/api/users.php?id=' + encodeURIComponent(id);
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        pending = true;
        button.disabled = true;
        apiForm.setAttribute('aria-busy', 'true');
        status.textContent = 'Sending request…';
        const request = {
            direction: 'request', summary: ['GET', path], note: null, open: false,
            sections: { 'Request headers': { Accept: 'application/json' } },
        };
        try {
            const response = await fetch(path, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
                cache: 'no-store',
            });
            const body = await response.json();
            status.textContent = 'HTTP ' + response.status;
            renderTrace(traceEl, 'Inspector', [request, {
                direction: 'response', summary: [response.status, response.statusText], note: null, open: true,
                sections: {
                    'Response headers': Object.fromEntries(response.headers),
                    Body: { JSON: JSON.stringify(body, null, 2) },
                },
            }]);
        } catch (error) {
            status.textContent = 'Request failed';
            renderTrace(traceEl, 'Inspector', [request, {
                direction: 'response', summary: ['No response', ''], open: true, sections: {},
                note: error.name === 'AbortError'
                    ? 'The request timed out. Send it again to retry.'
                    : 'Could not read the response. Check the local server and send the request again.',
            }]);
        } finally {
            clearTimeout(timeout);
            pending = false;
            button.disabled = false;
            apiForm.removeAttribute('aria-busy');
        }
    });
}
