import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';

export default {
    name: 'DatePickerPopup',

    props: {
        visible: {
            type: Boolean,
            default: false
        },
        value: {
            type: String,
            default: ''
        }
    },

    emits: ['close', 'select'],

    setup(props, { emit }) {
        const inputRef = ref(null);
        const dateValue = ref('');
        let hasChanged = false;

        // Watch for visibility changes
        watch(() => props.visible, async (newVisible) => {
            if (newVisible) {
                dateValue.value = props.value || '';
                hasChanged = false;
                await nextTick();
                if (inputRef.value) {
                    inputRef.value.focus();
                    // Directly open the native date picker
                    try {
                        inputRef.value.showPicker();
                    } catch (e) {
                        // showPicker() may not be supported in all browsers
                        // The input is still focused and usable
                    }
                }
            }
        });

        // Handle date change
        const handleChange = (event) => {
            hasChanged = true;
            const newValue = event.target.value;
            dateValue.value = newValue;
            emit('select', newValue || null);
            emit('close');
        };

        // Handle blur (picker closed without selection)
        const handleBlur = () => {
            // Small delay to allow change event to fire first
            setTimeout(() => {
                if (!hasChanged) {
                    emit('close');
                }
            }, 100);
        };

        // Handle escape key
        const handleKeydown = (event) => {
            if (event.key === 'Escape') {
                emit('close');
            }
        };

        onMounted(() => {
            document.addEventListener('keydown', handleKeydown);
        });

        onUnmounted(() => {
            document.removeEventListener('keydown', handleKeydown);
        });

        return {
            inputRef,
            dateValue,
            handleChange,
            handleBlur
        };
    },

    template: `
        <Teleport to="body">
            <input
                v-if="visible"
                ref="inputRef"
                type="date"
                :value="dateValue"
                @change="handleChange"
                @blur="handleBlur"
                style="position: fixed; top: -100px; left: -100px; opacity: 0; pointer-events: none;"
            />
        </Teleport>
    `
};
