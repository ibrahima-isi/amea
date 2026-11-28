/** Real Chrome journey against the disposable HTTP runner. No npm packages. */
import {spawn} from 'node:child_process';
import {mkdtemp, rm, mkdir, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
const out = process.argv[2] || '/private/tmp/amea-app-browser';
const base = 'https://public.amea.test:' + (process.env.AMEA_PUBLIC_PORT || '18443');
const staff = 'https://staff.amea.test:' + (process.env.AMEA_STAFF_PORT || '19443');
const directory = await mkdtemp(path.join(tmpdir(), 'amea-chrome-'));
const chrome = process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--ignore-certificate-errors', '--no-proxy-server', '--host-resolver-rules=MAP *.amea.test 127.0.0.1', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', `--user-data-dir=${directory}`, 'about:blank'], {stdio:['ignore','ignore','pipe']});
let stderr='';
let ws;
let shutdown;
try {
    const browserUrl = await new Promise((resolve,reject) => {
        const timer = setTimeout(() => reject(Error('Chrome startup timeout')),15000);
        browser.stderr.on('data',chunk => { stderr+=chunk; const match=stderr.match(/DevTools listening on (ws:\/\/[^\s]+)/); if(match){clearTimeout(timer);resolve(match[1]);}});
        browser.on('error',reject);
    });
    ws = new WebSocket(browserUrl);
    await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
    let id=0; const pending=new Map();
    ws.onmessage=event=>{const message=JSON.parse(event.data);if(message.id){const p=pending.get(message.id);pending.delete(message.id);message.error?p.reject(Error(JSON.stringify(message.error))):p.resolve(message.result);}};
    const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const next=++id;pending.set(next,{resolve,reject});ws.send(JSON.stringify({id:next,method,params,...(sessionId?{sessionId}:{})}));});
    shutdown = () => send('Browser.close');
    const target=await send('Target.createTarget',{url:'about:blank'});
    const {sessionId}=await send('Target.attachToTarget',{targetId:target.targetId,flatten:true});
    const cdp=(method,params={})=>send(method,params,sessionId);
    const evaluate=async expression=>{const r=await cdp('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const wait=ms=>new Promise(r=>setTimeout(r,ms));
    const navigate=async (url, expected=url)=>{await cdp('Page.navigate',{url});for(let i=0;i<100;i++){await wait(100);if(await evaluate("document.readyState !== 'loading' && location.href === " + JSON.stringify(expected)))return;}throw Error('page load timeout '+url+' '+JSON.stringify(await evaluate('({href:location.href,state:document.readyState,title:document.title})')));};
    const check=async (expression,label)=>{for(let attempt=0;attempt<100;attempt++){if(await evaluate(expression)){console.log('PASS browser:',label);return;}await wait(100);}assert(await evaluate(expression),label);};
    await cdp('Page.enable');
    await cdp('Network.enable');
    await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await navigate(staff+'/login.php');
    await evaluate("document.querySelector('[name=username]').value='toolsadmin';document.querySelector('[name=password]').value='Fixture-password-123!';document.querySelector('form').submit()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("location.pathname === '/dashboard.php'"))break;}
    await mkdir(out,{recursive:true});
    for (const route of ['manage-slider.php','communications.php','export.php','settings.php','users.php','add-user.php','upgrade-levels.php','profile.php']) {
        await navigate(staff+'/'+route);
        await check("document.querySelector('main') !== null && !document.body.innerText.includes('Fatal error')", 'authorized existing staff tool '+route);
    }
    const pressEnter = async () => {
        const key = {key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13};
        await cdp('Input.dispatchKeyEvent', {...key, type: 'keyDown', text: '\r'});
        await cdp('Input.dispatchKeyEvent', {...key, type: 'keyUp'});
    };
    for (const [page, form] of [['communications', 'bulkCommunicationForm'], ['consent', 'cguReminderForm']]) {
        await cdp('Emulation.setEmulatedMedia', {features: [{name: 'prefers-color-scheme', value: 'dark'}]});
        await navigate(staff+'/'+page+'.php');
        await evaluate("localStorage.removeItem('theme'); document.documentElement.dataset.beforeReload = 'true'");
        await cdp('Page.reload');
        await check(`document.readyState === 'complete' && !document.documentElement.dataset.beforeReload && document.querySelector('#${form}') && document.documentElement.dataset.bsTheme === 'light'`,
            page+' loads in light mode on a dark system');
        if (page === 'communications') {
            const guarded = await evaluate(`(async () => {
                const form = document.getElementById('bulkCommunicationForm');
                const originalFetch = window.fetch;
                const originalSwal = window.Swal;
                const subject = document.getElementById('subject');
                const body = document.getElementById('body');
                let posts = 0;
                let confirmations = 0;
                form.submit = () => posts++;
                subject.value = 'Test';
                body.value = 'Corps';
                const submit = async () => {
                    form.dispatchEvent(new Event('submit', {cancelable: true}));
                    await new Promise(resolve => setTimeout(resolve, 0));
                };
                try {
                    window.Swal = {fire: async () => {
                        confirmations++;
                        body.value += ' modifié';
                        return {isConfirmed: true};
                    }};
                    window.fetch = async () => ({ok: false, status: 503});
                    await submit();
                    if (posts || confirmations) return false;
                    window.fetch = async () => ({ok: true, json: async () => ({count: 2})});
                    await submit();
                    return posts === 0 && confirmations === 1;
                } finally {
                    window.fetch = originalFetch;
                    window.Swal = originalSwal;
                    delete form.submit;
                    subject.value = body.value = '';
                }
            })()`);
            assert(guarded, 'A failed audience refresh or changed message must block submission');
        }
        const palette = "getComputedStyle(document.body).color + ' ' + getComputedStyle(document.body).backgroundColor";
        const lightPalette = await evaluate(palette);
        for (const theme of ['light', 'dark']) {
            if (theme === 'dark') {
                await evaluate("document.querySelector('.theme-switcher').focus()");
                await pressEnter();
                await check(`document.documentElement.dataset.bsTheme === 'dark' && ${palette} !== ${JSON.stringify(lightPalette)}`,
                    page+' switches text and background palette from the keyboard');
            }
            await check(`(() => {
                const luminance = (color) => {
                    const channels = color.match(/[0-9]+/g).slice(0, 3).map(value => Number(value) / 255);
                    const [red, green, blue] = channels.map(value =>
                        value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
                    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
                };
                const hasContrast = (element, surface, minimum) => {
                    const text = luminance(getComputedStyle(element).color);
                    const background = luminance(getComputedStyle(surface).backgroundColor);
                    return (Math.max(text, background) + 0.05) / (Math.min(text, background) + 0.05) >= minimum;
                };
                const metrics = [...document.querySelectorAll('.h2.fw-bold[class*="-emphasis"]')];
                const links = [...document.querySelectorAll('#consentTabs .nav-link:not(.active)')];
                return metrics.length >= 2 && metrics.every(metric => hasContrast(metric, metric.closest('.card'), 3))
                    && links.every(link => hasContrast(link, document.body, 4.5));
            })()`, page+' metrics and navigation meet text contrast in '+theme);
            for (const width of [390, 1440]) {
                await cdp('Emulation.setDeviceMetricsOverride', {width,height:1000,deviceScaleFactor:1,mobile:false});
                await check("document.documentElement.scrollWidth <= innerWidth && document.querySelector('main').scrollWidth <= document.querySelector('main').clientWidth",
                    page+' fits '+width+' in '+theme);
                const {data} = await cdp('Page.captureScreenshot', {format:'png'});
                await writeFile(path.join(out, `staff-${page}-${width}-${theme}.png`), Buffer.from(data, 'base64'));
            }
        }
    }
    await evaluate("document.querySelector('#consentTabs a[href*=deletion]').focus()");
    await pressEnter();
    await check("location.search === '?tab=deletion' && document.querySelector('#consentTabs a[aria-current=page]')?.href.endsWith('?tab=deletion')",
        'consent deletion requests are reachable by keyboard');
    await navigate(staff+'/manage-slider.php');
    let shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'staff-carousel.png'),Buffer.from(shot.data,'base64'));
    await navigate(base+'/');
    await check("[...document.querySelectorAll('img')].some(i=>i.alt==='Synthetic tools carousel' && i.complete && i.naturalWidth>0)", 'staff publication renders actual image on public HTTPS page');
    shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'public-staff-publication.png'),Buffer.from(shot.data,'base64'));

    await navigate(staff+'/logout.php',staff+'/login.php');
    await check("fetch('/manage-journal.php').then(async response=>response.redirected && new URL(response.url).pathname==='/login.php' && new DOMParser().parseFromString(await response.text(),'text/html').querySelector('[name=username]') !== null)", 'logged-out browser is denied private journal administration');
    await navigate(staff+'/login.php');
    await evaluate("document.querySelector('[name=username]').value='journal-admin';document.querySelector('[name=password]').value='Fixture-password-123!';document.querySelector('form').submit()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("location.pathname === '/dashboard.php'"))break;}
    let editor;
    for (const page of [1, 2]) {
        await navigate(staff+'/manage-journal.php?page='+page);
        editor = await evaluate("[...document.querySelectorAll('tr')].find(row=>row.cells[0]?.textContent==='Updated draft')?.querySelector('a')?.href");
        if (editor) break;
    }
    assert(editor, 'published journal fixture has staff editor');
    await navigate(editor);
    const preview = await evaluate("document.querySelector('a[href*=\"journal-preview.php\"]').href");
    const publicArticle = await evaluate("document.querySelector('a[href*=\"journal-article.php\"]').href");
    await navigate(publicArticle);
    await check("[...document.images].some(image=>image.src.includes('journal-cover.php') && image.complete && image.naturalWidth>0)", 'published private-storage cover loads in actual browser');
    const coverUrl = await evaluate("[...document.images].find(image=>image.src.includes('journal-cover.php')).src");
    shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'journal-published-cover.png'),Buffer.from(shot.data,'base64'));
    await navigate(editor);
    await evaluate("document.querySelector('button[value=withdraw]').click()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("!document.querySelector('button[value=withdraw]')"))break;}
    await navigate(base+'/journal.php');
    assert.equal(await evaluate("fetch("+JSON.stringify(coverUrl)+").then(response=>response.status)"),404,'withdrawn cover denied after successful browser image read');
    await navigate(preview);
    await check("[...document.images].some(image=>image.src.includes('journal-preview-cover.php') && image.complete && image.naturalWidth>0)", 'withdrawn draft cover loads only through authorized staff preview');
    shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'journal-withdrawn-staff-preview.png'),Buffer.from(shot.data,'base64'));
    await navigate(editor);
    await evaluate("document.querySelector('button[value=publish]').click()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("document.querySelector('button[value=withdraw]') !== null"))break;}
    await navigate(publicArticle);
    await check("[...document.images].some(image=>image.src.includes('journal-cover.php') && image.complete && image.naturalWidth>0)", 'republished cover becomes readable again');

} finally {
    if (shutdown) await Promise.race([shutdown().catch(() => {}), new Promise(resolve => setTimeout(resolve, 1500))]);
    ws?.close();
    browser.stderr.destroy();
    if (browser.exitCode === null) {
        const exited = new Promise(resolve => browser.once('exit', resolve));
        browser.kill('SIGKILL');
        await exited;
    }
    await rm(directory,{recursive:true,force:true});
}
