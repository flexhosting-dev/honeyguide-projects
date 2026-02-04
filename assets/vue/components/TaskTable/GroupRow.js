export default {
    name: 'GroupRow',

    props: {
        groupKey: {
            type: String,
            required: true
        },
        groupLabel: {
            type: String,
            required: true
        },
        taskCount: {
            type: Number,
            default: 0
        },
        completedCount: {
            type: Number,
            default: 0
        },
        isCollapsed: {
            type: Boolean,
            default: false
        },
        columnCount: {
            type: Number,
            default: 7
        },
        canAdd: {
            type: Boolean,
            default: false
        },
        groupColor: {
            type: String,
            default: null
        }
    },

    emits: ['toggle', 'add-task'],

    setup(props, { emit }) {
        const handleToggle = () => {
            emit('toggle', props.groupKey);
        };

        const handleAddTask = (event) => {
            event.stopPropagation();
            emit('add-task', props.groupKey);
        };

        const progressPercent = props.taskCount > 0
            ? Math.round((props.completedCount / props.taskCount) * 100)
            : 0;

        return {
            handleToggle,
            handleAddTask,
            progressPercent
        };
    },

    template: `
        <tr
            role="row"
            class="group-row bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors"
            :aria-expanded="!isCollapsed"
            tabindex="0"
            @click="handleToggle"
            @keydown.enter="handleToggle"
            @keydown.space.prevent="handleToggle">
            <td :colspan="columnCount" class="px-3 py-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Collapse toggle -->
                        <button
                            type="button"
                            :aria-label="isCollapsed ? 'Expand ' + groupLabel : 'Collapse ' + groupLabel"
                            :aria-expanded="!isCollapsed"
                            class="flex items-center justify-center w-5 h-5 text-gray-400 hover:text-gray-600 rounded"
                            @click.stop="handleToggle">
                            <svg
                                :class="['w-4 h-4 transition-transform', isCollapsed ? '' : 'rotate-90']"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        <!-- Group label with optional color indicator -->
                        <div class="flex items-center gap-2">
                            <span
                                v-if="groupColor"
                                class="w-3 h-3 rounded-full flex-shrink-0"
                                :style="{ backgroundColor: groupColor }">
                            </span>
                            <span class="font-medium text-gray-900">{{ groupLabel }}</span>
                        </div>

                        <!-- Task count -->
                        <span class="text-sm text-gray-500">
                            {{ completedCount }}/{{ taskCount }}
                        </span>

                        <!-- Mini progress bar -->
                        <div v-if="taskCount > 0" class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-green-500 transition-all duration-300"
                                :style="{ width: progressPercent + '%' }">
                            </div>
                        </div>
                    </div>

                    <!-- Add task button -->
                    <button
                        v-if="canAdd"
                        type="button"
                        :aria-label="'Add task to ' + groupLabel"
                        class="sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 transition-opacity inline-flex items-center gap-1 px-2 py-1 text-xs text-gray-500 hover:text-gray-700 hover:bg-white rounded border border-transparent hover:border-gray-300"
                        @click="handleAddTask">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add task
                    </button>
                </div>
            </td>
        </tr>
    `
};
