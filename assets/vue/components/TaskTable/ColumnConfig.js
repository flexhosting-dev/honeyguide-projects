import { ref, computed, onMounted, onUnmounted } from 'vue';

export default {
    name: 'ColumnConfig',

    props: {
        columns: {
            type: Array,
            required: true
        }
    },

    emits: ['toggle-visibility', 'reorder', 'reset'],

    setup(props, { emit }) {
        const isOpen = ref(false);
        const dropdownRef = ref(null);
        const draggedIndex = ref(null);
        const dragOverIndex = ref(null);

        // Filter out checkbox column from configuration
        const configurableColumns = computed(() => {
            return props.columns.filter(col => col.key !== 'checkbox');
        });

        const toggleDropdown = () => {
            isOpen.value = !isOpen.value;
        };

        const closeDropdown = () => {
            isOpen.value = false;
        };

        const handleClickOutside = (event) => {
            if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
                closeDropdown();
            }
        };

        const handleKeydown = (event) => {
            if (event.key === 'Escape' && isOpen.value) {
                closeDropdown();
            }
        };

        const toggleColumnVisibility = (columnKey) => {
            emit('toggle-visibility', columnKey);
        };

        const handleDragStart = (event, index) => {
            // Skip if no dataTransfer (touch devices)
            if (!event.dataTransfer) return;
            draggedIndex.value = index;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', index);
        };

        const handleDragOver = (event, index) => {
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
            dragOverIndex.value = index;
        };

        const handleDragLeave = () => {
            dragOverIndex.value = null;
        };

        const handleDrop = (event, targetIndex) => {
            event.preventDefault();
            const sourceIndex = draggedIndex.value;
            if (sourceIndex !== null && sourceIndex !== targetIndex) {
                // Need to account for checkbox column being at index 0
                const actualSourceIndex = sourceIndex + 1; // +1 for checkbox
                const actualTargetIndex = targetIndex + 1;
                emit('reorder', actualSourceIndex, actualTargetIndex);
            }
            draggedIndex.value = null;
            dragOverIndex.value = null;
        };

        const handleDragEnd = () => {
            draggedIndex.value = null;
            dragOverIndex.value = null;
        };

        const resetColumns = () => {
            emit('reset');
            closeDropdown();
        };

        onMounted(() => {
            document.addEventListener('click', handleClickOutside);
            document.addEventListener('keydown', handleKeydown);
        });

        onUnmounted(() => {
            document.removeEventListener('click', handleClickOutside);
            document.removeEventListener('keydown', handleKeydown);
        });

        return {
            isOpen,
            dropdownRef,
            configurableColumns,
            draggedIndex,
            dragOverIndex,
            toggleDropdown,
            closeDropdown,
            toggleColumnVisibility,
            handleDragStart,
            handleDragOver,
            handleDragLeave,
            handleDrop,
            handleDragEnd,
            resetColumns
        };
    },

    template: `
        <div class="relative" ref="dropdownRef">
            <button
                type="button"
                @click="toggleDropdown"
                :aria-expanded="isOpen"
                aria-haspopup="true"
                aria-label="Configure visible columns"
                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
                title="Configure columns">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span class="hidden sm:inline">Columns</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <!-- Dropdown menu -->
            <div
                v-show="isOpen"
                role="menu"
                aria-label="Column visibility options"
                class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-50 overflow-hidden">
                <div class="px-3 py-2 border-b border-gray-200">
                    <h3 class="text-sm font-medium text-gray-900" id="column-config-title">Show columns</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Drag to reorder</p>
                </div>

                <div class="max-h-64 overflow-y-auto py-1">
                    <div
                        v-for="(column, index) in configurableColumns"
                        :key="column.key"
                        :draggable="true"
                        @dragstart="handleDragStart($event, index)"
                        @dragover="handleDragOver($event, index)"
                        @dragleave="handleDragLeave"
                        @drop="handleDrop($event, index)"
                        @dragend="handleDragEnd"
                        :class="[
                            'flex items-center gap-2 px-3 py-2 cursor-move transition-colors',
                            dragOverIndex === index ? 'bg-primary-50' : 'hover:bg-gray-50',
                            draggedIndex === index ? 'opacity-50' : ''
                        ]">
                        <!-- Drag handle -->
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                        </svg>

                        <!-- Checkbox -->
                        <input
                            type="checkbox"
                            :id="'col-' + column.key"
                            :checked="column.visible"
                            @change="toggleColumnVisibility(column.key)"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />

                        <!-- Label -->
                        <label
                            :for="'col-' + column.key"
                            class="flex-1 text-sm text-gray-700 cursor-pointer select-none">
                            {{ column.label }}
                        </label>
                    </div>
                </div>

                <div class="px-3 py-2 border-t border-gray-200">
                    <button
                        type="button"
                        @click="resetColumns"
                        class="w-full text-sm text-gray-600 hover:text-gray-900 py-1.5 rounded hover:bg-gray-50 transition-colors">
                        Reset to defaults
                    </button>
                </div>
            </div>
        </div>
    `
};
