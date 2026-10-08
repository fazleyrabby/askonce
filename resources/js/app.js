import Alpine from 'alpinejs';
import './visitor-counter';
window.Alpine = Alpine;
Alpine.start();


const builder = document.getElementById('request-builder');
if (builder) {
    let itemIndex = Math.max(-1, ...[...builder.querySelectorAll('.builder-item')].map(item => Number(item.querySelector('input[name]').name.match(/items\[(\d+)\]/)[1]))) + 1;
    const refreshItemControls = () => {
        const count = builder.querySelectorAll('.builder-item').length;
        for (const button of builder.querySelectorAll('.remove-item')) button.disabled = count === 1;
        document.getElementById('add-item').disabled = count >= 50;
    };
    refreshItemControls();
    document.getElementById('add-item').addEventListener('click', () => {
        if (builder.querySelectorAll('.builder-item').length >= 50) return;
        const html = document.getElementById('item-template').innerHTML.replaceAll('__INDEX__', String(itemIndex++));
        document.getElementById('builder-items').insertAdjacentHTML('beforeend', html);
        document.getElementById('builder-items').lastElementChild.querySelector('input').focus();
        refreshItemControls();
    });
    builder.addEventListener('click', event => {
        if (event.target.matches('.remove-item') && builder.querySelectorAll('.builder-item').length > 1) {
            event.target.closest('.builder-item').remove();
            refreshItemControls();
        }
    });
}
document.getElementById('copy-link')?.addEventListener('click', async () => {
    const field = document.getElementById('share-url');
    const status = document.getElementById('copy-status');
    try { await navigator.clipboard.writeText(field.value); status.textContent = 'Link copied.'; }
    catch { field.select(); status.textContent = 'Select and copy the link above.'; }
});
for (const form of document.querySelectorAll('form')) {
    form.addEventListener('submit', event => {
        const button = event.submitter;
        if (button?.dataset.confirm && !confirm(button.dataset.confirm)) { event.preventDefault(); return; }
        if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
    });
}
