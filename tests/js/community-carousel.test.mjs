import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import {runInNewContext} from 'node:vm';

const script = readFileSync(new URL('../../assets/js/community-carousel.js', import.meta.url), 'utf8');

function carousel({legacy = false, reduced = false} = {}) {
    function element() {
        const events = new Map();
        return {
            hidden: false,
            textContent: '',
            attributes: {},
            setAttribute(name, value) { this.attributes[name] = value; },
            addEventListener(name, callback) { events.set(name, callback); },
            emit(name, event = {}) { events.get(name)?.(event); },
        };
    }

    const slides = Array.from({length: 4}, (_, index) => ({
        hidden: index !== 0,
        querySelector: () => ({alt: 'Photo'}),
    }));
    const gallery = element();
    const controls = element();
    const count = element();
    count.textContent = '1 / 4';
    const pause = element();
    const next = element();
    const elements = {
        '.gallery-controls': controls,
        '[data-carousel-pause]': pause,
        '[data-carousel-count]': count,
        '.carousel-announcement': element(),
        '[data-carousel-prev]': element(),
        '[data-carousel-next]': next,
    };
    gallery.querySelector = selector => elements[selector];
    gallery.querySelectorAll = () => slides;
    const document = Object.assign(element(), {hidden: false, querySelectorAll: () => [gallery]});
    let onMotionChange;
    const motion = {matches: reduced};
    if (legacy) {
        motion.addListener = callback => { onMotionChange = callback; };
    } else {
        motion.addEventListener = (name, callback) => {
            assert.equal(name, 'change');
            onMotionChange = callback;
        };
    }
    let interval;
    const window = {
        matchMedia: () => motion,
        setInterval: callback => { interval = callback; return 1; },
        clearInterval: () => { interval = undefined; },
    };

    runInNewContext(script, {document, window});

    return {
        gallery, pause, next, count, slides,
        tick() { interval?.(); },
        reduceMotion() { onMotionChange({matches: true}); },
    };
}

test('explicit resume advances until the next hover, which still pauses rotation', () => {
    const {gallery, pause, count, slides, tick} = carousel();
    gallery.emit('mouseenter');
    tick();
    assert.equal(count.textContent, '1 / 4');
    pause.emit('click');
    tick();
    assert.equal(count.textContent, '1 / 4');

    pause.emit('click');
    tick();
    assert.equal(count.textContent, '2 / 4');
    assert.deepEqual(slides.map(slide => slide.hidden), [true, false, true, true]);

    gallery.emit('mouseleave');
    gallery.emit('mouseenter');
    tick();
    assert.equal(count.textContent, '2 / 4');
    gallery.emit('mouseleave');
    tick();
    assert.equal(count.textContent, '3 / 4');
});

test('autoplay starts with legacy media-query listeners and pauses for reduced motion', () => {
    const {count, pause, tick, reduceMotion} = carousel({legacy: true});
    tick();
    assert.equal(count.textContent, '2 / 4');
    reduceMotion();
    tick();
    assert.equal(count.textContent, '2 / 4');
    assert.equal(pause.attributes['aria-pressed'], 'true');
});

test('reduced motion starts paused with manual navigation available', () => {
    const {count, pause, next, tick} = carousel({reduced: true});
    tick();
    assert.equal(count.textContent, '1 / 4');
    assert.equal(pause.attributes['aria-pressed'], 'true');
    next.emit('click');
    assert.equal(count.textContent, '2 / 4');
});
