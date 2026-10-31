/** Real Chrome journey against the disposable HTTP runner. No npm packages. */
import {spawn} from 'node:child_process';
import {mkdtemp, rm, mkdir, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
const [base, out] = process.argv.slice(2);
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

    const navigate=async route=>{
        const url=base+route;
        await cdp('Page.navigate',{url});
        for(let i=0;i<150;i++) {
            await wait(100);
            if(await evaluate(`location.href === ${JSON.stringify(url)} && document.readyState === 'complete' && !!document.querySelector('.theme-switcher')`)) return;
        }
        throw Error('page load timeout '+route);
    };
    const check=async(expression,label)=>{assert(await evaluate(expression),label);console.log('PASS browser:',label);};
    const key=async(key,code,keyCode)=>{
        await cdp('Input.dispatchKeyEvent',{type:'keyDown',key,code,windowsVirtualKeyCode:keyCode,...(key==='Enter'?{text:'\r'}:{})});
        await cdp('Input.dispatchKeyEvent',{type:'keyUp',key,code,windowsVirtualKeyCode:keyCode});
    };
    const screenshot=async name=>{
        const {data}=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
        await writeFile(path.join(out,name+'.png'),Buffer.from(data,'base64'));
    };
    const rendered=async(theme,label)=>{
        await check(`document.documentElement.dataset.bsTheme === '${theme}' && [...document.querySelectorAll('.theme-label')].every(label=>label.textContent === '${theme==='dark'?'Mode clair':'Mode sombre'}')`,label+' theme and French action');
        const colors=await evaluate(`({body:getComputedStyle(document.body).backgroundColor,text:getComputedStyle(document.body).color,scheme:getComputedStyle(document.documentElement).colorScheme})`);
        console.log('RENDER',label,JSON.stringify(colors));
        return colors;
    };
    await mkdir(out,{recursive:true});
    await cdp('Page.enable');
    await cdp('Emulation.setDeviceMetricsOverride',{width:1280,height:900,deviceScaleFactor:1,mobile:false});
    await navigate('/');
    for(const system of ['light','dark']) {
        await cdp('Emulation.setEmulatedMedia',{features:[{name:'prefers-color-scheme',value:system}]});
        await evaluate("localStorage.removeItem('theme')");
        await navigate('/');
        await rendered('light','fresh '+system+' system');
    }
    for(const saved of ['', 'invalid', 'system', 'Dark', ' dark ']) {
        await evaluate(`localStorage.setItem('theme',${JSON.stringify(saved)})`);
        await navigate('/');
        await rendered('light','invalid saved '+JSON.stringify(saved));
    }
    // Injected API/storage restrictions are simulations, not browser privacy settings.
    const simulations=[
        ['missing system API',"localStorage.removeItem('theme');window.matchMedia=undefined;"],
        ['storage read failure',"Storage.prototype.getItem=function(){throw new DOMException('Simulated read failure','SecurityError')};"],
        ['storage getter failure',"Object.defineProperty(window,'localStorage',{get(){throw new DOMException('Simulated getter failure','SecurityError')}});"],
        ['storage write failure',"localStorage.removeItem('theme');Storage.prototype.setItem=function(){throw new DOMException('Simulated write failure','QuotaExceededError')};"],
    ];
    for(const [name,source] of simulations) {
        const {identifier}=await cdp('Page.addScriptToEvaluateOnNewDocument',{source});
        await navigate('/');
        const light=await rendered('light','SIMULATION '+name);
        await evaluate("document.querySelector('.theme-switcher').focus()");
        await key('Enter','Enter',13);
        const dark=await rendered('dark','SIMULATION '+name+' keyboard switch');
        assert.notEqual(light.body,dark.body,'actual rendered background changes under '+name);
        await evaluate("document.querySelector('.theme-switcher').click()");
        await rendered('light','SIMULATION '+name+' reverse switch');
        await cdp('Page.removeScriptToEvaluateOnNewDocument',{identifier});
    }
    const verifySurface=async(name,route)=>{
        await evaluate("localStorage.removeItem('theme')");
        await navigate(route);
        await rendered('light',name+' unsaved dark system');
        const colors={};
        for(const theme of ['light','dark']) {
            await evaluate(`localStorage.setItem('theme','${theme}')`);
            await navigate(route);
            colors[theme]=await rendered(theme,name+' saved '+theme);
            await screenshot(name+'-'+theme);
            await navigate(route);
            await rendered(theme,name+' reload '+theme);
        }
        assert.notEqual(colors.light.body,colors.dark.body,name+' renders different light/dark backgrounds');
    };
    await verifySurface('authentication','/login.php');
    // Authenticate through the real disposable staff form.
    await navigate('/login.php');
    await evaluate("document.querySelector('[name=username]').value='fixture-admin';document.querySelector('[name=password]').value='fixture-password';document.querySelector('form').submit()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("location.pathname === '/dashboard.php' && document.readyState === 'complete'"))break;}
    await check("location.pathname === '/dashboard.php'",'synthetic staff login succeeds');
    for(const [name,route] of [['public','/'],['registration','/register.php'],['staff','/edit-student.php?id=1'],['member','/kyc-correction.php?token='+'a'.repeat(64)]]) await verifySurface(name,route);
    // Reach the one-use preview through its normal authorized form for each render.
    const previewColors={};
    for(const theme of ['light','dark']) {
        await evaluate(`localStorage.setItem('theme','${theme}')`);
        await navigate('/export.php');
        await evaluate("document.querySelector('[name=\"fields[]\"]').checked=true;document.querySelector('[name=export_format][value=pdf]').checked=true;document.querySelector('form').submit()");
        for(let i=0;i<100;i++){await wait(100);if(await evaluate("location.pathname === '/export-preview.php' && document.readyState === 'complete'"))break;}
        await check("location.pathname === '/export-preview.php'",'authorized one-use export preview');
        previewColors[theme]=await rendered(theme,'preview '+theme);
        await screenshot('preview-'+theme);
    }
    assert.notEqual(previewColors.light.body,previewColors.dark.body,'preview actual background changes');
    await navigate('/');
    await evaluate("localStorage.setItem('theme','light')");
    await navigate('/');
    for(let i=0;i<15;i++){await key('Tab','Tab',9);if(await evaluate("document.activeElement.classList.contains('theme-switcher')"))break;}
    await check("document.activeElement.classList.contains('theme-switcher') && getComputedStyle(document.activeElement).outlineStyle !== 'none'",'theme button reached by Tab with visible focus');
    await key('Enter','Enter',13);
    await rendered('dark','keyboard Enter');
    await navigate('/register.php');
    await rendered('dark','explicit keyboard selection survives navigation');
    await navigate('/');
    await rendered('dark','explicit keyboard selection survives reload');
    await evaluate("document.querySelector('.theme-switcher').focus()");
    await key(' ','Space',32);
    await rendered('light','keyboard Space');
    // The separate origin gets no preference despite a saved dark choice here.
    await evaluate("localStorage.setItem('theme','dark')");
    const otherBase=process.argv[4];
    assert(otherBase?.startsWith('http://127.0.0.1:'),'second disposable origin required');
    await cdp('Page.navigate',{url:otherBase+'/login.php'});
    for(let i=0;i<150;i++){await wait(100);if(await evaluate(`location.origin === ${JSON.stringify(otherBase)} && document.readyState === 'complete' && !!document.querySelector('.theme-switcher')`))break;}
    await rendered('light','independent origin');
    await check("localStorage.getItem('theme') === null",'preferences remain origin-scoped');
    // With scripts disabled, the unchanged stylesheet defaults still render light.
    await cdp('Emulation.setScriptExecutionDisabled',{value:true});
    await navigate('/');
    await check("!document.documentElement.hasAttribute('data-bs-theme')",'no-JavaScript fallback has no applied script theme');
    await check("getComputedStyle(document.body).backgroundColor === 'rgb(255, 250, 243)'",'no-JavaScript renders the existing light background');
    await screenshot('no-javascript-light');
    await cdp('Emulation.setScriptExecutionDisabled',{value:false});
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
