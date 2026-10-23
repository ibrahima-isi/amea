/** Real Chrome journey against the disposable HTTP runner. No npm packages. */
import {spawn} from 'node:child_process';
import {mkdtemp, rm, mkdir, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
const [base, out, state = 'multiple'] = process.argv.slice(2);
assert(base?.startsWith('http://127.0.0.1:'), 'only isolated loopback server');
const directory = await mkdtemp(path.join(tmpdir(), 'amea-chrome-'));
const chrome = process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', `--user-data-dir=${directory}`, 'about:blank'], {stdio:['ignore','ignore','pipe']});
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
    const navigate=async()=>{await cdp('Page.navigate',{url:base});for(let i=0;i<80;i++){await wait(50);if(await evaluate("document.readyState === 'complete' && !!document.querySelector('[data-community-carousel]')"))return;}throw Error('page load timeout');};
    const check=async (expression,label)=>{assert(await evaluate(expression),label);console.log('PASS browser:',label);};
    await cdp('Page.enable');
    await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await cdp('Emulation.setEmulatedMedia',{features:[{name:'prefers-color-scheme',value:'light'},{name:'prefers-reduced-motion',value:'no-preference'}]});
    await navigate();
    await check("document.documentElement.dataset.bsTheme === 'light'",'light theme follows device');
    if (state !== 'multiple') {
        await check("!document.querySelector('[data-carousel-pause]')", `${state} state has no rotation controls`);
        await check("document.documentElement.scrollWidth <= innerWidth", `${state} state has no overflow`);
        await check(state === 'single' ? "document.querySelector('.community-slide img').naturalWidth > 0" : "!!document.querySelector('.community-empty')", `${state} state renders usable content`);
    } else {
    await check("[...document.querySelectorAll('.community-slide:not([hidden]) img')].every(img=>img.complete&&img.naturalWidth>0)",'backend uploaded photos render');
    await check("document.documentElement.scrollWidth <= innerWidth",'desktop has no horizontal overflow');
    await mkdir(out,{recursive:true});
    const screenshot=async name=>{const {data}=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});await writeFile(path.join(out,name),Buffer.from(data,'base64'));};
    await screenshot('homepage-desktop-light.png');
    await wait(6800);
    await check("document.querySelector('[data-carousel-count]').textContent === '2 / 2'",'autoplay advances photo');
    const pausePoint = await evaluate("(() => {const r=document.querySelector('[data-carousel-pause]').getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()");
    await cdp('Input.dispatchMouseEvent',{type:'mouseMoved',...pausePoint});
    await cdp('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...pausePoint});
    await cdp('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...pausePoint});
    await cdp('Input.dispatchMouseEvent',{type:'mouseMoved',x:0,y:0});
    await wait(6800);
    await check("document.querySelector('[data-carousel-count]').textContent === '2 / 2' && document.querySelector('[data-carousel-pause]').getAttribute('aria-pressed') === 'true'",'pause stops autoplay');
    await evaluate("document.querySelector('[data-carousel-next]').click()");
    await check("document.querySelector('[data-carousel-count]').textContent === '1 / 2'",'manual next wraps while paused');
    await evaluate("document.querySelector('[data-carousel-prev]').click()");
    await check("document.querySelector('[data-carousel-count]').textContent === '2 / 2'",'manual previous wraps');
    await evaluate("document.querySelector('.theme-switcher').click()");
    await check("document.documentElement.dataset.bsTheme === 'dark'",'theme switch selects dark');
    await screenshot('homepage-desktop-dark.png');
    await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await screenshot('homepage-mobile-dark.png');
    await check("document.documentElement.scrollWidth <= innerWidth",'mobile has no horizontal overflow');
    await evaluate("document.querySelector('.theme-switcher').click()");
    await screenshot('homepage-mobile-light.png');
    await evaluate('localStorage.removeItem("theme")');
    await cdp('Emulation.setEmulatedMedia',{features:[{name:'prefers-color-scheme',value:'dark'},{name:'prefers-reduced-motion',value:'reduce'}]});
    await navigate();
    await check("document.documentElement.dataset.bsTheme === 'dark'",'dark theme follows device without saved override');
    await check("document.querySelector('[data-carousel-pause]').getAttribute('aria-pressed') === 'true'",'reduced motion starts paused');
    await wait(6800);
    await check("document.querySelector('[data-carousel-count]').textContent === '1 / 2'",'reduced motion prevents automatic rotation');
    await cdp('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
    await cdp('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
    await check("document.activeElement.classList.contains('skip-link') && getComputedStyle(document.activeElement).outlineStyle !== 'none'",'keyboard skip link has visible focus');
    await cdp('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,text:'\r',unmodifiedText:'\r'});
    await cdp('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await check("document.activeElement.id === 'main-content'",'skip link reaches main content');
    for (let step=0;step<15;step++) {
        await cdp('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
        await cdp('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
        if (await evaluate("document.activeElement.hasAttribute('data-carousel-next')")) break;
    }
    await check("document.activeElement.hasAttribute('data-carousel-next') && getComputedStyle(document.activeElement).outlineStyle !== 'none'",'keyboard reaches carousel with visible focus');
    await cdp('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,text:'\r',unmodifiedText:'\r'});
    await cdp('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await check("document.querySelector('[data-carousel-count]').textContent === '2 / 2'",'keyboard activates next photo during reduced motion');
    console.log('Browser screenshots:',out);
    }
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
