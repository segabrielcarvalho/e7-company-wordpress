(() => {
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
  const navToggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.primary-nav');
  navToggle?.addEventListener('click', () => {
    const open = navToggle.getAttribute('aria-expanded') === 'true';
    navToggle.setAttribute('aria-expanded', String(!open));
    nav?.classList.toggle('is-open', !open);
  });

  document.querySelectorAll('.faq-button').forEach((button) => {
    button.addEventListener('click', () => {
      const expanded = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', String(!expanded));
      button.nextElementSibling?.classList.toggle('hidden', expanded);
      const icon = button.querySelector('span:last-child');
      if (icon) icon.textContent = expanded ? '+' : '−';
    });
  });

  document.querySelectorAll('form[action="#"]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      if (!button) return;
      const original = button.textContent;
      button.textContent = 'Thank you!';
      window.setTimeout(() => { button.textContent = original; form.reset(); }, 2200);
    });
  });

  document.querySelectorAll('[data-inventory-sort]').forEach((select) => {
    select.addEventListener('change', () => {
      const url = new URL(window.location.href);
      url.searchParams.set('orderby', select.value);
      url.searchParams.delete('vehicle_page');
      url.hash = 'inventory';
      window.location.assign(url.toString());
    });
  });

  document.querySelectorAll('.catalog-filters').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]');
      form.classList.add('is-submitting');
      button?.setAttribute('aria-busy', 'true');
    });
  });

  document.querySelectorAll('[data-vehicle-gallery]').forEach((gallery) => {
    const main = gallery.querySelector('[data-gallery-main]');
    const thumbnails = [...gallery.querySelectorAll('[data-gallery-image]')];
    const previous = gallery.querySelector('[data-gallery-prev]');
    const next = gallery.querySelector('[data-gallery-next]');
    if (!main || thumbnails.length < 2) return;
    const scrollBehavior = reducedMotion ? 'auto' : 'smooth';
    let current = Math.max(0, thumbnails.findIndex((thumbnail) => thumbnail.classList.contains('is-active')));

    main.addEventListener('animationend', () => {
      main.classList.remove('is-switching-next', 'is-switching-previous');
    });

    const showImage = (index, shouldScroll = true, requestedDirection = '') => {
      if (!main || !thumbnails.length) return;
      const previousIndex = current;
      current = (index + thumbnails.length) % thumbnails.length;
      const activeThumbnail = thumbnails[current];
      if (!reducedMotion && shouldScroll && current !== previousIndex) {
        const direction = requestedDirection || (current > previousIndex ? 'next' : 'previous');
        main.classList.remove('is-switching-next', 'is-switching-previous');
        void main.offsetWidth;
        main.classList.add(direction === 'previous' ? 'is-switching-previous' : 'is-switching-next');
      }
      main.src = activeThumbnail.dataset.galleryImage || main.src;
      thumbnails.forEach((thumbnail, thumbnailIndex) => {
        const isActive = thumbnailIndex === current;
        thumbnail.classList.toggle('is-active', isActive);
        thumbnail.setAttribute('aria-current', String(isActive));
      });
      if (shouldScroll) {
        activeThumbnail.scrollIntoView({ behavior: scrollBehavior, block: 'nearest', inline: 'center' });
      }
    };

    thumbnails.forEach((button, index) => {
      button.addEventListener('click', () => showImage(index));
    });
    previous?.addEventListener('click', () => showImage(current - 1, true, 'previous'));
    next?.addEventListener('click', () => showImage(current + 1, true, 'next'));
    gallery.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      const direction = event.key === 'ArrowLeft' ? 'previous' : 'next';
      showImage(current + (direction === 'previous' ? -1 : 1), true, direction);
    });
    showImage(current, false);
  });
})();
