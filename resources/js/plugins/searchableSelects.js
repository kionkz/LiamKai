import Choices from 'choices.js';
import 'choices.js/public/assets/styles/choices.min.css';

const instances = new WeakMap();
const optionSignatureAtInit = new WeakMap();
const initialWidths = new WeakMap();
let listenersBound = false;
let mutationObserver = null;
let enhancementQueued = false;
const pendingRoots = new Set();

const getOptionSignature = (select) => Array.from(select.options ?? [])
  .map((option) => [option.value, option.text, option.disabled, option.selected].join('::'))
  .join('||');

const getRenderedWidth = (element) => {
  const width = element.getBoundingClientRect().width;
  return width > 0 ? `${Math.round(width)}px` : null;
};

const applyStoredWidth = (select, choices) => {
  const width = initialWidths.get(select);
  const container = choices?.containerOuter?.element;
  if (!width || !container) return;

  container.style.width = width;
  container.style.maxWidth = '100%';
}

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

const shouldEnhance = (select) => {
  if (!isEnhanceable(select)) return false;
  return (select.options?.length ?? 0) > 0;
};

const enhanceSelect = (select) => {
  if (!shouldEnhance(select) || instances.has(select)) return;

  // Skip if this select is already wrapped by Choices markup.
  if (select.closest('.choices')) return;

  initialWidths.set(select, getRenderedWidth(select));

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
  optionSignatureAtInit.set(select, getOptionSignature(select));
  applyStoredWidth(select, choices);
};

const destroySelect = (select) => {
  const instance = instances.get(select);
  if (!instance) return;

  instance.destroy();
  instances.delete(select);
  optionSignatureAtInit.delete(select);
  initialWidths.delete(select);
};

const refreshIfStale = (select) => {
  if (!isEnhanceable(select)) {
    destroySelect(select);
    return;
  }

  if (!shouldEnhance(select)) {
    destroySelect(select);
    return;
  }

  if (!instances.has(select)) {
    enhanceSelect(select);
    return;
  }

  const previousSignature = optionSignatureAtInit.get(select) ?? '';
  const currentSignature = getOptionSignature(select);
  if (currentSignature === previousSignature) return;

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

const processRoot = (root) => {
  getSelectElements(root).forEach((select) => {
    refreshIfStale(select);
    enhanceSelect(select);
  });
};

const queueEnhancement = (root = document) => {
  pendingRoots.add(root);

  if (enhancementQueued) return;

  enhancementQueued = true;
  requestAnimationFrame(() => {
    pendingRoots.forEach((pendingRoot) => {
      processRoot(pendingRoot);
    });

    pendingRoots.clear();
    enhancementQueued = false;
  });
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

  if (!mutationObserver) {
    mutationObserver = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.target instanceof HTMLSelectElement) {
          queueEnhancement(mutation.target);
          return;
        }

        if (mutation.target instanceof HTMLElement || mutation.target instanceof DocumentFragment) {
          queueEnhancement(mutation.target);
        }

        mutation.addedNodes.forEach((node) => {
          if (node instanceof HTMLElement || node instanceof DocumentFragment) {
            queueEnhancement(node);
          }
        });
      });
    });

    mutationObserver.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['disabled'],
    });
  }

  queueEnhancement(document);
};
