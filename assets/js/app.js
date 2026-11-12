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
    const result = document.getElementById('api-result');
    const status = document.getElementById('api-status');
    const headerList = document.getElementById('api-headers');
    const button = apiForm.querySelector('button[type="submit"]');
    const showHeaders = (entries) => {
        headerList.replaceChildren(...entries.flatMap(([name, value]) => {
            const term = document.createElement('dt');
            const detail = document.createElement('dd');
            term.textContent = name;
            detail.textContent = value;
            return [term, detail];
        }));
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
        result.textContent = 'Waiting for response…';
        showHeaders([['Headers', '—']]);
        try {
            const response = await fetch(path, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
                cache: 'no-store',
            });
            const body = await response.json();
            status.textContent = 'HTTP ' + response.status;
            showHeaders([...response.headers]);
            result.textContent = JSON.stringify(body, null, 2);
        } catch (error) {
            status.textContent = 'Request failed';
            result.textContent = error.name === 'AbortError'
                ? 'The request timed out. Send it again to retry.'
                : 'Could not read the response. Check the local server and send the request again.';
        } finally {
            clearTimeout(timeout);
            pending = false;
            button.disabled = false;
            apiForm.removeAttribute('aria-busy');
        }
    });
}
