/** Shared script boundary; browser coverage additionally verifies actual rendering. */
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/js/theme.js', import.meta.url), 'utf8');
function page({saved = null, system = 'dark', readFails = false, writeFails = false} = {}) {
    const attributes = new Map();
    const label = {textContent: ''};
    let click;
    let ready;
    const button = {querySelector: () => label, addEventListener: (_, listener) => {click = listener;}};
    const context = {
        localStorage: {
            getItem() {if (readFails) throw new Error('Simulated read failure'); return saved;},
            setItem(_, value) {if (writeFails) throw new Error('Simulated write failure'); saved = value;},
        },
        window: system === null ? {} : {matchMedia: () => ({matches: system === 'dark'})},
        document: {
            documentElement: {setAttribute: (key, value) => attributes.set(key, value), getAttribute: key => attributes.get(key)},
            addEventListener: (_, listener) => {ready = listener;},
            querySelectorAll: () => [button],
        },
    };
    vm.runInNewContext(source, context);
    const initialTheme = attributes.get('data-bs-theme');
    ready();
    return {initialTheme, theme: () => attributes.get('data-bs-theme'), label: () => label.textContent,
        saved: () => saved, toggle: () => click({preventDefault() {}})};
}

test('first visit uses light before DOM readiness for either system theme or no system API', () => {
    for (const system of ['light', 'dark', null]) assert.equal(page({system}).initialTheme, 'light');
});

test('only exact saved light and dark values override the light fallback', () => {
    for (const saved of ['light', 'dark']) assert.equal(page({saved}).initialTheme, saved);
    for (const saved of ['', 'system', 'Dark', ' dark ', 'invalid']) assert.equal(page({saved}).initialTheme, 'light');
});

test('simulated storage read failure still initializes light and allows switching', () => {
    const view = page({readFails: true});
    assert.equal(view.initialTheme, 'light');
    view.toggle();
    assert.equal(view.theme(), 'dark');
    assert.equal(view.label(), 'Mode clair');
});

test('simulated write failure preserves page-local switching and French target labels', () => {
    const view = page({writeFails: true});
    view.toggle();
    assert.equal(view.theme(), 'dark');
    assert.equal(view.label(), 'Mode clair');
    view.toggle();
    assert.equal(view.theme(), 'light');
    assert.equal(view.label(), 'Mode sombre');
});

test('explicit choices persist and initialize the next page', () => {
    const view = page();
    view.toggle();
    assert.equal(page({saved: view.saved()}).initialTheme, 'dark');
    view.toggle();
    assert.equal(page({saved: view.saved()}).initialTheme, 'light');
});
