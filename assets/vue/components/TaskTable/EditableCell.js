import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

export default {
    name: 'EditableCell',

    props: {
        value: {
            type: [String, Number, Object, Array],
            default: null
        },
        type: {
            type: String,
            default: 'text' // 'text', 'select', 'date', 'multiselect'
        },
        options: {
            type: Array,
            default: () => [] // For select/multiselect: [{ value: '', label: '', color: '' }]
        },
        placeholder: {
            type: String,
            default: ''
        },
        disabled: {
            type: Boolean,
            default: false
        },
        canEdit: {
            type: Boolean,
            default: true
        },
        taskId: {
            type: String,
            required: true
        },
        field: {
            type: String,
            required: true
        }
    },

    emits: ['save', 'cancel', 'navigate'],

    setup(props, { emit }) {
        const isEditing = ref(false);
        const editValue = ref(null);
        const inputRef = ref(null);
        const selectRef = ref(null);
        const isSaving = ref(false);

        // Initialize edit value
        const initEditValue = () => {
            if (props.type === 'multiselect') {
                editValue.value = Array.isArray(props.value)
                    ? [...props.value]
                    : [];
            } else if (props.type === 'select') {
                editValue.value = props.value?.value || props.value || '';
            } else {
                editValue.value = props.value || '';
            }
        };

        // Start editing
        const startEditing = async () => {
            if (!props.canEdit || props.disabled) return;
            initEditValue();
            isEditing.value = true;

            await nextTick();

            // Focus the input
            if (inputRef.value) {
                inputRef.value.focus();
                if (props.type === 'text') {
                    inputRef.value.select();
                }
            } else if (selectRef.value) {
                selectRef.value.focus();
            }
        };

        // Save changes
        const saveChanges = async () => {
            if (isSaving.value) return;

            // Check if value changed
            let originalValue = props.type === 'select'
                ? (props.value?.value || props.value)
                : props.value;

            if (editValue.value === originalValue) {
                cancelEditing();
                return;
            }

            isSaving.value = true;
            emit('save', {
                taskId: props.taskId,
                field: props.field,
                value: editValue.value
            });

            // Don't immediately exit edit mode - let parent handle it after API call
            setTimeout(() => {
                isEditing.value = false;
                isSaving.value = false;
            }, 100);
        };

        // Cancel editing
        const cancelEditing = () => {
            isEditing.value = false;
            editValue.value = null;
            emit('cancel');
        };

        // Handle keyboard navigation
        const handleKeydown = (event) => {
            if (!isEditing.value) {
                // Start editing on Enter or typing
                if (event.key === 'Enter' || event.key === 'F2') {
                    event.preventDefault();
                    startEditing();
                }
                return;
            }

            switch (event.key) {
                case 'Enter':
                    if (props.type !== 'multiselect') {
                        event.preventDefault();
                        saveChanges();
                    }
                    break;
                case 'Escape':
                    event.preventDefault();
                    cancelEditing();
                    break;
                case 'Tab':
                    // Let parent handle tab navigation
                    saveChanges();
                    emit('navigate', event.shiftKey ? 'prev' : 'next');
                    break;
            }
        };

        // Handle click outside
        const handleClickOutside = (event) => {
            if (isEditing.value) {
                const wrapper = event.target.closest('.editable-cell-wrapper');
                if (!wrapper || wrapper.dataset.taskId !== props.taskId || wrapper.dataset.field !== props.field) {
                    saveChanges();
                }
            }
        };

        // Toggle option for multiselect
        const toggleOption = (optionValue) => {
            if (!Array.isArray(editValue.value)) {
                editValue.value = [];
            }
            const idx = editValue.value.indexOf(optionValue);
            if (idx >= 0) {
                editValue.value.splice(idx, 1);
            } else {
                editValue.value.push(optionValue);
            }
        };

        // Get display value
        const displayValue = computed(() => {
            if (props.type === 'select') {
                const val = props.value?.value || props.value;
                const opt = props.options.find(o => o.value === val);
                return opt?.label || val || '-';
            }
            if (props.type === 'date') {
                if (!props.value) return '-';
                const date = new Date(props.value);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }
            if (props.type === 'multiselect') {
                if (!Array.isArray(props.value) || props.value.length === 0) return '-';
                return props.value.map(v => {
                    const opt = props.options.find(o => o.value === v);
                    return opt?.label || v;
                }).join(', ');
            }
            return props.value || '-';
        });

        // Get badge class for select
        const getBadgeClass = computed(() => {
            if (props.type !== 'select') return '';
            const val = props.value?.value || props.value;
            const opt = props.options.find(o => o.value === val);
            return opt?.badgeClass || '';
        });

        onMounted(() => {
            document.addEventListener('click', handleClickOutside);
        });

        onUnmounted(() => {
            document.removeEventListener('click', handleClickOutside);
        });

        return {
            isEditing,
            editValue,
            inputRef,
            selectRef,
            isSaving,
            displayValue,
            getBadgeClass,
            startEditing,
            saveChanges,
            cancelEditing,
            handleKeydown,
            toggleOption
        };
    },

    template: `
        <div
            class="editable-cell-wrapper relative"
            :data-task-id="taskId"
            :data-field="field"
            @keydown="handleKeydown"
            @dblclick="startEditing">

            <!-- Display mode -->
            <div
                v-if="!isEditing"
                :class="[
                    'cell-display cursor-pointer rounded px-1 -mx-1 hover:bg-gray-100 transition-colors',
                    canEdit ? '' : 'cursor-default'
                ]"
                @click.stop="startEditing">

                <!-- Text display -->
                <template v-if="type === 'text'">
                    <span class="truncate">{{ displayValue }}</span>
                </template>

                <!-- Select display (badge) -->
                <template v-else-if="type === 'select'">
                    <span
                        v-if="value"
                        :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', getBadgeClass]">
                        {{ displayValue }}
                    </span>
                    <span v-else class="text-gray-400">{{ placeholder || '-' }}</span>
                </template>

                <!-- Date display -->
                <template v-else-if="type === 'date'">
                    <span :class="value ? '' : 'text-gray-400'">{{ displayValue }}</span>
                </template>

                <!-- Default display -->
                <template v-else>
                    <span>{{ displayValue }}</span>
                </template>
            </div>

            <!-- Edit mode -->
            <div v-else class="cell-editor">
                <!-- Text input -->
                <input
                    v-if="type === 'text'"
                    ref="inputRef"
                    type="text"
                    v-model="editValue"
                    :placeholder="placeholder"
                    :disabled="isSaving"
                    class="w-full px-2 py-1 text-sm border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500"
                    @keydown="handleKeydown"
                    @blur="saveChanges"
                />

                <!-- Select dropdown -->
                <select
                    v-else-if="type === 'select'"
                    ref="selectRef"
                    v-model="editValue"
                    :disabled="isSaving"
                    class="w-full px-2 py-1 text-sm border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500"
                    @keydown="handleKeydown"
                    @change="saveChanges"
                    @blur="saveChanges">
                    <option v-for="opt in options" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>

                <!-- Date input -->
                <input
                    v-else-if="type === 'date'"
                    ref="inputRef"
                    type="date"
                    v-model="editValue"
                    :disabled="isSaving"
                    class="w-full px-2 py-1 text-sm border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500"
                    @keydown="handleKeydown"
                    @change="saveChanges"
                    @blur="saveChanges"
                />

                <!-- Loading indicator -->
                <div v-if="isSaving" class="absolute inset-0 flex items-center justify-center bg-white/50">
                    <svg class="animate-spin h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>
            </div>
        </div>
    `
};
