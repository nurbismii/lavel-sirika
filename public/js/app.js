(() => {
  function parseData(value) {
    try {
      return Function(`return (${value})`)();
    } catch (error) {
      return {};
    }
  }

  function evaluate(binding, state) {
    try {
      return Function('state', `with(state){ return (${binding}); }`)(state);
    } catch (error) {
      return {};
    }
  }

  function runExpression(expression, state) {
    try {
      return Function('state', `with(state){ ${expression}; }`)(state);
    } catch (error) {
      return undefined;
    }
  }

  function applyBindings(root, state) {
    root.querySelectorAll('[\:class], [x-bind\:class]').forEach((el) => {
      const binding = el.getAttribute(':class') || el.getAttribute('x-bind:class');
      const classes = evaluate(binding, state) || {};

      Object.entries(classes).forEach(([className, isActive]) => {
        el.classList.toggle(className, !!isActive);
      });
    });
  }

  function initRoot(root) {
    const state = parseData(root.getAttribute('x-data'));

    applyBindings(root, state);

    root.querySelectorAll('[x-on\:click]').forEach((el) => {
      const expression = el.getAttribute('x-on:click');

      el.addEventListener('click', () => {
        runExpression(expression, state);
        applyBindings(root, state);
      });
    });
  }

  function start() {
    document.querySelectorAll('[x-data]').forEach(initRoot);
  }

  window.Alpine = { start };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
