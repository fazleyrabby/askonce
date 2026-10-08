const csrf = document.querySelector('meta[name="csrf-token"]').content;
const forms = [...document.querySelectorAll('[data-item-form]')];
let pending = Promise.resolve();
let completed = false;
const snapshots = new WeakMap();
const failures = new Set();
const snapshot = form => {
    const field = form.elements.namedItem('value');
    return field ? (field.type === 'checkbox' ? String(field.checked) : field.value) : null;
};
for (const form of forms) snapshots.set(form, snapshot(form));
async function request(url, data) {
    const response = await fetch(url, {
        method: 'POST', body: data, credentials: 'same-origin',
        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = response.status === 419 ? 'Your session expired. Reload this page and try again.'
            : response.status === 409 ? 'This request is closed. Reload the page to see its current status.'
            : Object.values(result.errors || {}).flat()[0] || 'Could not save. Check your connection and try again.';
        throw new Error(message);
    }
    return result;
}
function enqueue(form) {
    pending = pending.then(async () => {
        const value = snapshot(form);
        const fileInput = form.querySelector('input[type="file"]');
        if (fileInput ? !fileInput.files.length : value === snapshots.get(form) && !failures.has(form)) return;
        const status = form.querySelector('[data-save-status]');
        const button = form.querySelector('button');
        const data = new FormData();
        status.textContent = 'Saving…';
        status.classList.remove('save-error');
        button.disabled = true;
        try {
            if (fileInput) {
                const files = [...fileInput.files];
                if (files.length > 10 || files.some(file => file.size > 50 * 1024 * 1024) || files.reduce((total,file) => total + file.size, 0) > 90 * 1024 * 1024) {
                    throw new Error('Choose up to 10 files, each under 50 MB and 90 MB total.');
                }
                for (const file of files) data.append('files[]', file);
                data.append('replace', form.elements.namedItem('replace').checked ? '1' : '0');
            } else {
                const field = form.elements.namedItem('value');
                data.append('value', field.type === 'checkbox' ? (field.checked ? '1' : '0') : value);
            }
            const result = await request(form.action, data);
            snapshots.set(form, value);
            failures.delete(form);
            status.textContent = 'Saved';
            if (fileInput) {
                let list = form.querySelector('.uploaded-files');
                if (!list) { list = document.createElement('ul'); list.className = 'uploaded-files'; fileInput.before(list); }
                if (form.elements.namedItem('replace').checked) list.replaceChildren();
                for (const file of fileInput.files) { const li = document.createElement('li'); li.textContent = file.name; list.append(li); }
                fileInput.value = '';
            }
            completed ||= result.completed;
            if (completed) location.reload();
        } catch (error) {
            failures.add(form);
            status.textContent = error.message;
            status.classList.add('save-error');
        } finally { button.disabled = false; }
    });
    return pending;
}
for (const form of forms) {
    form.addEventListener('submit', event => { event.preventDefault(); enqueue(form); });
    form.querySelector('[name="value"], input[type="file"]')?.addEventListener(form.dataset.type === 'file' || form.dataset.type === 'confirmation' ? 'change' : 'blur', () => enqueue(form));
}
const submit = document.getElementById('submit-request');
submit?.addEventListener('click', async () => {
    const message = document.getElementById('submission-message');
    submit.disabled = true;
    submit.setAttribute('aria-busy', 'true');
    try {
        for (const form of forms) enqueue(form);
        await pending;
        if (failures.size) throw new Error('Some items haven’t saved. Use “Save item” to retry before submitting.');
        const result = await request(submit.dataset.url, new FormData());
        if (result.completed || completed) { location.reload(); return; }
        message.textContent = `${result.missing} required item${result.missing === 1 ? '' : 's'} still missing — you can come back to this link any time.`;
        message.className = 'notice';
        message.scrollIntoView({block:'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
    } catch (error) { message.textContent = error.message; message.className = 'error-summary'; }
    finally { submit.disabled = false; submit.removeAttribute('aria-busy'); }
});
window.addEventListener('beforeunload', event => {
    if (!completed && (forms.some(form => snapshot(form) !== snapshots.get(form) || form.querySelector('input[type="file"]')?.files.length) || failures.size)) {
        event.preventDefault();
    }
});
