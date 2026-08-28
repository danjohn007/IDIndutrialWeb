(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion || typeof Element.prototype.animate !== 'function') return;

  document.body.classList.add('motion-enabled');
  const targets = Array.from(document.querySelectorAll(
    'main > header, main > section, main > article'
  ));
  const reveal = (element, index) => {
    if (element.dataset.motionShown === '1') return;
    element.dataset.motionShown = '1';
    element.animate(
      [
        { opacity: 0, transform: 'translateY(12px)' },
        { opacity: 1, transform: 'translateY(0)' },
      ],
      {
        delay: Math.min(index * 45, 180),
        duration: 380,
        easing: 'cubic-bezier(.2,.75,.25,1)',
        fill: 'backwards',
      }
    );
  };

  if (!('IntersectionObserver' in window)) {
    targets.forEach(reveal);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      reveal(entry.target, targets.indexOf(entry.target));
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.06, rootMargin: '0px 0px -20px' });

  targets.forEach((target) => observer.observe(target));
})();
