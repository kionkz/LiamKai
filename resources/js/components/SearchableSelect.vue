<template>
  <div ref="root" class="searchable-select" :class="{ open: isOpen, disabled }">
    <div class="input-wrap">
      <input
        ref="inputRef"
        type="text"
        class="search-input"
        :placeholder="selectedOption ? selectedOption.label : placeholder"
        :value="inputValue"
        :disabled="disabled"
        autocomplete="off"
        @focus="openDropdown"
        @input="handleInput"
        @keydown.down.prevent="highlightNext"
        @keydown.up.prevent="highlightPrevious"
        @keydown.enter.prevent="selectHighlighted"
        @keydown.esc.prevent="closeDropdown"
      />
      <button
        type="button"
        class="toggle-button"
        :disabled="disabled"
        @click="toggleDropdown"
      >
        ▾
      </button>
    </div>

    <div v-if="isOpen" class="dropdown-panel">
      <button
        v-for="(option, index) in filteredOptions"
        :key="`${option.value}-${index}`"
        type="button"
        class="option-button"
        :class="{ highlighted: index === highlightedIndex, selected: option.value === modelValue }"
        @mousedown.prevent="selectOption(option)"
      >
        {{ option.label }}
      </button>
      <p v-if="filteredOptions.length === 0" class="empty-state">No results found</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: [String, Number, null],
    default: '',
  },
  options: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: 'Search...',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'change']);

const root = ref(null);
const inputRef = ref(null);
const isOpen = ref(false);
const searchTerm = ref('');
const highlightedIndex = ref(-1);

const selectedOption = computed(() => {
  return props.options.find((option) => option.value == props.modelValue) || null;
});

const filteredOptions = computed(() => {
  const normalizedSearch = searchTerm.value.trim().toLowerCase();

  if (!normalizedSearch) {
    return props.options;
  }

  return props.options.filter((option) => option.label.toLowerCase().includes(normalizedSearch));
});

const inputValue = computed(() => {
  if (isOpen.value) {
    return searchTerm.value;
  }

  return selectedOption.value?.label || '';
});

const openDropdown = () => {
  if (props.disabled) return;

  isOpen.value = true;
  searchTerm.value = '';
  highlightedIndex.value = filteredOptions.value.length > 0 ? 0 : -1;
};

const closeDropdown = () => {
  isOpen.value = false;
  searchTerm.value = '';
  highlightedIndex.value = -1;
};

const toggleDropdown = () => {
  if (isOpen.value) {
    closeDropdown();
    return;
  }

  openDropdown();
  inputRef.value?.focus();
};

const handleInput = (event) => {
  searchTerm.value = event.target.value;
  highlightedIndex.value = filteredOptions.value.length > 0 ? 0 : -1;
};

const selectOption = (option) => {
  emit('update:modelValue', option.value);
  emit('change', option.value);
  closeDropdown();
};

const highlightNext = () => {
  if (!isOpen.value) {
    openDropdown();
    return;
  }

  if (filteredOptions.value.length === 0) return;
  highlightedIndex.value = (highlightedIndex.value + 1) % filteredOptions.value.length;
};

const highlightPrevious = () => {
  if (!isOpen.value) {
    openDropdown();
    return;
  }

  if (filteredOptions.value.length === 0) return;
  highlightedIndex.value = highlightedIndex.value <= 0
    ? filteredOptions.value.length - 1
    : highlightedIndex.value - 1;
};

const selectHighlighted = () => {
  if (!isOpen.value || highlightedIndex.value < 0) return;

  const option = filteredOptions.value[highlightedIndex.value];
  if (option) {
    selectOption(option);
  }
};

const handleDocumentClick = (event) => {
  if (!root.value?.contains(event.target)) {
    closeDropdown();
  }
};

watch(() => props.options, () => {
  if (isOpen.value) {
    highlightedIndex.value = filteredOptions.value.length > 0 ? 0 : -1;
  }
});

onMounted(() => {
  document.addEventListener('mousedown', handleDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleDocumentClick);
});
</script>

<style scoped>
.searchable-select {
  position: relative;
  width: 100%;
}

.input-wrap {
  position: relative;
}

.search-input {
  width: 100%;
  min-height: 42px;
  padding: 10px 42px 10px 12px;
  border: 1px solid #dbe3ec;
  border-radius: 12px;
  font-size: 14px;
  font-family: inherit;
  background: #fff;
  color: #10243e;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}

.search-input:focus {
  outline: none;
  border-color: #e57c2a;
  box-shadow: 0 0 0 3px rgba(229, 124, 42, 0.2);
  background-color: #fff;
}

.toggle-button {
  position: absolute;
  top: 50%;
  right: 10px;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: #555;
  cursor: pointer;
  font-size: 14px;
  min-height: 0;
}

.dropdown-panel {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  z-index: 50;
  background: #fff;
  border: 1px solid #dbe3ec;
  border-radius: 12px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
  max-height: 240px;
  overflow-y: auto;
}

.option-button {
  width: 100%;
  padding: 10px 12px;
  border: none;
  background: #fff;
  text-align: left;
  cursor: pointer;
  font-size: 13px;
  color: #10243e;
  min-height: 38px;
}

.option-button:hover,
.option-button.highlighted {
  background: #fff5ec;
}

.option-button.selected {
  color: #2563eb;
  font-weight: 600;
}

.option-button.selected::before {
  content: '✓';
  margin-right: 8px;
  color: #2563eb;
  font-weight: 700;
}

.empty-state {
  margin: 0;
  padding: 12px;
  color: #6b7280;
  font-size: 13px;
}

.searchable-select.disabled .search-input,
.searchable-select.disabled .toggle-button {
  cursor: not-allowed;
}
</style>
