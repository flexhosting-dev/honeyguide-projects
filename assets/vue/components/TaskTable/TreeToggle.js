export default {
    name: 'TreeToggle',

    props: {
        taskId: {
            type: String,
            required: true
        },
        isExpanded: {
            type: Boolean,
            default: false
        },
        hasChildren: {
            type: Boolean,
            default: false
        },
        isLoading: {
            type: Boolean,
            default: false
        },
        depth: {
            type: Number,
            default: 0
        }
    },

    emits: ['toggle'],

    setup(props, { emit }) {
        const handleClick = (event) => {
            event.stopPropagation();
            if (props.hasChildren && !props.isLoading) {
                emit('toggle', props.taskId);
            }
        };

        return { handleClick };
    },

    template: `
        <button
            v-if="hasChildren"
            type="button"
            class="tree-toggle flex-shrink-0 w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors"
            :class="{ 'cursor-pointer': !isLoading }"
            :disabled="isLoading"
            @click="handleClick"
            :title="isExpanded ? 'Collapse' : 'Expand'">
            <!-- Loading spinner -->
            <svg v-if="isLoading" class="animate-spin w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <!-- Expanded icon (minus) -->
            <svg v-else-if="isExpanded" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <!-- Collapsed icon (plus) -->
            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        <!-- Spacer when no children but has depth (for alignment) -->
        <span v-else-if="depth > 0" class="flex-shrink-0 w-5"></span>
    `
};
