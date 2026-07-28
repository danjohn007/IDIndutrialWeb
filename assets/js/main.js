const header = document.querySelector('[data-header]');
const navToggle = document.querySelector('[data-nav-toggle]');
const navMenu = document.querySelector('[data-nav-menu]');
const navLinks = document.querySelectorAll('.nav-menu a');
const dropdown = document.querySelector('[data-dropdown]');
const dropdownToggle = document.querySelector('[data-dropdown-toggle]');
const revealItems = document.querySelectorAll('.reveal');
const counters = document.querySelectorAll('[data-count]');

function setHeaderState() {
  header?.classList.toggle('is-scrolled', window.scrollY > 20);
}

navToggle?.addEventListener('click', () => {
  const isOpen = navMenu.classList.toggle('is-open');
  navToggle.setAttribute('aria-expanded', String(isOpen));
  if (!isOpen) closeDropdown();
});

navLinks.forEach((link) => {
  link.addEventListener('click', () => {
    navMenu.classList.remove('is-open');
    navToggle?.setAttribute('aria-expanded', 'false');
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
  const suffix = target === 20 ? '+' : target === 24 ? '/7' : target === 100 ? '%' : '';
  const duration = 1200;
  const start = performance.now();

  function tick(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    el.textContent = `${Math.round(target * eased)}${suffix}`;
    if (progress < 1) requestAnimationFrame(tick);
  }

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

document.addEventListener('mousemove', (event) => {
  document.documentElement.style.setProperty('--cursor-x', `${event.clientX}px`);
  document.documentElement.style.setProperty('--cursor-y', `${event.clientY}px`);
});

window.addEventListener('scroll', setHeaderState, { passive: true });
setHeaderState();
