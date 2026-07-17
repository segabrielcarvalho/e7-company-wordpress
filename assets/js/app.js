const menuToggle = document.querySelector('[data-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');
const siteHeader = document.querySelector('[data-site-header]');

if (menuToggle && mobileMenu) {
  menuToggle.addEventListener('click', () => {
    const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!isOpen));
    mobileMenu.classList.toggle('hidden', isOpen);
  });

  mobileMenu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      menuToggle.setAttribute('aria-expanded', 'false');
      mobileMenu.classList.add('hidden');
    });
  });
}

const updateHeader = () => {
  if (!siteHeader) return;
  siteHeader.classList.toggle('is-scrolled', window.scrollY > 24);
};

updateHeader();
window.addEventListener('scroll', updateHeader, { passive: true });

const canvas = document.querySelector('[data-hero-dot-canvas]');
const dotContainer = canvas?.parentElement;

if (canvas && dotContainer) {
  const context = canvas.getContext('2d');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const interactiveDots = !reducedMotion && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  const frameInterval = 1000 / 30;
  const mouse = { x: -1000, y: -1000 };
  const previousMouse = { x: -1000, y: -1000 };
  const mouseVelocity = { x: 0, y: 0 };
  const dots = [];
  const mouseRadius = 110;
  const distortionStrength = 1.2;
  const returnSpeed = 0.06;
  let dotGap = 18;
  let dotSize = 1.1;
  let width = 0;
  let height = 0;
  let animationFrame = 0;
  let previousTimestamp = 0;
  let pointerStarted = false;

  const initializeDots = () => {
    dots.length = 0;
    dotGap = width < 640 ? 15 : 18;
    dotSize = width < 640 ? 0.9 : 1.1;
    const columns = Math.ceil(width / dotGap) + 2;
    const rows = Math.ceil(height / dotGap) + 2;
    const offsetX = (width % dotGap) / 2;
    const offsetY = (height % dotGap) / 2;

    for (let column = 0; column < columns; column += 1) {
      for (let row = 0; row < rows; row += 1) {
        const x = column * dotGap + offsetX;
        const y = row * dotGap + offsetY;
        const noise =
          Math.sin(column * 0.3 + row * 0.2) * 0.3 +
          Math.sin(column * 0.7 - row * 0.5) * 0.2 +
          Math.sin((column + row) * 0.4) * 0.2 +
          Math.random() * 0.3;

        dots.push({
          x,
          y,
          baseX: x,
          baseY: y,
          velocityX: 0,
          velocityY: 0,
          brightness: Math.max(0.1, Math.min(1, 0.3 + noise)),
          phase: Math.random() * Math.PI * 2,
          breathingSpeed: 0.5 + Math.random() * 0.5,
          glow: 0,
          glowTarget: 0,
          nextGlow: Math.random() * 3,
        });
      }
    }
  };

  const resizeCanvas = () => {
    const bounds = dotContainer.getBoundingClientRect();
    const pixelRatio = Math.min(2, Math.max(1, window.devicePixelRatio || 1));
    width = bounds.width;
    height = bounds.height;
    canvas.width = Math.floor(width * pixelRatio);
    canvas.height = Math.floor(height * pixelRatio);
    canvas.style.width = `${Math.floor(width)}px`;
    canvas.style.height = `${Math.floor(height)}px`;
    context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
    initializeDots();

    if (!interactiveDots) drawDots(0);
  };

  const handlePointerMove = (event) => {
    const bounds = dotContainer.getBoundingClientRect();
    const x = event.clientX - bounds.left;
    const y = event.clientY - bounds.top;

    if (x < 0 || x > bounds.width || y < 0 || y > bounds.height) {
      mouse.x = -1000;
      mouse.y = -1000;
      mouseVelocity.x = 0;
      mouseVelocity.y = 0;
      pointerStarted = false;
      return;
    }

    if (!pointerStarted) {
      previousMouse.x = x;
      previousMouse.y = y;
      pointerStarted = true;
    }

    mouse.x = x;
    mouse.y = y;
    mouseVelocity.x = mouse.x - previousMouse.x;
    mouseVelocity.y = mouse.y - previousMouse.y;
    previousMouse.x = mouse.x;
    previousMouse.y = mouse.y;
  };

  function drawDots(timestamp) {
    if (interactiveDots && previousTimestamp && timestamp - previousTimestamp < frameInterval) {
      animationFrame = window.requestAnimationFrame(drawDots);
      return;
    }

    const delta = previousTimestamp ? Math.min((timestamp - previousTimestamp) / 16.67, 1.5) : 1;
    const time = timestamp * 0.001;
    const velocityMagnitude = Math.hypot(mouseVelocity.x, mouseVelocity.y);
    previousTimestamp = timestamp;
    context.clearRect(0, 0, width, height);

    dots.forEach((dot) => {
      if (interactiveDots && time >= dot.nextGlow) {
        if (dot.glowTarget === 0) {
          dot.glowTarget = 0.55 + Math.random() * 0.45;
          dot.nextGlow = time + 2 + Math.random() * 3;
        } else {
          dot.glowTarget = 0;
          dot.nextGlow = time + 1 + Math.random() * 4;
        }
      }

      dot.glow += (dot.glowTarget - dot.glow) * 0.018 * delta;

      if (interactiveDots) {
        const distanceX = mouse.x - dot.baseX;
        const distanceY = mouse.y - dot.baseY;
        const distance = Math.hypot(distanceX, distanceY);

        if (distance < mouseRadius && velocityMagnitude > 0.5) {
          const falloff = 1 - distance / mouseRadius;
          const strength = falloff * falloff * distortionStrength;
          dot.velocityX += mouseVelocity.x * strength * 0.3;
          dot.velocityY += mouseVelocity.y * strength * 0.3;
        }

        dot.x += dot.velocityX * delta;
        dot.y += dot.velocityY * delta;
        dot.x += (dot.baseX - dot.x) * returnSpeed * delta;
        dot.y += (dot.baseY - dot.y) * returnSpeed * delta;
        dot.velocityX = dot.velocityX * 0.92 + (dot.baseX - dot.x) * 0.02 * delta;
        dot.velocityY = dot.velocityY * 0.92 + (dot.baseY - dot.y) * 0.02 * delta;
      }

      const breathing = interactiveDots ? Math.sin(time * dot.breathingSpeed + dot.phase) * 0.15 : 0;
      const displacement = Math.hypot(dot.x - dot.baseX, dot.y - dot.baseY);
      const brightness = Math.min(1, Math.max(0.06, dot.brightness + breathing + Math.min(0.5, displacement * 0.05) + dot.glow * 0.7));
      const shouldGlow = brightness > 0.58 || dot.glow > 0.12;

      context.shadowColor = shouldGlow ? '#3b82f6' : 'transparent';
      context.shadowBlur = shouldGlow ? 6 + 15 * Math.max(brightness, dot.glow) : 0;
      context.globalAlpha = 0.15 + brightness * 0.72;
      context.fillStyle = '#60a5fa';
      context.beginPath();
      context.arc(dot.x, dot.y, dotSize + dot.glow * 0.45, 0, Math.PI * 2);
      context.fill();
    });

    context.globalAlpha = 1;
    mouseVelocity.x *= 0.9;
    mouseVelocity.y *= 0.9;

    if (interactiveDots) animationFrame = window.requestAnimationFrame(drawDots);
  }

  const resizeObserver = new ResizeObserver(resizeCanvas);
  resizeObserver.observe(dotContainer);
  if (interactiveDots) window.addEventListener('pointermove', handlePointerMove, { passive: true });
  resizeCanvas();

  if (interactiveDots) animationFrame = window.requestAnimationFrame(drawDots);

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      window.cancelAnimationFrame(animationFrame);
      return;
    }

    previousTimestamp = 0;
    if (interactiveDots) animationFrame = window.requestAnimationFrame(drawDots);
  });

  window.addEventListener('pagehide', () => {
    window.cancelAnimationFrame(animationFrame);
    window.removeEventListener('pointermove', handlePointerMove);
    resizeObserver.disconnect();
  }, { once: true });
}

const approachTabs = [...document.querySelectorAll('[data-approach-tab]')];
const approachPanels = [...document.querySelectorAll('[data-approach-panel]')];

const activateApproachTab = (selectedTab, moveFocus = false) => {
  const selectedId = selectedTab.dataset.approachTab;

  approachTabs.forEach((tab) => {
    const isActive = tab === selectedTab;
    tab.classList.toggle('is-active', isActive);
    tab.setAttribute('aria-selected', String(isActive));
    tab.tabIndex = isActive ? 0 : -1;
  });

  approachPanels.forEach((panel) => {
    const isActive = panel.dataset.approachPanel === selectedId;
    panel.hidden = !isActive;
    panel.classList.toggle('is-active', isActive);
  });

  if (moveFocus) selectedTab.focus();
};

approachTabs.forEach((tab, index) => {
  tab.tabIndex = tab.classList.contains('is-active') ? 0 : -1;

  tab.addEventListener('click', () => activateApproachTab(tab));
  tab.addEventListener('keydown', (event) => {
    if (!['ArrowDown', 'ArrowUp', 'ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();

    let nextIndex = index;
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = approachTabs.length - 1;
    if (['ArrowDown', 'ArrowRight'].includes(event.key)) nextIndex = (index + 1) % approachTabs.length;
    if (['ArrowUp', 'ArrowLeft'].includes(event.key)) nextIndex = (index - 1 + approachTabs.length) % approachTabs.length;

    activateApproachTab(approachTabs[nextIndex], true);
  });
});

const technologyTabs = [...document.querySelectorAll('[data-technology-tab]')];
const technologyPanels = [...document.querySelectorAll('[data-technology-panel]')];

const activateTechnologyTab = (selectedTab, moveFocus = false) => {
  const selectedId = selectedTab.dataset.technologyTab;

  technologyTabs.forEach((tab) => {
    const isActive = tab === selectedTab;
    tab.classList.toggle('is-active', isActive);
    tab.setAttribute('aria-selected', String(isActive));
    tab.tabIndex = isActive ? 0 : -1;
  });

  technologyPanels.forEach((panel) => {
    const isActive = panel.dataset.technologyPanel === selectedId;
    panel.hidden = !isActive;
    panel.classList.toggle('is-active', isActive);
  });

  if (moveFocus) selectedTab.focus();
};

technologyTabs.forEach((tab, index) => {
  tab.tabIndex = tab.classList.contains('is-active') ? 0 : -1;

  tab.addEventListener('click', () => activateTechnologyTab(tab));
  tab.addEventListener('keydown', (event) => {
    if (!['ArrowDown', 'ArrowUp', 'ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();

    let nextIndex = index;
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = technologyTabs.length - 1;
    if (['ArrowDown', 'ArrowRight'].includes(event.key)) nextIndex = (index + 1) % technologyTabs.length;
    if (['ArrowUp', 'ArrowLeft'].includes(event.key)) nextIndex = (index - 1 + technologyTabs.length) % technologyTabs.length;

    activateTechnologyTab(technologyTabs[nextIndex], true);
  });
});
