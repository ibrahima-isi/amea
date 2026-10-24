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
    const navigate=async()=>{await cdp('Page.navigate',{url:base+'/register.php'});for(let i=0;i<80;i++){await wait(50);if(await evaluate("document.readyState === 'complete' && !!document.querySelector('#registrationForm')"))return;}throw Error('page load timeout');};
    const check=async (expression,label)=>{assert(await evaluate(expression),label);console.log('PASS browser:',label);};
    await cdp('Page.enable');
    await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await cdp('Emulation.setEmulatedMedia',{features:[{name:'prefers-color-scheme',value:'light'},{name:'prefers-reduced-motion',value:'reduce'}]});
    await navigate();
    await mkdir(out,{recursive:true});
    const screenshot=async name=>{const {data}=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});await writeFile(path.join(out,name),Buffer.from(data,'base64'));};
    for(let i=0;i<100;i++){if(await evaluate("!!document.querySelector('.tagify')"))break;await wait(100);}
    await check("!!document.querySelector('.tagify')",'nationalities enhancement initializes');
    await check("document.querySelectorAll('main').length===1",'single accessible main landmark');
    await check("document.documentElement.scrollWidth<=innerWidth",'registration desktop has no overflow');
    await screenshot('registration-desktop-light.png');
    await evaluate("document.querySelector('.theme-switcher').click()");
    await wait(300);
    await screenshot('registration-desktop-dark.png');
    await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await check("document.documentElement.scrollWidth<=innerWidth",'registration mobile has no overflow');
    await screenshot('registration-mobile-dark.png');
    await evaluate("document.querySelector('.theme-switcher').click()");
    await wait(300);
    await screenshot('registration-mobile-light.png');
    await cdp('Emulation.setDeviceMetricsOverride',{width:320,height:844,deviceScaleFactor:1,mobile:true});
    await check("document.documentElement.scrollWidth<=innerWidth",'registration320px has no overflow');
    await evaluate("document.querySelector('.registration-section-toggle').focus()");
    await cdp('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,text:'\r'});
    await cdp('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await wait(300);
    await check("document.activeElement.getAttribute('aria-expanded')==='true'",'keyboard expands optional section');
    await check("getComputedStyle(document.activeElement).outlineStyle!=='none'",'section keyboard focus is visible');
    await evaluate(`(() => {const f=document.querySelector('#registrationForm');f.querySelector('[name=last_name]').value='Browser';f.querySelector('[name=first_name]').value='Fixture';f.querySelector('[name=gender]').checked=true;f.querySelector('[name=status]').value='ETUDIANT';f.querySelector('[name=phone]').value='771234568';f.querySelector('[name=email]').value='browser@example.invalid';f.querySelector('[name=consent_privacy]').checked=true;const files=new DataTransfer();files.items.add(new File(['%PDF-1.4\\nSynthetic browser fixture\\n%%EOF'], 'identity.pdf', {type:'application/pdf'}));f.querySelector('[name=photo]').files=files.files;f.submit();})()`);
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("location.pathname.includes('registration-details')"))break;}
    await check("location.pathname.includes('registration-details')",'browser submits registration to review');
    await check("!!document.querySelector('a[href=\"registration-preview.php?type=identite\"]')",'PDF identity preview is a usable protected document link');
    await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await screenshot('registration-review-mobile-light.png');
    await evaluate("document.querySelector('.theme-switcher').click()");
    await wait(300);
    await screenshot('registration-review-mobile-dark.png');
    await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await screenshot('registration-review-desktop-dark.png');
    await evaluate("document.querySelector('.theme-switcher').click()");
    await wait(300);
    await screenshot('registration-review-desktop-light.png');
    await check("document.documentElement.scrollWidth<=innerWidth",'review has no overflow');
    await cdp('Emulation.setDeviceMetricsOverride',{width:320,height:844,deviceScaleFactor:1,mobile:true});
    await check("document.documentElement.scrollWidth<=innerWidth",'review320px has no overflow');
    await evaluate("document.querySelector('form[action=\"registration-details.php\"]').submit()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("location.pathname==='/'"))break;}
    await check("!!document.querySelector('.public-flash')&&document.querySelector('.public-flash').textContent.includes('révision')",'confirmation gives member success feedback');
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
