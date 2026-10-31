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
    const navigate=async url=>{await cdp('Page.navigate',{url});for(let i=0;i<100;i++){await wait(100);if(await evaluate("document.readyState === 'complete' && location.href === " + JSON.stringify(url)))return;}throw Error('page load timeout '+url);};
    const check=async (expression,label)=>{assert(await evaluate(expression),label);console.log('PASS browser:',label);};
    await cdp('Page.enable');
    await cdp('Network.enable');
    await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await navigate(base+'/register.php');
    await check("!!document.querySelector('#registrationForm')", 'public HTTPS registration renders');
    await navigate(staff+'/login.php');
    await check("!!document.querySelector('[name=username]')", 'staff HTTPS login renders');
    const {cookies}=await cdp('Network.getAllCookies');
    const publicCookie=cookies.find(c=>c.name==='AMEA_PUBLIC');
    const staffCookie=cookies.find(c=>c.name==='AMEA_STAFF');
    assert(publicCookie?.secure && publicCookie.httpOnly && publicCookie.sameSite==='Strict' && publicCookie.domain==='public.amea.test');
    assert(staffCookie?.secure && staffCookie.httpOnly && staffCookie.sameSite==='Strict' && staffCookie.domain==='staff.amea.test');
    console.log('PASS browser: distinct Secure host-only cookies established by actual TLS browser');
    await check("!document.cookie.includes('AMEA_')", 'session cookies inaccessible to JavaScript');
    const sent=await evaluate("fetch('/login.php').then(r=>r.text()).then(t=>t.includes('Connexion Administration'))");
    assert(sent);console.log('PASS browser: public session does not authenticate staff');
    await evaluate("document.querySelector('[name=username]').value='expired';document.querySelector('[name=password]').value='Fixture-password-123!';document.querySelector('form').submit()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("document.body?.innerText.includes('Tableau de bord')"))break;}
    await check("document.body?.innerText.includes('Tableau de bord')", 'real browser staff login succeeds');
    await mkdir(out,{recursive:true});
    let shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'staff-dashboard.png'),Buffer.from(shot.data,'base64'));
    await navigate(staff+'/students.php?search=SharedMember');
    await check("document.body?.innerText.includes('SharedMember')", 'browser member listing displays shared dossier');
    const memberLink=await evaluate("document.querySelector('a[href*=\"student-details.php?id=\"]').getAttribute('href')");
    await navigate(staff+'/'+memberLink);
    await check("document.body?.innerText.includes('StaffEdited')", 'browser opens corrected and edited member detail');
    shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'staff-member-detail.png'),Buffer.from(shot.data,'base64'));
    await navigate(base+'/register.php');
    await check("!!document.querySelector('#registrationForm')", 'authenticated staff browser retains independent public journey');
    await check("!document.body?.innerText.includes('Tableau de bord')", 'staff state does not replace public registration');
    shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'public-registration-isolated.png'),Buffer.from(shot.data,'base64'));
    // Internal staff login returns remain; old public staff URLs fail locally.
    await cdp('Page.navigate',{url:staff+'/logout.php'});
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("location.pathname === '/login.php' && !!document.querySelector('[name=username]')"))break;}
    await navigate(staff+'/login.php?return_to='+encodeURIComponent('/students.php?search=Legacy'));
    await evaluate("document.querySelector('[name=username]').value='expired';document.querySelector('[name=password]').value='Fixture-password-123!';document.querySelector('form').submit()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("location.pathname === '/students.php' && document.body?.innerText.includes('Legacy')"))break;}
    await check("location.pathname === '/students.php' && new URL(location.href).searchParams.get('search') === 'Legacy'", 'browser login returns to vetted member listing');
    shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'staff-login-return.png'),Buffer.from(shot.data,'base64'));
    await navigate(base+'/');
    await evaluate("(() => {const form=document.createElement('form');form.method='POST';form.action='/students.php';document.body.append(form);form.submit();})()");
    for(let i=0;i<100;i++){await wait(100);if(await evaluate("document.body?.innerText.includes('Page introuvable.')"))break;}
    await check("location.origin === " + JSON.stringify(base) + " && document.body?.innerText.includes('Page introuvable.')", 'browser old staff submission stays on public and is rejected');
    await check("!document.querySelector('a')", 'public rejection offers no staff recovery link');
    shot=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(path.join(out,'public-staff-route-denied.png'),Buffer.from(shot.data,'base64'));

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
