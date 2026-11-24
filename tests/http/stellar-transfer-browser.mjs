/**
 * Real Chrome browser driver for the legacy-VPS to split-Stellar transfer rehearsal.
 *
 * Keeps one Chrome process and cookie jar alive across the simulated cutover so
 * session cookies (lifetime=0, Secure, HttpOnly, SameSite=Strict) are tested
 * without artificial cookie injection:
 *   Phase 1 (on legacy single-app source): register with staged PDFs, verify
 *            PHPSESSID cookie and staged previews, then wait for "RESUME\n" on stdin.
 *   Phase 2 (on split Stellar public host at the exact same origin): navigate to
 *            /registration-details.php, verify PHPSESSID -> AMEA_PUBLIC handover,
 *            preview both staged PDFs from the destination backend, confirm once,
 *            and verify confirmation feedback.
 */
import {spawn} from 'node:child_process';
import {mkdtemp, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import path from 'node:path';
import readline from 'node:readline';
import assert from 'node:assert/strict';

const [publicBase, identityText, cvText] = process.argv.slice(2);
assert(publicBase?.startsWith('http://127.0.0.1:'), 'only isolated loopback origin is allowed');
assert(identityText && cvText, 'synthetic PDF marker texts are required');

const profileDir = await mkdtemp(path.join(tmpdir(), 'amea-transfer-chrome-'));
const chrome = process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const browser = spawn(
    chrome,
    [
        '--headless=new',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        '--remote-debugging-port=0',
        `--user-data-dir=${profileDir}`,
        'about:blank',
    ],
    {stdio: ['ignore', 'ignore', 'pipe']}
);

let stderr = '';
let ws;
let shutdown;

const rl = readline.createInterface({input: process.stdin});
const waitForResumeSignal = () => new Promise((resolve, reject) => {
    const onLine = (line) => {
        if (line.trim() === 'RESUME') {
            rl.off('line', onLine);
            resolve();
        }
    };
    rl.on('line', onLine);
    rl.once('close', () => reject(new Error('stdin closed before RESUME signal')));
});

try {
    const browserUrl = await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Chrome startup timeout')), 15000);
        browser.stderr.on('data', (chunk) => {
            stderr += chunk;
            const match = stderr.match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if (match) {
                clearTimeout(timer);
                resolve(match[1]);
            }
        });
        browser.on('error', reject);
    });

    ws = new WebSocket(browserUrl);
    await new Promise((resolve, reject) => {
        ws.onopen = resolve;
        ws.onerror = reject;
    });

    let msgId = 0;
    const pending = new Map();
    ws.onmessage = (event) => {
        const message = JSON.parse(event.data);
        if (message.id) {
            const p = pending.get(message.id);
            pending.delete(message.id);
            if (message.error) {
                p.reject(new Error(JSON.stringify(message.error)));
            } else {
                p.resolve(message.result);
            }
        }
    };
    const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const next = ++msgId;
        pending.set(next, {resolve, reject});
        ws.send(JSON.stringify({id: next, method, params, ...(sessionId ? {sessionId} : {})}));
    });
    shutdown = () => send('Browser.close');

    const target = await send('Target.createTarget', {url: 'about:blank'});
    const {sessionId} = await send('Target.attachToTarget', {targetId: target.targetId, flatten: true});
    const cdp = (method, params = {}) => send(method, params, sessionId);
    const evaluate = async (expression) => {
        const r = await cdp('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
        if (r.exceptionDetails) {
            throw new Error(JSON.stringify(r.exceptionDetails));
        }
        return r.result.value;
    };
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));
    const waitForCondition = async (expression, label, timeoutMs = 15000) => {
        const deadline = Date.now() + timeoutMs;
        while (Date.now() < deadline) {
            if (await evaluate(expression)) {
                return;
            }
            await wait(100);
        }
        throw new Error(`Timed out waiting for: ${label} (href=${await evaluate('location.href')})`);
    };

    await cdp('Page.enable');
    await cdp('Network.enable');

    // Phase 1: Start unconfirmed registration on legacy single-app source
    await cdp('Page.navigate', {url: `${publicBase}/register.php`});
    await waitForCondition("document.readyState === 'complete' && !!document.querySelector('#registrationForm')", 'legacy /register.php load');

    const submitScript = `(() => {
        const f = document.querySelector('#registrationForm');
        f.querySelector('[name=last_name]').value = 'TransferMember';
        f.querySelector('[name=first_name]').value = 'Rehearsal';
        f.querySelector('[name=gender]').checked = true;
        f.querySelector('[name=status]').value = 'ETUDIANT';
        f.querySelector('[name=phone]').value = '775551234';
        f.querySelector('[name=email]').value = 'transfer-rehearsal@example.invalid';
        f.querySelector('[name=consent_privacy]').checked = true;
        const idFiles = new DataTransfer();
        idFiles.items.add(new File([${JSON.stringify(identityText)}], 'identity.pdf', {type: 'application/pdf'}));
        f.querySelector('[name=photo]').files = idFiles.files;
        const cvFiles = new DataTransfer();
        cvFiles.items.add(new File([${JSON.stringify(cvText)}], 'cv.pdf', {type: 'application/pdf'}));
        f.querySelector('[name=cv_file]').files = cvFiles.files;
        f.submit();
    })()`;
    await evaluate(submitScript);
    await waitForCondition("location.pathname.includes('registration-details.php') && document.readyState === 'complete'", 'legacy /registration-details.php');

    const beforeCookies = (await cdp('Network.getCookies', {urls: [publicBase]})).cookies;
    const legacyCookie = beforeCookies.find((c) => c.name === 'PHPSESSID');
    const splitCookieBefore = beforeCookies.find((c) => c.name === 'AMEA_PUBLIC');
    assert(legacyCookie && legacyCookie.value, 'legacy source must issue PHPSESSID');
    assert(!splitCookieBefore, 'legacy source must not issue AMEA_PUBLIC');

    const fetchPreviews = () => evaluate(`(async () => {
        const idResp = await fetch('/registration-preview.php?type=identite');
        const cvResp = await fetch('/registration-preview.php?type=cv');
        return {
            idStatus: idResp.status,
            idType: idResp.headers.get('Content-Type'),
            idNosniff: idResp.headers.get('X-Content-Type-Options'),
            idText: await idResp.text(),
            cvStatus: cvResp.status,
            cvType: cvResp.headers.get('Content-Type'),
            cvNosniff: cvResp.headers.get('X-Content-Type-Options'),
            cvText: await cvResp.text(),
        };
    })()`);

    const prePreview = await fetchPreviews();
    assert.equal(prePreview.idStatus, 200);
    assert.equal(prePreview.idType, 'application/pdf');
    assert.equal(prePreview.idText, identityText);
    assert.equal(prePreview.cvStatus, 200);
    assert.equal(prePreview.cvType, 'application/pdf');
    assert.equal(prePreview.cvText, cvText);

    // Park tab on about:blank so browser stays open with its session cookie jar intact while cutover runs
    await cdp('Page.navigate', {url: 'about:blank'});
    console.log('STAGED_READY ' + JSON.stringify({legacy_cookie_name: legacyCookie.name}));

    await waitForResumeSignal();

    // Phase 2: Resume in the exact same browser against split Stellar public host on the same origin
    await cdp('Page.navigate', {url: `${publicBase}/registration-details.php`});
    await waitForCondition(
        "location.pathname.includes('registration-details.php') && document.readyState === 'complete' && document.body.innerText.includes('TransferMember')",
        'resumed /registration-details.php on split Stellar host'
    );

    const afterCookies = (await cdp('Network.getCookies', {urls: [publicBase]})).cookies;
    const newPublicCookie = afterCookies.find((c) => c.name === 'AMEA_PUBLIC');
    const leftoverLegacy = afterCookies.find((c) => c.name === 'PHPSESSID');
    assert(newPublicCookie && newPublicCookie.value && newPublicCookie.value !== legacyCookie.value,
        'split Stellar public host must rotate PHPSESSID to a fresh AMEA_PUBLIC cookie');
    assert(!leftoverLegacy, 'legacy PHPSESSID cookie must be expired after handover');

    const postPreview = await fetchPreviews();
    assert.equal(postPreview.idStatus, 200);
    assert.equal(postPreview.idType, 'application/pdf');
    assert.equal(postPreview.idNosniff, 'nosniff');
    assert.equal(postPreview.idText, identityText);
    assert.equal(postPreview.cvStatus, 200);
    assert.equal(postPreview.cvType, 'application/pdf');
    assert.equal(postPreview.cvNosniff, 'nosniff');
    assert.equal(postPreview.cvText, cvText);

    await evaluate("document.querySelector('form[action=\"registration-details.php\"]').submit()");
    await waitForCondition("location.pathname === '/' && !!document.querySelector('.public-flash')", 'registration confirmation redirect');
    const flashText = await evaluate("document.querySelector('.public-flash')?.textContent || ''");
    assert(flashText.includes('révision') || flashText.includes('enregistrée'), `unexpected confirmation flash: ${flashText}`);

    console.log('RESUME_CONFIRMED ' + JSON.stringify({
        rotated_to: newPublicCookie.name,
        legacy_expired: !leftoverLegacy,
    }));
} finally {
    rl.close();
    if (shutdown) {
        await Promise.race([shutdown().catch(() => {}), new Promise((r) => setTimeout(r, 1500))]);
    }
    ws?.close();
    browser.stderr.destroy();
    if (browser.exitCode === null) {
        const exited = new Promise((resolve) => browser.once('exit', resolve));
        browser.kill('SIGKILL');
        await exited;
    }
    await rm(profileDir, {recursive: true, force: true});
}
