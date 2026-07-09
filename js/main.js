// JOTECH — shared site behaviour: nav, reveal-on-scroll, footer year, back-to-top
(function () {
  document.documentElement.classList.add('js');

  document.addEventListener('DOMContentLoaded', function () {
    // Mobile nav toggle
    var toggle = document.querySelector('.nav-toggle');
    var links = document.querySelector('.nav-links');
    if (toggle && links) {
      toggle.addEventListener('click', function () {
        var open = links.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      links.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
          links.classList.remove('open');
          toggle.setAttribute('aria-expanded', 'false');
        });
      });
    }

    // Footer year
    var yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    // Reveal on scroll
    var revealEls = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window && revealEls.length) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.15 });
      revealEls.forEach(function (el) { io.observe(el); });
    } else {
      revealEls.forEach(function (el) { el.classList.add('is-visible'); });
    }

    // Back to top
    var toTop = document.querySelector('.to-top');
    if (toTop) {
      window.addEventListener('scroll', function () {
        toTop.classList.toggle('show', window.scrollY > 600);
      });
      toTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    // Timestamp fields for basic bot-timing check (paired with PHP honeypot check)
    document.querySelectorAll('input[name="form_loaded_at"]').forEach(function (el) {
      el.value = Math.floor(Date.now() / 1000);
    });
  });
})();
