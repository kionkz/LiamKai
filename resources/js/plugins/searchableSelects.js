import Choices from 'choices.js';
import 'choices.js/public/assets/styles/choices.min.css';

const instances = new WeakMap();
const optionCountAtInit = new WeakMap();
let listenersBound = false;

const getSelectElements = (root) => {
  if (!root) return [];
  if (root.matches && root.matches('select')) return [root];
  if (!root.querySelectorAll) return [];
  return Array.from(root.querySelectorAll('select'));
};

const isEnhanceable = (select) => {
  if (!(select instanceof HTMLSelectElement)) return false;
  if (select.dataset.searchable === 'off') return false;
  if (select.disabled) return false;
  return true;
};

const enhanceSelect = (select) => {
  if (!isEnhanceable(select) || instances.has(select)) return;

  // Avoid initializing too early (many lists are filled asynchronously).
  if ((select.options?.length ?? 0) <= 1) return;

  // Skip if this select is already wrapped by Choices markup.
  if (select.closest('.choices')) return;

  const choices = new Choices(select, {
    shouldSort: false,
    searchEnabled: true,
    itemSelectText: '',
    allowHTML: false,
    removeItemButton: false,
    placeholder: true,
    placeholderValue: 'Search...',
    noResultsText: 'No results found',
    noChoicesText: 'No options available',
  });

  instances.set(select, choices);
  optionCountAtInit.set(select, select.options.length);
};

const destroySelect = (select) => {
  const instance = instances.get(select);
  if (!instance) return;

  instance.destroy();
  instances.delete(select);
  optionCountAtInit.delete(select);
};

const refreshIfStale = (select) => {
  if (!instances.has(select)) return;

  const previousCount = optionCountAtInit.get(select) ?? 0;
  const currentCount = select.options?.length ?? 0;
  if (currentCount === previousCount) return;

  destroySelect(select);
  enhanceSelect(select);
};

const resolveSelectFromTarget = (target) => {
  if (!target || typeof target.closest !== 'function') return;

  const directSelect = target.closest('select');
  if (directSelect) return directSelect;

  const choicesWrapper = target.closest('.choices');
  if (!choicesWrapper) return null;

  const nestedSelect = choicesWrapper.querySelector('select');
  if (nestedSelect) return nestedSelect;

  const prev = choicesWrapper.previousElementSibling;
  if (prev instanceof HTMLSelectElement) return prev;

  return null;
};

const enhanceFromEventTarget = (target) => {
  const select = resolveSelectFromTarget(target);
  if (select) {
    refreshIfStale(select);
    enhanceSelect(select);
  }
};

export const setupSearchableSelects = () => {
  if (!listenersBound) {
    // Lazy enhancement ensures newly-rendered selects always become searchable
    // when the user interacts with them.
    document.addEventListener('focusin', (event) => {
      enhanceFromEventTarget(event.target);
    }, true);

    document.addEventListener('pointerdown', (event) => {
      enhanceFromEventTarget(event.target);
    }, true);

    listenersBound = true;
  }
};
