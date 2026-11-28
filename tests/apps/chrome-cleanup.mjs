import {rm} from 'node:fs/promises';

export async function closeChrome(browser, directory, ws, shutdown) {
    if (browser.pid && browser.exitCode === null && browser.signalCode === null) {
        const exited = new Promise(resolve => browser.once('exit', resolve));
        if (shutdown) {
            const timer = AbortSignal.timeout(1500);
            void shutdown().catch(() => {});
            await Promise.race([exited, new Promise(resolve => {
                timer.addEventListener('abort', resolve, {once: true});
            })]);
        }
        if (browser.exitCode === null && browser.signalCode === null) {
            browser.kill('SIGKILL');
            await exited;
        }
    }
    ws?.close();
    browser.stderr.destroy();
    // Chrome helpers can finish writing profile files after the main process exits.
    await rm(directory, {recursive: true, force: true, maxRetries: 5, retryDelay: 200});
}
