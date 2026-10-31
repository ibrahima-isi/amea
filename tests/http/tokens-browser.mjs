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
    const navigate=async route=>{await cdp('Page.navigate',{url:base+route});for(let n=0;n<100;n++){await wait(100);if(await evaluate("document.readyState === 'complete' && !!document.querySelector('.public-form-page')"))return;}throw Error('page load timeout');};
    const check=async(expression,label)=>{assert(await evaluate(expression),label);console.log('PASS browser:',label);};
    const key=async(key,code,keyCode)=>{await cdp('Input.dispatchKeyEvent',{type:'keyDown',key,code,windowsVirtualKeyCode:keyCode,...(key==='Enter'?{text:'\r'}:{})});await cdp('Input.dispatchKeyEvent',{type:'keyUp',key,code,windowsVirtualKeyCode:keyCode});};
    await cdp('Page.enable');
    await mkdir(out,{recursive:true});
    const screenshot=async name=>{const {data}=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});await writeFile(path.join(out,name),Buffer.from(data,'base64'));};
    const correction='/kyc-correction.php?token='+'a'.repeat(64);
    const consent='/accept-cgu.php?token='+'a'.repeat(64);
    await navigate(correction);
    for(const [width,mobile] of [[1440,false],[390,true],[320,true]]) {
        await cdp('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile});
        for(const theme of ['light','dark']) {
            await cdp('Emulation.setEmulatedMedia',{features:[{name:'prefers-color-scheme',value:theme},{name:'prefers-reduced-motion',value:'reduce'}]});
            await evaluate(`localStorage.setItem('theme', '${theme}')`);
            for(const [name,route] of [['correction',correction],['consent',consent],['legal','/legal-notice.php']]) {
                await navigate(route);
                await check(`document.documentElement.dataset.bsTheme === '${theme}'`,`${name} ${width} retains explicit ${theme} preference`);
                await check('document.documentElement.scrollWidth <= innerWidth',`${name} ${width} ${theme} no horizontal overflow`);
                await check("document.querySelectorAll('main').length === 1 && document.querySelectorAll('h1').length === 1",`${name} landmark and heading hierarchy`);
                if(width!==320) await screenshot(`${name}-${mobile?'mobile':'desktop'}-${theme}.png`);
            }
        }
    }
    await navigate(correction);
    await evaluate("document.querySelector('#phone').value = ''");
    await check("!document.querySelector('#phone').checkValidity()",'correction missing required phone exposes native validation');
    await evaluate("document.querySelector('#phone').focus()");
    await check("document.activeElement.id === 'phone'",'correction field accepts keyboard focus');
    await navigate(consent);
    await key('Tab','Tab',9);
    await check("document.activeElement.classList.contains('skip-link') && getComputedStyle(document.activeElement).outlineStyle !== 'none'",'consent keyboard skip link visible');
    await key('Enter','Enter',13);
    await check("document.activeElement.id === 'main-content'",'consent skip link reaches main');
    for(let n=0;n<12;n++){await key('Tab','Tab',9);if(await evaluate("document.activeElement.type === 'submit'"))break;}
    await check("document.activeElement.type === 'submit' && getComputedStyle(document.activeElement).outlineStyle !== 'none'",'consent action reachable with visible keyboard focus');
    await key('Enter','Enter',13);await wait(500);
    await check("document.querySelector('h1').textContent.includes('Consentement enregistré')",'keyboard submits consent confirmation');
    await navigate('/accept-cgu.php?token=invalid');
    await check("document.querySelector('h1').textContent.includes('Lien invalide') && !document.querySelector('[name=token]')",'invalid consent has safe public state');
    console.log('Browser screenshots:',out);
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
