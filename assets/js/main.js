document.documentElement.classList.add('js');

const header = document.querySelector('[data-header]');
const navToggle = document.querySelector('[data-nav-toggle]');
const navMenu = document.querySelector('[data-nav-menu]');
const navLinks = document.querySelectorAll('.nav-menu a');
const dropdown = document.querySelector('[data-dropdown]');
const dropdownToggle = document.querySelector('[data-dropdown-toggle]');
const revealItems = document.querySelectorAll('.reveal');
const counters = document.querySelectorAll('[data-count]');
const carousels = document.querySelectorAll('[data-carousel]');

let scrollTicking = false;

function setHeaderState() {
  header?.classList.toggle('is-scrolled', window.scrollY > 20);
}

function syncMobileMenuState(forceState) {
  const isOpen = typeof forceState === 'boolean' ? forceState : Boolean(navMenu?.classList.contains('is-open'));
  navMenu?.classList.toggle('is-open', isOpen);
  header?.classList.toggle('has-menu-open', isOpen);
  document.body?.classList.toggle('has-nav-open', isOpen);
  navToggle?.setAttribute('aria-expanded', String(isOpen));
  navToggle?.setAttribute('aria-label', isOpen ? 'Cerrar menu de navegacion' : 'Abrir menu de navegacion');
  if (!isOpen) closeDropdown();
}

navToggle?.addEventListener('click', () => {
  const isOpen = navMenu.classList.toggle('is-open');
  navToggle.setAttribute('aria-expanded', String(isOpen));
  navToggle.setAttribute('aria-label', isOpen ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
  if (!isOpen) closeDropdown();
});

navLinks.forEach((link) => {
  link.addEventListener('click', () => {
    navMenu.classList.remove('is-open');
    navToggle?.setAttribute('aria-expanded', 'false');
    navToggle?.setAttribute('aria-label', 'Abrir menú de navegación');
    closeDropdown();
  });
});

function closeDropdown() {
  dropdown?.classList.remove('is-open');
  dropdownToggle?.setAttribute('aria-expanded', 'false');
}

dropdownToggle?.addEventListener('click', (event) => {
  event.stopPropagation();
  const isOpen = dropdown.classList.toggle('is-open');
  dropdownToggle.setAttribute('aria-expanded', String(isOpen));
});

document.addEventListener('click', (event) => {
  if (!dropdown?.contains(event.target)) closeDropdown();
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') closeDropdown();
});

navToggle?.addEventListener('click', () => requestAnimationFrame(() => syncMobileMenuState()));

navLinks.forEach((link) => {
  link.addEventListener('click', () => syncMobileMenuState(false));
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') syncMobileMenuState(false);
});

const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.16 });

revealItems.forEach((item) => revealObserver.observe(item));

function animateCounter(el) {
  const target = Number(el.dataset.count || 0);
  const suffix = el.dataset.suffix || '';
  const finalText = el.textContent;
  const duration = 1200;
  const start = performance.now();

  function tick(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    el.textContent = `${Math.round(target * eased)}${suffix}`;
    if (progress < 1) requestAnimationFrame(tick);
    else el.textContent = finalText;
  }

  el.textContent = `0${suffix}`;
  requestAnimationFrame(tick);
}

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      counterObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.7 });

counters.forEach((counter) => counterObserver.observe(counter));

carousels.forEach((carousel) => {
  const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
  const dots = Array.from(carousel.querySelectorAll('[data-carousel-dot]'));
  const prev = carousel.querySelector('[data-carousel-prev]');
  const next = carousel.querySelector('[data-carousel-next]');
  let current = 0;

  function loadSlide(slide) {
    const image = slide?.querySelector('img[data-src]');
    if (image) {
      if (image.dataset.srcset) {
        image.srcset = image.dataset.srcset;
        image.removeAttribute('data-srcset');
      }
      image.src = image.dataset.src;
      image.removeAttribute('data-src');
    }

    if (slide?.dataset.bg && !slide.style.getPropertyValue('--slide-image')) {
      slide.style.setProperty('--slide-image', `url('${slide.dataset.bg}')`);
    }

    if (slide?.dataset.bgMobile && !slide.style.getPropertyValue('--slide-image-mobile')) {
      slide.style.setProperty('--slide-image-mobile', `url('${slide.dataset.bgMobile}')`);
    }
  }

  function showSlide(index) {
    if (!slides.length) return;
    current = (index + slides.length) % slides.length;
    loadSlide(slides[current]);
    slides.forEach((slide, slideIndex) => {
      const isActive = slideIndex === current;
      slide.classList.toggle('is-active', isActive);
      slide.setAttribute('aria-hidden', String(!isActive));
    });
    dots.forEach((dot, dotIndex) => {
      const isActive = dotIndex === current;
      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-current', String(isActive));
    });
  }

  loadSlide(slides[current]);
  prev?.addEventListener('click', () => showSlide(current - 1));
  next?.addEventListener('click', () => showSlide(current + 1));
  dots.forEach((dot) => {
    dot.addEventListener('click', () => showSlide(Number(dot.dataset.carouselDot || 0)));
  });
  carousel.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') showSlide(current - 1);
    if (event.key === 'ArrowRight') showSlide(current + 1);
  });
});

window.addEventListener('scroll', () => {
  if (scrollTicking) return;
  scrollTicking = true;
  requestAnimationFrame(() => {
    setHeaderState();
    scrollTicking = false;
  });
}, { passive: true });
setHeaderState();

document.querySelectorAll('[data-contact-form]').forEach((form) => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('[type="submit"]');
    if (!button) return;
    button.disabled = true;
    button.dataset.originalText = button.textContent;
    button.textContent = 'Enviando solicitud...';
  });
});

const quoteModal = document.querySelector('[data-quote-modal]');

if (quoteModal) {
  const quoteOpeners = document.querySelectorAll('[data-quote-open]');
  const quoteClosers = quoteModal.querySelectorAll('[data-quote-close]');
  const quoteServiceField = quoteModal.querySelector('[data-quote-service-field]');
  const quoteFirstField = quoteModal.querySelector('input:not([type="hidden"]), select, textarea, button');
  let quoteLastFocus = null;

  function openQuoteModal(service = '') {
    quoteLastFocus = document.activeElement;
    if (service && quoteServiceField) {
      const matchingOption = Array.from(quoteServiceField.options).find((option) => option.value === service);
      if (matchingOption) quoteServiceField.value = service;
    }
    quoteModal.classList.add('is-open');
    quoteModal.setAttribute('aria-hidden', 'false');
    document.body?.classList.add('has-quote-modal-open');
    window.requestAnimationFrame(() => quoteFirstField?.focus());
  }

  function closeQuoteModal() {
    quoteModal.classList.remove('is-open');
    quoteModal.setAttribute('aria-hidden', 'true');
    document.body?.classList.remove('has-quote-modal-open');
    if (window.location.hash === '#cotizacion') {
      history.replaceState(null, '', `${window.location.pathname}${window.location.search}`);
    }
    if (quoteLastFocus instanceof HTMLElement) quoteLastFocus.focus();
  }

  quoteOpeners.forEach((opener) => {
    opener.addEventListener('click', (event) => {
      event.preventDefault();
      openQuoteModal(opener.dataset.quoteService || '');
    });
  });

  quoteClosers.forEach((closer) => closer.addEventListener('click', closeQuoteModal));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && quoteModal.classList.contains('is-open')) {
      closeQuoteModal();
    }
  });

  if (quoteModal.classList.contains('is-open') || window.location.hash === '#cotizacion') {
    openQuoteModal();
  }
}
