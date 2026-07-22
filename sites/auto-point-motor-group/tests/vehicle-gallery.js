const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class FakeClassList {
  constructor(classes = []) {
    this.classes = new Set(classes);
  }

  add(className) {
    this.classes.add(className);
  }

  remove(className) {
    this.classes.delete(className);
  }

  toggle(className, force) {
    if (force === true) this.add(className);
    else if (force === false) this.remove(className);
    else if (this.contains(className)) this.remove(className);
    else this.add(className);
  }

  contains(className) {
    return this.classes.has(className);
  }
}

class FakeElement {
  constructor({ classes = [], dataset = {} } = {}) {
    this.attributes = new Map();
    this.classList = new FakeClassList(classes);
    this.dataset = dataset;
    this.listeners = new Map();
    this.scrollCalls = [];
  }

  addEventListener(type, listener) {
    this.listeners.set(type, listener);
  }

  dispatch(type, properties = {}) {
    const event = {
      defaultPrevented: false,
      preventDefault() { this.defaultPrevented = true; },
      ...properties,
    };
    const listener = this.listeners.get(type);
    assert.ok(listener, `Expected a ${type} listener`);
    listener(event);
    return event;
  }

  getAttribute(name) {
    return this.attributes.get(name) ?? null;
  }

  setAttribute(name, value) {
    this.attributes.set(name, String(value));
  }

  scrollIntoView(options) {
    this.scrollCalls.push(options);
  }
}

const main = new FakeElement();
main.src = 'one.jpg';

const thumbnails = ['one.jpg', 'two.jpg', 'three.jpg'].map((image, index) => (
  new FakeElement({
    classes: index === 0 ? ['is-active'] : [],
    dataset: { galleryImage: image },
  })
));
const previous = new FakeElement();
const next = new FakeElement();
const gallery = new FakeElement();
const singleImageGallery = new FakeElement();
const singleImageMain = new FakeElement();
singleImageMain.src = 'only.jpg';

gallery.querySelector = (selector) => ({
  '[data-gallery-main]': main,
  '[data-gallery-prev]': previous,
  '[data-gallery-next]': next,
}[selector] ?? null);
gallery.querySelectorAll = (selector) => selector === '[data-gallery-image]' ? thumbnails : [];
singleImageGallery.querySelector = (selector) => selector === '[data-gallery-main]' ? singleImageMain : null;
singleImageGallery.querySelectorAll = () => [];

global.document = {
  querySelector: () => null,
  querySelectorAll: (selector) => selector === '[data-vehicle-gallery]' ? [gallery, singleImageGallery] : [],
};
global.window = {
  clearInterval: () => {},
  matchMedia: () => ({ matches: true }),
  setInterval: () => 0,
};

const script = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
vm.runInThisContext(script, { filename: 'assets/js/app.js' });

assert.equal(singleImageGallery.listeners.has('keydown'), false, 'A single photo gallery should not capture arrow keys');

thumbnails[1].dispatch('click');
assert.equal(main.src, 'two.jpg');
assert.equal(thumbnails[1].classList.contains('is-active'), true);
assert.equal(thumbnails[0].classList.contains('is-active'), false);
assert.equal(thumbnails[1].getAttribute('aria-current'), 'true');
assert.deepEqual(thumbnails[1].scrollCalls.at(-1), { behavior: 'auto', block: 'nearest', inline: 'center' });

next.dispatch('click');
assert.equal(main.src, 'three.jpg');
next.dispatch('click');
assert.equal(main.src, 'one.jpg', 'Next should wrap from the last photo to the first');

previous.dispatch('click');
assert.equal(main.src, 'three.jpg', 'Previous should wrap from the first photo to the last');

const keyboardEvent = gallery.dispatch('keydown', { key: 'ArrowLeft' });
assert.equal(main.src, 'two.jpg');
assert.equal(keyboardEvent.defaultPrevented, true);

console.log('Vehicle gallery behavior passed.');
