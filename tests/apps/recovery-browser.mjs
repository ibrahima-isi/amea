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
    const phase = process.env.AMEA_RECOVERY_BROWSER_PHASE || 'before';
    await cdp('Network.setCookie', {name:'AMEA_PUBLIC', value:'recoverypending', url:base,
        secure:true, httpOnly:true, sameSite:'Strict', path:'/'});
    await cdp('Emulation.setDeviceMetricsOverride',{width:1280,height:900,deviceScaleFactor:1,mobile:false});
    await navigate(base+'/registration-details.php');
    await check("document.body.innerText.includes('RecoveryPending')", 'pre-pause pending registration renders in the original session');
    const preview = await evaluate("fetch('/registration-preview.php?type=identite').then(async response => ({status:response.status,type:response.headers.get('Content-Type'),text:await response.text()}))");
    assert(preview.status===200 && preview.type==='application/pdf' && preview.text.includes('Synthetic registration opened before recovery pause'));
    console.log('PASS browser: restored/current private object preview returns its preserved synthetic PDF and MIME');
    await mkdir(out,{recursive:true});
    const shot = await cdp('Page.captureScreenshot',{format:'png'});
    await writeFile(path.join(out,'recovery-registration-'+phase+'.png'),Buffer.from(shot.data,'base64'));
    if (phase === 'after') {
        await evaluate("document.querySelector('form[action=\"registration-details.php\"]').submit()");
        for(let i=0;i<100;i++) { await wait(100); if(await evaluate("location.pathname==='/' && !!document.querySelector('.public-flash')"))break; }
        await check("location.pathname==='/' && document.querySelector('.public-flash')?.textContent.includes('révision')", 'registration opened before pause confirms after isolated restored reopening');
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
