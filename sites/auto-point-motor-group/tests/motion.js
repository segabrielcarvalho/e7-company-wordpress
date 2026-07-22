const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class FakeClassList {
  constructor(classes = []) { this.classes = new Set(classes); }
  add(className) { this.classes.add(className); }
  remove(className) { this.classes.delete(className); }
  contains(className) { return this.classes.has(className); }
  toggle(className, force) {
    if (force === true) this.add(className);
    else if (force === false) this.remove(className);
    else if (this.contains(className)) this.remove(className);
    else this.add(className);
  }
}

class FakeElement {
  constructor() {
    this.attributes = new Map();
    this.classList = new FakeClassList();
    this.listeners = new Map();
    this.textContent = '';
  }
  addEventListener(type, listener) { this.listeners.set(type, listener); }
  dispatch(type, properties = {}) {
    const event = { preventDefault() {}, ...properties };
    const listener = this.listeners.get(type);
    assert.ok(listener, `Expected a ${type} listener`);
    listener(event);
  }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  querySelector() { return null; }
  querySelectorAll() { return []; }
}

const script = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
const motionCss = fs.readFileSync(path.join(__dirname, '../src/input.css'), 'utf8');
const catalogForm = new FakeElement();
const catalogButton = new FakeElement();
catalogButton.textContent = 'Search Cars';
catalogForm.querySelector = (selector) => selector === 'button[type="submit"]' ? catalogButton : null;

const document = {
  documentElement: new FakeElement(),
  querySelector: () => null,
  querySelectorAll: (selector) => selector === '.catalog-filters' ? [catalogForm] : [],
};
const window = {
  clearInterval: () => {},
  setInterval: () => 0,
  setTimeout: (callback) => callback(),
  matchMedia: () => ({ matches: false }),
  location: { href: 'https://example.test/', assign: () => {} },
};

vm.runInNewContext(script, { document, window, URL }, { filename: 'assets/js/app.js' });

assert.doesNotMatch(script, /IntersectionObserver|motion-reveal|is-revealed/, 'Viewport reveal animations must be removed');
assert.doesNotMatch(motionCss, /motion-reveal-in|\.motion-enabled \.motion-reveal/, 'CSS must never hide content for entry reveals');
assert.doesNotMatch(script, /hero-slide/, 'The unused fade slideshow must not remain active');
assert.doesNotMatch(motionCss, /\.hero-slide\s*\{[^}]*opacity:\s*0/s, 'No hero content may depend on a fade to become visible');

catalogForm.dispatch('submit');
assert.equal(catalogForm.classList.contains('is-submitting'), true, 'Filtering should show immediate motion feedback');
assert.equal(catalogButton.getAttribute('aria-busy'), 'true');

assert.match(motionCss, /\.inventory-card::after\s*\{[^}]*linear-gradient/s, 'Cards should use an interactive light sweep');
assert.match(motionCss, /\.inventory-card\s*\{[^}]*position: relative/s, 'The card must contain its light sweep');
assert.match(motionCss, /\.inventory-card:hover::after\s*\{[^}]*transform:/s);
assert.match(motionCss, /\.inventory-card__cta:hover i\s*\{[^}]*transform: translateX/s, 'CTA arrow should travel on hover');
assert.match(motionCss, /\.buying-grid > article:hover > i\s*\{[^}]*animation:/s, 'Benefit icons should react instead of fading in');
assert.match(motionCss, /@keyframes gallery-slide-next/, 'Gallery changes should slide horizontally');
assert.match(motionCss, /@keyframes filter-searching/, 'Filter submission should have an activity animation');
assert.match(motionCss, /@media \(prefers-reduced-motion: reduce\)/);
assert.match(motionCss, /scroll-behavior: auto/);
assert.match(motionCss, /\.catalog-filters\.is-submitting button[^}]*animation: none/s, 'Reduced motion must disable filter activity');

console.log('Interactive motion behavior passed.');
