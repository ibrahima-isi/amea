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
