// Shared views service (same one the other portfolio projects use).
const endpoint = 'https://views.fazleyrabbi.xyz';
const project = 'askonce';
const cacheKey = 'askonce:visits';
const sessionKey = 'askonce:visitTracked';

const storage = store => (store === 'local' ? localStorage : sessionStorage);
const read = (store, key) => { try { return storage(store).getItem(key); } catch { return null; } };
const write = (store, key, value) => { try { storage(store).setItem(key, value); } catch { /* Storage can be disabled. */ } };

const isPreview = () => {
    const host = location.hostname;
    return host === 'localhost' || host === '127.0.0.1' || host === '::1' || host.endsWith('.local') || host.endsWith('.test') || location.port !== '';
};
const isAutomatedVisitor = () => {
    const agent = navigator.userAgent.toLowerCase();
    return navigator.webdriver || ['bot', 'spider', 'crawler', 'preview', 'lighthouse', 'headless', 'playwright', 'puppeteer', 'selenium', 'curl', 'wget', 'uptime'].some(word => agent.includes(word));
};
const validCount = value => (typeof value === 'number' && Number.isSafeInteger(value) && value >= 0 ? value : null);

// Counts one visit per browser session (never on localhost or bots) and resolves with the total.
const trackVisit = async () => {
    const track = !isPreview() && !isAutomatedVisitor() && read('session', sessionKey) !== 'true';
    if (track) write('session', sessionKey, 'true');
    try {
        const response = await fetch(`${endpoint}/api/${track ? 'hit' : 'get'}?project=${project}&key=visitors`, { signal: AbortSignal.timeout(4000), cache: 'no-store' });
        if (!response.ok) return null;
        const views = validCount((await response.json())?.views);
        if (views !== null) write('local', cacheKey, String(views));
        return views;
    } catch {
        // Keep the last known count when offline.
        return null;
    }
};

const counter = document.querySelector('[data-visits]');
if (counter) {
    const show = visits => {
        if (!visits) return;
        counter.querySelector('[data-visits-count]').textContent = visits.toLocaleString();
        counter.hidden = false;
    };
    const cached = read('local', cacheKey);
    show(cached === null ? null : validCount(Number(cached)));
    trackVisit().then(show);
}
