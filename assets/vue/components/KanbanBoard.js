import { ref, computed, onMounted, onUnmounted, nextTick, defineAsyncComponent } from 'vue';

export default {
    name: 'KanbanBoard',

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
        const tasks = ref(Array.isArray(props.initialTasks) ? [...props.initialTasks] : []);
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

            // Find the column config to get the label for the new value
            const column = columns.value.find(c => c.value === newValue);
            const newLabel = column?.label || newValue;

            if (currentMode.value === 'status') {
                if (typeof tasks.value[taskIndex].status === 'object') {
                    tasks.value[taskIndex].status.value = newValue;
                    tasks.value[taskIndex].status.label = newLabel;
                } else {
                    tasks.value[taskIndex].status = { value: newValue, label: newLabel };
                }
            } else if (currentMode.value === 'priority') {
                if (typeof tasks.value[taskIndex].priority === 'object') {
                    tasks.value[taskIndex].priority.value = newValue;
                    tasks.value[taskIndex].priority.label = newLabel;
                } else {
                    tasks.value[taskIndex].priority = { value: newValue, label: newLabel };
                }
            } else if (currentMode.value === 'milestone') {
                tasks.value[taskIndex].milestoneId = newValue;
                if (tasks.value[taskIndex].milestone) {
                    tasks.value[taskIndex].milestone.id = newValue;
                    tasks.value[taskIndex].milestone.name = newLabel;
                } else {
                    tasks.value[taskIndex].milestone = { id: newValue, name: newLabel };
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

        // Task card helper functions
        const getPriorityClasses = (task) => {
            const priority = task.priority?.value || task.priority || 'none';
            const classes = {
                'high': 'bg-red-100 text-red-700',
                'medium': 'bg-yellow-100 text-yellow-700',
                'low': 'bg-blue-100 text-blue-700',
                'none': 'bg-gray-100 text-gray-700'
            };
            return classes[priority] || classes['none'];
        };

        const getPriorityLabel = (task) => {
            return task.priority?.label || 'None';
        };

        const isTaskOverdue = (task) => {
            if (!task.dueDate) return false;
            const dueDate = new Date(task.dueDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return dueDate < today && task.status?.value !== 'completed';
        };

        const formatDueDate = (task) => {
            if (!task.dueDate) return null;
            const date = new Date(task.dueDate);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        };

        const getAssigneeInitials = (assignee) => {
            const firstName = assignee.user?.firstName || assignee.firstName || '';
            const lastName = assignee.user?.lastName || assignee.lastName || '';
            return (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
        };

        // Handle browser back/forward
        const handlePopState = (e) => {
            const urlParams = new URLSearchParams(window.location.search);
            const urlMode = urlParams.get('kanban');
            if (urlMode && urlMode !== currentMode.value) {
                currentMode.value = urlMode;
            }
        };

        // Handle task updates from external sources (e.g., task panel)
        const handleTaskUpdate = (e) => {
            const { taskId, field, value, label } = e.detail;
            const taskIndex = tasks.value.findIndex(t => t.id === taskId || t.id === parseInt(taskId));
            if (taskIndex === -1) return;

            const task = tasks.value[taskIndex];

            if (field === 'status') {
                if (typeof task.status === 'object') {
                    task.status.value = value;
                    task.status.label = label;
                } else {
                    tasks.value[taskIndex].status = { value, label };
                }
            } else if (field === 'priority') {
                if (typeof task.priority === 'object') {
                    task.priority.value = value;
                    task.priority.label = label;
                } else {
                    tasks.value[taskIndex].priority = { value, label };
                }
            } else if (field === 'milestone') {
                tasks.value[taskIndex].milestoneId = value;
                if (task.milestone) {
                    task.milestone.id = value;
                    task.milestone.name = label;
                } else {
                    tasks.value[taskIndex].milestone = { id: value, name: label };
                }
            } else if (field === 'title') {
                tasks.value[taskIndex].title = value;
            } else if (field === 'dueDate') {
                tasks.value[taskIndex].dueDate = value;
            }
        };

        onMounted(() => {
            loadSavedState();
            window.addEventListener('popstate', handlePopState);
            document.addEventListener('task-updated', handleTaskUpdate);
        });

        onUnmounted(() => {
            window.removeEventListener('popstate', handlePopState);
            document.removeEventListener('task-updated', handleTaskUpdate);
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
            handleTaskClick,
            getPriorityClasses,
            getPriorityLabel,
            isTaskOverdue,
            formatDueDate,
            getAssigneeInitials
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
                    <!-- Column header (shared between expanded/collapsed) -->
                    <div class="kanban-header p-4 transition-all duration-300" :class="{ 'pb-0': !isCollapsed(column.value) }">
                        <div class="flex items-center justify-between transition-all duration-300" :class="{ 'mb-4': !isCollapsed(column.value) }">
                            <div class="flex items-center gap-2 overflow-hidden">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium text-white whitespace-nowrap"
                                    :class="column.badgeColor"
                                >
                                    {{ column.label }} ({{ tasksByColumn[column.value]?.length || 0 }})
                                </span>
                            </div>
                            <button
                                type="button"
                                @click="toggleCollapse(column.value)"
                                class="p-1 rounded hover:bg-gray-200 text-gray-500 hover:text-gray-700 flex-shrink-0"
                                :title="isCollapsed(column.value) ? 'Expand' : 'Collapse'"
                            >
                                <svg
                                    class="w-4 h-4 transition-transform duration-300"
                                    :class="{ 'rotate-[-90deg]': isCollapsed(column.value) }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                        </div>
                        <p v-show="column.dueDate && !isCollapsed(column.value)" class="text-xs text-gray-500 -mt-2 mb-2 transition-opacity duration-300" :class="{ 'opacity-0': isCollapsed(column.value) }">
                            Due {{ new Date(column.dueDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                        </p>
                    </div>

                    <!-- Content area with animation -->
                    <div
                        class="kanban-content-wrapper transition-all duration-300 ease-in-out overflow-hidden"
                        :class="isCollapsed(column.value) ? 'max-h-0 opacity-0' : 'max-h-[2000px] opacity-100'"
                    >
                        <div class="kanban-content p-4 pt-0 overflow-y-auto">
                            <div class="kanban-dropzone space-y-3 min-h-[100px]">
                                <!-- Inline Task Card -->
                                <div
                                    v-for="task in tasksByColumn[column.value]"
                                    :key="task.id"
                                    :data-task-id="task.id"
                                    draggable="true"
                                    @click="handleTaskClick(task)"
                                    @dragstart="handleDragStart($event, task)"
                                    @dragend="handleDragEnd"
                                    class="task-card bg-white rounded-lg shadow-sm border border-gray-200 p-4 cursor-move hover:shadow-md transition-all duration-200"
                                    :class="{ 'opacity-40 scale-95': draggedTask?.id === task.id }"
                                >
                                    <div class="flex items-start justify-between">
                                        <h4 class="text-sm font-medium text-gray-900 flex-1">
                                            <a href="#" class="hover:text-primary-600" @click.prevent="handleTaskClick(task)">
                                                {{ task.title }}
                                            </a>
                                        </h4>
                                        <span
                                            class="ml-2 flex-shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="getPriorityClasses(task)"
                                        >
                                            {{ getPriorityLabel(task) }}
                                        </span>
                                    </div>
                                    <p v-if="task.projectName" class="mt-1 text-xs text-gray-500">{{ task.projectName }}</p>
                                    <div class="mt-3 flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <span v-if="formatDueDate(task)" class="flex items-center text-xs" :class="isTaskOverdue(task) ? 'text-red-600 font-medium' : 'text-gray-500'">
                                                <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                                </svg>
                                                {{ formatDueDate(task) }}
                                            </span>
                                            <span v-if="task.commentCount > 0" class="flex items-center text-xs text-gray-500">
                                                <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z" />
                                                </svg>
                                                {{ task.commentCount }}
                                            </span>
                                            <span v-if="task.checklistCount > 0" class="flex items-center text-xs text-gray-500">
                                                <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ task.completedChecklistCount || 0 }}/{{ task.checklistCount }}
                                            </span>
                                        </div>
                                        <div v-if="task.assignees?.length > 0" class="flex -space-x-1">
                                            <span
                                                v-for="assignee in task.assignees.slice(0, 3)"
                                                :key="assignee.id"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 ring-2 ring-white"
                                            >
                                                <span class="text-xs font-medium text-primary-700">{{ getAssigneeInitials(assignee) }}</span>
                                            </span>
                                            <span v-if="task.assignees.length > 3" class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 ring-2 ring-white text-xs font-medium text-gray-500">
                                                +{{ task.assignees.length - 3 }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Drop placeholder when empty and dragging -->
                                <div
                                    v-if="tasksByColumn[column.value]?.length === 0 && draggedTask"
                                    class="drop-placeholder"
                                >
                                    <span>Drop here</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state when no tasks -->
            <div v-if="tasks.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No tasks yet</h3>
                <p class="mt-1 text-sm text-gray-500">Create milestones first, then add tasks to them.</p>
            </div>
        </div>
    `
};
