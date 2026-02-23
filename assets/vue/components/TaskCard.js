import { ref, computed } from 'vue';

export default {
    name: 'TaskCard',

    props: {
        task: {
            type: Object,
            required: true
        },
        draggable: {
            type: Boolean,
            default: false
        },
        basePath: {
            type: String,
            default: ''
        },
        loading: {
            type: Boolean,
            default: false
        },
        canAddSubtask: {
            type: Boolean,
            default: false
        }
    },

    emits: ['click', 'dragstart', 'dragend', 'add-subtask'],

    setup(props, { emit }) {
        const isDragging = ref(false);
        const basePath = props.basePath || window.BASE_PATH || '';

        // Computed properties
        const priorityClasses = computed(() => {
            const priority = props.task.priority?.value || props.task.priority || 'none';
            const classes = {
                'high': 'bg-red-100 text-red-700',
                'medium': 'bg-yellow-100 text-yellow-700',
                'low': 'bg-blue-100 text-blue-700',
                'none': 'bg-gray-100 text-gray-700'
            };
            return classes[priority] || classes['none'];
        });

        const priorityLabel = computed(() => {
            const priority = props.task.priority?.label || props.task.priorityLabel || 'None';
            return priority;
        });

        const isOverdue = computed(() => {
            if (!props.task.dueDate) return false;
            const dueDate = new Date(props.task.dueDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            // Check if status is closed (parentType === 'closed' or legacy status === 'completed')
            const isClosed = props.task.status?.parentType === 'closed' || props.task.status?.value === 'completed';
            return dueDate < today && !isClosed;
        });

        const formattedDueDate = computed(() => {
            if (!props.task.dueDate) return null;
            const date = new Date(props.task.dueDate);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        const displayedAssignees = computed(() => {
            const assignees = props.task.assignees || [];
            return assignees.slice(0, 3);
        });

        const extraAssigneesCount = computed(() => {
            const assignees = props.task.assignees || [];
            return Math.max(0, assignees.length - 3);
        });

        const taskUrl = computed(() => {
            return `${basePath}/tasks/${props.task.id}`;
        });

        // Task depth for visual indicator
        const taskDepth = computed(() => {
            return props.task.depth || 0;
        });

        const depthLabel = computed(() => {
            const depth = taskDepth.value;
            if (depth === 0) return 'Top-level task';
            if (depth === 1) return 'Subtask (Level 1)';
            if (depth === 2) return 'Subtask (Level 2)';
            return `Subtask (Level ${depth})`;
        });

        // Generate depth icon rectangles dynamically
        const depthIconRects = computed(() => {
            const depth = taskDepth.value;
            if (depth === 0) {
                // Root task with children: 2 lines
                return [
                    { x: 2, y: 4, width: 6 },
                    { x: 4, y: 8, width: 10 }
                ];
            } else {
                // Subtasks: 1 parent line + depth indented lines
                const rects = [{ x: 2, y: 2, width: 6 }];
                for (let i = 1; i <= depth; i++) {
                    rects.push({ x: 4, y: 2 + (i * 4), width: 10 });
                }
                return rects;
            }
        });

        // Methods
        const handleClick = (event) => {
            event.preventDefault();
            emit('click', props.task);
            // Try to open task panel if function exists
            if (typeof window.openTaskPanel === 'function') {
                window.openTaskPanel(props.task.id);
            }
        };

        const handleDragStart = (event) => {
            if (!props.draggable) return;
            isDragging.value = true;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', props.task.id);
            emit('dragstart', { event, task: props.task });
        };

        const handleDragEnd = (event) => {
            isDragging.value = false;
            emit('dragend', { event, task: props.task });
        };

        // Get assignee initials
        const getInitials = (assignee) => {
            const firstName = assignee.user?.firstName || assignee.firstName || '';
            const lastName = assignee.user?.lastName || assignee.lastName || '';
            return (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
        };

        // Get assignee avatar URL
        const getAvatar = (assignee) => {
            return assignee.user?.avatar || assignee.avatar || null;
        };

        const handleAddSubtask = (event) => {
            event.stopPropagation();
            event.preventDefault();
            emit('add-subtask', props.task);
        };

        return {
            isDragging,
            priorityClasses,
            priorityLabel,
            isOverdue,
            formattedDueDate,
            displayedAssignees,
            extraAssigneesCount,
            taskUrl,
            taskDepth,
            depthLabel,
            depthIconRects,
            handleClick,
            handleDragStart,
            handleDragEnd,
            getInitials,
            getAvatar,
            handleAddSubtask
        };
    },

    template: `
        <div
            :data-task-id="task.id"
            :draggable="draggable"
            @dragstart="handleDragStart"
            @dragend="handleDragEnd"
            class="task-card bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-all duration-200 relative"
            :class="{
                'cursor-move': draggable,
                'opacity-40 scale-95': isDragging
            }"
        >
            <!-- Loading overlay -->
            <div v-if="loading" class="absolute inset-0 bg-white/70 rounded-lg flex items-center justify-center z-10">
                <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div class="flex items-start justify-between">
                <h4 class="text-sm flex-1 min-w-0 line-clamp-3">
                    <a
                        :href="taskUrl"
                        class="font-medium text-gray-900 hover:text-primary-600 task-link"
                        :data-task-id="task.id"
                        @click="handleClick"
                    >{{ task.title }}</a><span v-if="task.parentChain" class="text-xs text-gray-400 font-normal ml-1" :title="task.parentChain">in {{ task.parentChain }}</span>
                </h4>
                <span
                    class="ml-2 flex-shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="priorityClasses"
                >
                    {{ priorityLabel }}
                </span>
            </div>

            <p v-if="task.projectName || task.project?.name" class="mt-1 text-xs text-gray-500 flex items-center gap-1">
                <svg class="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                </svg>
                {{ task.projectName || task.project?.name }}
            </p>

            <div class="mt-3 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <!-- Due Date -->
                    <span
                        v-if="formattedDueDate"
                        class="flex items-center text-xs"
                        :class="isOverdue ? 'text-red-600 font-medium' : 'text-gray-500'"
                    >
                        <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        {{ formattedDueDate }}
                    </span>

                    <!-- Comment Count -->
                    <span
                        v-if="task.commentCount > 0"
                        class="flex items-center text-xs text-gray-500"
                    >
                        <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z" />
                        </svg>
                        {{ task.commentCount }}
                    </span>

                    <!-- Depth indicator (always show for subtasks, show count if has subtasks) -->
                    <span
                        v-if="taskDepth > 0 || task.subtaskCount > 0"
                        class="flex items-center text-xs text-gray-500"
                        :title="depthLabel + (task.subtaskCount > 0 ? ' - ' + (task.completedSubtaskCount || 0) + '/' + task.subtaskCount + ' subtasks' : '')"
                    >
                        <!-- Dynamic depth icon -->
                        <svg class="h-4 w-4" :class="{ 'mr-1': task.subtaskCount > 0 }" viewBox="0 0 16 16" fill="currentColor">
                            <rect
                                v-for="(rect, index) in depthIconRects"
                                :key="index"
                                :x="rect.x"
                                :y="rect.y"
                                :width="rect.width"
                                height="1.5"
                                rx="0.5"
                            />
                        </svg>
                        <span v-if="task.subtaskCount > 0">{{ task.completedSubtaskCount || 0 }}/{{ task.subtaskCount }}</span>
                    </span>

                    <!-- Checklist Progress (checkmark icon) -->
                    <span
                        v-if="task.checklistCount > 0"
                        class="flex items-center text-xs text-gray-500"
                        title="Checklist items"
                    >
                        <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ task.completedChecklistCount || 0 }}/{{ task.checklistCount }}
                    </span>
                </div>

                <!-- Assignees -->
                <div v-if="displayedAssignees.length > 0" class="flex -space-x-1">
                    <span
                        v-for="assignee in displayedAssignees"
                        :key="assignee.id || assignee.user?.id"
                        class="inline-flex h-6 w-6 items-center justify-center rounded-full overflow-hidden ring-2 ring-white"
                        :title="assignee.user?.fullName || assignee.fullName"
                    >
                        <img v-if="getAvatar(assignee)" :src="getAvatar(assignee)" :alt="assignee.user?.fullName || assignee.fullName" class="w-full h-full object-cover">
                        <span v-else class="w-full h-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center">
                            <span class="text-xs font-medium text-white">{{ getInitials(assignee) }}</span>
                        </span>
                    </span>
                    <span
                        v-if="extraAssigneesCount > 0"
                        class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 ring-2 ring-white text-xs font-medium text-gray-500"
                    >
                        +{{ extraAssigneesCount }}
                    </span>
                </div>
            </div>

            <!-- Tags -->
            <div v-if="task.tags && task.tags.length > 0" class="mt-2 flex flex-wrap gap-1">
                <span
                    v-for="tag in task.tags.slice(0, 3)"
                    :key="tag.id"
                    class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium text-white"
                    :style="{ backgroundColor: tag.color }"
                >
                    {{ tag.name }}
                </span>
                <span
                    v-if="task.tags.length > 3"
                    class="inline-flex items-center text-xs text-gray-500"
                >
                    +{{ task.tags.length - 3 }}
                </span>
            </div>

            <!-- Add subtask button -->
            <button
                v-if="canAddSubtask"
                type="button"
                class="task-card-add-subtask absolute bottom-2 right-2 w-5 h-5 rounded-full bg-gray-100 hover:bg-primary-100 text-gray-400 hover:text-primary-600 flex items-center justify-center opacity-0 transition-opacity"
                title="Add subtask"
                @click="handleAddSubtask"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
        </div>
    `
};
