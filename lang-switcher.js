(function () {
  const initLanguageSwitcher = () => {
    document.querySelectorAll('.site-nav-lang-wrap').forEach((wrap) => {
      const trigger = wrap.querySelector('.site-nav-lang');
      const menu = wrap.querySelector('.site-nav-lang-menu');
      const options = wrap.querySelectorAll('.site-nav-lang-option');

      if (!trigger || !menu || !options.length) return;

      const closeMenu = () => {
        wrap.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
      };

      trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = wrap.classList.toggle('open');
        trigger.setAttribute('aria-expanded', String(isOpen));
      });

      options.forEach((option) => {
        option.addEventListener('click', (event) => {
          event.stopPropagation();
          options.forEach((item) => item.classList.remove('active'));
          option.classList.add('active');
          trigger.textContent = option.dataset.value;
          trigger.setAttribute('aria-label', `Language: ${option.dataset.label}`);
          closeMenu();
        });
      });

      document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) {
          closeMenu();
        }
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLanguageSwitcher);
  } else {
    initLanguageSwitcher();
  }
})();
