import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import TaskCard from 'vue/components/TaskCard';

export default {
    name: 'KanbanBoard',

    components: {
        TaskCard
    },

    props: {
        projectId: {
            type: String,
            required: true
        },
        initialTasks: {
            type: Array,
            default: () => []
        },
        milestones: {
            type: Array,
            default: () => []
        },
        basePath: {
            type: String,
            default: ''
        }
    },

    setup(props) {
        const tasks = ref([...props.initialTasks]);
        const currentMode = ref('status');
        const collapsedColumns = ref({});
        const draggedTask = ref(null);
        const dragOverColumn = ref(null);
        const isUpdating = ref(false);

        const basePath = props.basePath || window.BASE_PATH || '';

        // Column configurations
        const statusColumns = [
            { value: 'todo', label: 'To Do', bgColor: 'bg-gray-100', badgeColor: 'bg-gray-500' },
            { value: 'in_progress', label: 'In Progress', bgColor: 'bg-blue-50', badgeColor: 'bg-blue-500' },
            { value: 'in_review', label: 'In Review', bgColor: 'bg-yellow-50', badgeColor: 'bg-yellow-500' },
            { value: 'completed', label: 'Completed', bgColor: 'bg-green-50', badgeColor: 'bg-green-500' }
        ];

        const priorityColumns = [
            { value: 'none', label: 'None', bgColor: 'bg-slate-100', badgeColor: 'bg-slate-500' },
            { value: 'low', label: 'Low', bgColor: 'bg-blue-50', badgeColor: 'bg-blue-500' },
            { value: 'medium', label: 'Medium', bgColor: 'bg-yellow-50', badgeColor: 'bg-yellow-500' },
            { value: 'high', label: 'High', bgColor: 'bg-red-50', badgeColor: 'bg-red-500' }
        ];

        const milestoneColors = ['indigo', 'purple', 'pink', 'teal', 'cyan', 'amber'];

        // Computed columns based on mode
        const columns = computed(() => {
            if (currentMode.value === 'status') {
                return statusColumns;
            } else if (currentMode.value === 'priority') {
                return priorityColumns;
            } else if (currentMode.value === 'milestone') {
                return props.milestones.map((m, index) => ({
                    value: m.id,
                    label: m.name,
                    dueDate: m.dueDate,
                    bgColor: `bg-${milestoneColors[index % milestoneColors.length]}-50`,
                    badgeColor: `bg-${milestoneColors[index % milestoneColors.length]}-500`,
                    textColor: `text-${milestoneColors[index % milestoneColors.length]}-700`
                }));
            }
            return statusColumns;
        });

        // Group tasks by current mode
        const tasksByColumn = computed(() => {
            const grouped = {};
            columns.value.forEach(col => {
                grouped[col.value] = [];
            });

            tasks.value.forEach(task => {
                let columnValue;
                if (currentMode.value === 'status') {
                    columnValue = task.status?.value || task.status || 'todo';
                } else if (currentMode.value === 'priority') {
                    columnValue = task.priority?.value || task.priority || 'none';
                } else if (currentMode.value === 'milestone') {
                    columnValue = task.milestoneId || task.milestone?.id;
                }

                if (grouped[columnValue]) {
                    grouped[columnValue].push(task);
                }
            });

            // Sort by position
            Object.keys(grouped).forEach(key => {
                grouped[key].sort((a, b) => (a.position || 0) - (b.position || 0));
            });

            return grouped;
        });

        // Check if milestone mode is available
        const hasMilestones = computed(() => props.milestones.length > 0);

        // Storage keys
        const modeStorageKey = computed(() => `project_${props.projectId}_kanban_mode`);
        const collapseStorageKey = 'kanban_collapsed_columns';

        // Load saved state
        const loadSavedState = () => {
            // Load mode from URL or localStorage
            const urlParams = new URLSearchParams(window.location.search);
            const urlMode = urlParams.get('kanban');
            const savedMode = localStorage.getItem(modeStorageKey.value);

            if (urlMode && ['status', 'priority', 'milestone'].includes(urlMode)) {
                currentMode.value = urlMode;
            } else if (savedMode && ['status', 'priority', 'milestone'].includes(savedMode)) {
                currentMode.value = savedMode;
            }

            // Load collapsed state
            try {
                const saved = localStorage.getItem(collapseStorageKey);
                if (saved) {
                    collapsedColumns.value = JSON.parse(saved);
                }
            } catch (e) {
                // Ignore
            }
        };

        // Save mode
        const setMode = (mode) => {
            currentMode.value = mode;
            localStorage.setItem(modeStorageKey.value, mode);

            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('kanban', mode);
            window.history.pushState({ kanbanMode: mode }, '', url);
        };

        // Toggle column collapse
        const toggleCollapse = (columnValue) => {
            const key = `${currentMode.value}_${columnValue}`;
            if (collapsedColumns.value[key]) {
                delete collapsedColumns.value[key];
            } else {
                collapsedColumns.value[key] = true;
            }
            localStorage.setItem(collapseStorageKey, JSON.stringify(collapsedColumns.value));
        };

        const isCollapsed = (columnValue) => {
            const key = `${currentMode.value}_${columnValue}`;
            return !!collapsedColumns.value[key];
        };

        // Drag and drop handlers
        const handleDragStart = (event, task) => {
            draggedTask.value = task;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', task.id);
        };

        const handleDragEnd = () => {
            draggedTask.value = null;
            dragOverColumn.value = null;
        };

        const handleDragOver = (event, columnValue) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            dragOverColumn.value = columnValue;
        };

        const handleDragLeave = (event, columnValue) => {
            // Only clear if actually leaving the column
            const relatedTarget = event.relatedTarget;
            const column = event.currentTarget;
            if (!column.contains(relatedTarget)) {
                dragOverColumn.value = null;
            }
        };

        const handleDrop = async (event, newValue) => {
            event.preventDefault();

            if (!draggedTask.value || isUpdating.value) return;

            const task = draggedTask.value;
            const oldValue = getCurrentValue(task);

            // Clear drag state
            dragOverColumn.value = null;

            // If same column, just reorder
            if (oldValue === newValue) {
                await persistColumnOrder(newValue);
                draggedTask.value = null;
                return;
            }

            // Update task locally first (optimistic)
            updateTaskValue(task, newValue);

            isUpdating.value = true;

            try {
                const endpoint = getUpdateEndpoint(task.id);
                const fieldName = getFieldName();

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ [fieldName]: newValue })
                });

                if (!response.ok) {
                    throw new Error('Update failed');
                }

                // Show success notification
                const columnLabel = columns.value.find(c => c.value === newValue)?.label || newValue;
                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Task Updated', `"${task.title}" moved to ${columnLabel}`);
                }

            } catch (error) {
                console.error('Error updating task:', error);
                // Revert on error
                updateTaskValue(task, oldValue);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Update Failed', 'Could not update task. Please try again.');
                }
            } finally {
                isUpdating.value = false;
                draggedTask.value = null;
            }
        };

        // Helper functions
        const getCurrentValue = (task) => {
            if (currentMode.value === 'status') {
                return task.status?.value || task.status || 'todo';
            } else if (currentMode.value === 'priority') {
                return task.priority?.value || task.priority || 'none';
            } else if (currentMode.value === 'milestone') {
                return task.milestoneId || task.milestone?.id;
            }
            return null;
        };

        const updateTaskValue = (task, newValue) => {
            const taskIndex = tasks.value.findIndex(t => t.id === task.id);
            if (taskIndex === -1) return;

            if (currentMode.value === 'status') {
                if (typeof tasks.value[taskIndex].status === 'object') {
                    tasks.value[taskIndex].status.value = newValue;
                } else {
                    tasks.value[taskIndex].status = newValue;
                }
            } else if (currentMode.value === 'priority') {
                if (typeof tasks.value[taskIndex].priority === 'object') {
                    tasks.value[taskIndex].priority.value = newValue;
                } else {
                    tasks.value[taskIndex].priority = newValue;
                }
            } else if (currentMode.value === 'milestone') {
                tasks.value[taskIndex].milestoneId = newValue;
                if (tasks.value[taskIndex].milestone) {
                    tasks.value[taskIndex].milestone.id = newValue;
                }
            }
        };

        const getUpdateEndpoint = (taskId) => {
            if (currentMode.value === 'status') {
                return `${basePath}/tasks/${taskId}/status`;
            } else if (currentMode.value === 'priority') {
                return `${basePath}/tasks/${taskId}/priority`;
            } else if (currentMode.value === 'milestone') {
                return `${basePath}/tasks/${taskId}/milestone`;
            }
            return '';
        };

        const getFieldName = () => {
            if (currentMode.value === 'status') return 'status';
            if (currentMode.value === 'priority') return 'priority';
            if (currentMode.value === 'milestone') return 'milestone';
            return '';
        };

        const persistColumnOrder = async (columnValue) => {
            const columnTasks = tasksByColumn.value[columnValue] || [];
            const taskIds = columnTasks.map(t => t.id);

            if (taskIds.length === 0) return;

            try {
                const response = await fetch(`${basePath}/tasks/reorder`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ taskIds })
                });

                if (response.ok && typeof Toastr !== 'undefined') {
                    Toastr.success('Task Reordered', 'Position updated');
                }
            } catch (error) {
                console.error('Error saving order:', error);
            }
        };

        const handleTaskClick = (task) => {
            if (typeof window.openTaskPanel === 'function') {
                window.openTaskPanel(task.id);
            }
        };

        // Handle browser back/forward
        const handlePopState = (e) => {
            const urlParams = new URLSearchParams(window.location.search);
            const urlMode = urlParams.get('kanban');
            if (urlMode && urlMode !== currentMode.value) {
                currentMode.value = urlMode;
            }
        };

        onMounted(() => {
            loadSavedState();
            window.addEventListener('popstate', handlePopState);
        });

        onUnmounted(() => {
            window.removeEventListener('popstate', handlePopState);
        });

        return {
            tasks,
            currentMode,
            columns,
            tasksByColumn,
            hasMilestones,
            collapsedColumns,
            draggedTask,
            dragOverColumn,
            isUpdating,
            setMode,
            toggleCollapse,
            isCollapsed,
            handleDragStart,
            handleDragEnd,
            handleDragOver,
            handleDragLeave,
            handleDrop,
            handleTaskClick
        };
    },

    template: `
        <div class="kanban-board-vue space-y-4">
            <!-- Header with mode selector -->
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold leading-6 text-gray-900">Tasks</h3>

                <div class="inline-flex rounded-lg bg-gray-100 p-1" role="tablist">
                    <button
                        type="button"
                        @click="setMode('status')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                        :class="currentMode === 'status'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                    >
                        Status
                    </button>
                    <button
                        type="button"
                        @click="setMode('priority')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                        :class="currentMode === 'priority'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                    >
                        Priority
                    </button>
                    <button
                        v-if="hasMilestones"
                        type="button"
                        @click="setMode('milestone')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                        :class="currentMode === 'milestone'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                    >
                        Milestone
                    </button>
                </div>
            </div>

            <!-- Kanban columns -->
            <div
                v-if="tasks.length > 0"
                class="kanban-columns flex gap-4 overflow-x-auto pb-2"
                :class="{ 'kanban-milestone-mode': currentMode === 'milestone' }"
            >
                <div
                    v-for="column in columns"
                    :key="column.value"
                    class="kanban-column rounded-lg flex flex-col transition-all duration-300"
                    :class="[
                        column.bgColor,
                        isCollapsed(column.value) ? 'kanban-column-collapsed' : 'kanban-column-expanded',
                        dragOverColumn === column.value ? 'ring-2 ring-primary-500' : ''
                    ]"
                    :data-value="column.value"
                    @dragover="handleDragOver($event, column.value)"
                    @dragleave="handleDragLeave($event, column.value)"
                    @drop="handleDrop($event, column.value)"
                >
                    <!-- Expanded state -->
                    <template v-if="!isCollapsed(column.value)">
                        <div class="kanban-header p-4 pb-0">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium text-white"
                                        :class="column.badgeColor"
                                    >
                                        {{ column.label }} ({{ tasksByColumn[column.value]?.length || 0 }})
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="toggleCollapse(column.value)"
                                    class="p-1 rounded hover:bg-gray-200 text-gray-500 hover:text-gray-700"
                                    title="Collapse"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                                    </svg>
                                </button>
                            </div>
                            <p v-if="column.dueDate" class="text-xs text-gray-500 -mt-2 mb-2">
                                Due {{ new Date(column.dueDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                            </p>
                        </div>

                        <div class="kanban-content p-4 pt-0 flex-1 overflow-y-auto">
                            <div class="kanban-dropzone space-y-3 min-h-[100px]">
                                <TaskCard
                                    v-for="task in tasksByColumn[column.value]"
                                    :key="task.id"
                                    :task="task"
                                    :draggable="true"
                                    @click="handleTaskClick(task)"
                                    @dragstart="handleDragStart($event, task)"
                                    @dragend="handleDragEnd"
                                />

                                <!-- Drop placeholder when empty and dragging -->
                                <div
                                    v-if="tasksByColumn[column.value]?.length === 0 && draggedTask"
                                    class="drop-placeholder"
                                >
                                    <span>Drop here</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Collapsed state -->
                    <template v-else>
                        <div class="kanban-collapsed-content flex flex-col items-center p-3 h-full">
                            <button
                                type="button"
                                @click="toggleCollapse(column.value)"
                                class="p-1 rounded hover:bg-gray-200 text-gray-500 hover:text-gray-700 mb-2"
                                title="Expand"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <div class="kanban-vertical-title">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium text-white whitespace-nowrap"
                                    :class="column.badgeColor"
                                >
                                    {{ column.label }} ({{ tasksByColumn[column.value]?.length || 0 }})
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No tasks yet</h3>
                <p class="mt-1 text-sm text-gray-500">Create milestones first, then add tasks to them.</p>
            </div>
        </div>

        <style scoped>
        .kanban-columns {
            min-height: 500px;
            max-height: 70vh;
            align-items: stretch;
        }

        .kanban-column-expanded {
            flex: 1 0 220px;
            min-width: 220px;
            max-width: 400px;
        }

        .kanban-column-collapsed {
            flex: 0 0 48px;
            min-width: 48px;
            max-width: 48px;
        }

        .kanban-milestone-mode .kanban-column-expanded {
            flex: 0 0 288px;
            min-width: 288px;
            max-width: 288px;
        }

        .kanban-content {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }

        .kanban-vertical-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
        }

        .drop-placeholder {
            background: #f9fafb;
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 1.25rem 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 70px;
        }

        .drop-placeholder span {
            color: #9ca3af;
            font-size: 0.875rem;
        }

        @media (max-width: 1023px) {
            .kanban-columns {
                flex-direction: column;
                max-height: none;
            }

            .kanban-column-expanded,
            .kanban-column-collapsed {
                flex: none !important;
                min-width: 100% !important;
                max-width: 100% !important;
            }

            .kanban-column-collapsed {
                min-height: 48px;
                max-height: 48px;
            }

            .kanban-content {
                max-height: 300px;
            }

            .kanban-collapsed-content {
                flex-direction: row;
                height: auto;
            }

            .kanban-vertical-title {
                writing-mode: horizontal-tb;
                transform: none;
                margin-left: 0.5rem;
            }
        }
        </style>
    `
};
