import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';

export default {
    name: 'GanttView',

    props: {
        initialTasks: {
            type: Array,
            default: () => []
        },
        milestones: {
            type: Array,
            default: () => []
        },
        startDateUrlTemplate: {
            type: String,
            default: ''
        },
        dueDateUrlTemplate: {
            type: String,
            default: ''
        },
        progressUrlTemplate: {
            type: String,
            default: ''
        },
        viewMode: {
            type: String,
            default: 'Week' // Day, Week, Month, Year
        },
        storageKey: {
            type: String,
            default: 'gantt_view'
        }
    },

    setup(props) {
        const tasks = ref(Array.isArray(props.initialTasks) ? [...props.initialTasks] : []);
        const ganttContainer = ref(null);
        const currentViewMode = ref(props.viewMode);
        const ganttInstance = ref(null);
        const isUpdating = ref(false);

        const viewModes = ['Day', 'Week', 'Month', 'Year'];

        // Status colors for task bars
        const statusColors = {
            'todo': '#6b7280',
            'in_progress': '#3b82f6',
            'in_review': '#eab308',
            'completed': '#22c55e'
        };

        // Priority bar accents
        const priorityColors = {
            'high': '#ef4444',
            'medium': '#eab308',
            'low': '#3b82f6',
            'none': '#6b7280'
        };

        // Convert tasks to Frappe Gantt format
        const ganttTasks = computed(() => {
            return tasks.value
                .filter(task => task.startDate || task.dueDate)
                .map(task => {
                    // Default dates if missing
                    const today = new Date();
                    const startDate = task.startDate ? new Date(task.startDate) :
                        (task.dueDate ? new Date(new Date(task.dueDate).getTime() - 7 * 24 * 60 * 60 * 1000) : today);
                    const endDate = task.dueDate ? new Date(task.dueDate) :
                        new Date(startDate.getTime() + 7 * 24 * 60 * 60 * 1000);

                    // Calculate progress based on status
                    let progress = 0;
                    if (task.status?.value === 'completed') progress = 100;
                    else if (task.status?.value === 'in_review') progress = 75;
                    else if (task.status?.value === 'in_progress') progress = 50;
                    else if (task.status?.value === 'todo') progress = 0;

                    // Find dependencies (parent tasks)
                    const dependencies = task.parentId ? [task.parentId] : [];

                    return {
                        id: task.id,
                        name: task.title || 'Untitled Task',
                        start: formatDate(startDate),
                        end: formatDate(endDate),
                        progress: progress,
                        dependencies: dependencies.join(', '),
                        custom_class: `gantt-task-${task.status?.value || 'todo'} gantt-priority-${task.priority?.value || 'none'}`
                    };
                });
        });

        // Tasks without dates (shown in sidebar)
        const unscheduledTasks = computed(() => {
            return tasks.value.filter(task => !task.startDate && !task.dueDate);
        });

        function formatDate(date) {
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function parseDate(dateStr) {
            const [year, month, day] = dateStr.split('-').map(Number);
            return new Date(year, month - 1, day);
        }

        // Initialize or update Gantt chart
        function initGantt() {
            if (!ganttContainer.value || !window.Gantt) {
                console.warn('Gantt container or library not available');
                return;
            }

            const ganttData = ganttTasks.value;

            if (ganttData.length === 0) {
                // Show empty state
                ganttContainer.value.innerHTML = '';
                return;
            }

            // Clear previous instance
            if (ganttInstance.value) {
                ganttContainer.value.innerHTML = '';
            }

            try {
                ganttInstance.value = new window.Gantt(ganttContainer.value, ganttData, {
                    view_mode: currentViewMode.value,
                    date_format: 'YYYY-MM-DD',
                    language: 'en',
                    popup_trigger: 'click',
                    custom_popup_html: function(task) {
                        const originalTask = tasks.value.find(t => t.id === task.id);
                        if (!originalTask) return '';

                        const statusBadge = originalTask.status ?
                            `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                   style="background-color: ${statusColors[originalTask.status.value] || '#6b7280'}20;
                                          color: ${statusColors[originalTask.status.value] || '#6b7280'}">
                                ${originalTask.status.label || originalTask.status.value}
                            </span>` : '';

                        const assignees = originalTask.assignees?.length > 0 ?
                            originalTask.assignees.map(a => a.user?.fullName || 'Unknown').join(', ') : 'Unassigned';

                        return `
                            <div class="gantt-popup bg-white rounded-lg shadow-lg p-4 min-w-64">
                                <div class="font-semibold text-gray-900 mb-2">${task.name}</div>
                                <div class="flex items-center gap-2 mb-2">${statusBadge}</div>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <div><span class="font-medium">Start:</span> ${task.start}</div>
                                    <div><span class="font-medium">End:</span> ${task.end}</div>
                                    <div><span class="font-medium">Assignees:</span> ${assignees}</div>
                                    <div><span class="font-medium">Progress:</span> ${task.progress}%</div>
                                </div>
                                <div class="mt-3 pt-3 border-t">
                                    <a href="/tasks/${task.id}"
                                       class="text-sm text-primary-600 hover:text-primary-800 font-medium">
                                        View Details →
                                    </a>
                                </div>
                            </div>
                        `;
                    },
                    on_click: function(task) {
                        // Open task panel or navigate
                        if (typeof window.openTaskPanel === 'function') {
                            window.openTaskPanel(task.id);
                        }
                    },
                    on_date_change: async function(task, start, end) {
                        await updateTaskDates(task.id, start, end);
                    },
                    on_progress_change: async function(task, progress) {
                        // Could update status based on progress if needed
                        console.log('Progress changed:', task.id, progress);
                    },
                    on_view_change: function(mode) {
                        currentViewMode.value = mode;
                        saveViewPreference(mode);
                    }
                });
            } catch (error) {
                console.error('Failed to initialize Gantt chart:', error);
            }
        }

        async function updateTaskDates(taskId, startDate, endDate) {
            if (isUpdating.value) return;
            isUpdating.value = true;

            try {
                // Update start date
                if (props.startDateUrlTemplate) {
                    const startUrl = props.startDateUrlTemplate.replace('__TASK_ID__', taskId);
                    await fetch(startUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ startDate: formatDate(startDate) })
                    });
                }

                // Update due date
                if (props.dueDateUrlTemplate) {
                    const dueUrl = props.dueDateUrlTemplate.replace('__TASK_ID__', taskId);
                    await fetch(dueUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ dueDate: formatDate(endDate) })
                    });
                }

                // Update local state
                const taskIndex = tasks.value.findIndex(t => t.id === taskId);
                if (taskIndex !== -1) {
                    tasks.value[taskIndex] = {
                        ...tasks.value[taskIndex],
                        startDate: formatDate(startDate),
                        dueDate: formatDate(endDate)
                    };
                }

                // Dispatch event for other components
                document.dispatchEvent(new CustomEvent('task-updated', {
                    detail: {
                        taskId,
                        field: 'dates',
                        startDate: formatDate(startDate),
                        dueDate: formatDate(endDate)
                    }
                }));

                if (window.Toastr) {
                    window.Toastr.success('Task Updated', 'Task dates have been updated');
                }
            } catch (error) {
                console.error('Failed to update task dates:', error);
                if (window.Toastr) {
                    window.Toastr.error('Update Failed', 'Could not update task dates');
                }
                // Refresh gantt to revert visual changes
                initGantt();
            } finally {
                isUpdating.value = false;
            }
        }

        function changeViewMode(mode) {
            currentViewMode.value = mode;
            if (ganttInstance.value) {
                ganttInstance.value.change_view_mode(mode);
            }
            saveViewPreference(mode);
        }

        function saveViewPreference(mode) {
            try {
                localStorage.setItem(`${props.storageKey}_viewMode`, mode);
            } catch (e) {}
        }

        function loadViewPreference() {
            try {
                const saved = localStorage.getItem(`${props.storageKey}_viewMode`);
                if (saved && viewModes.includes(saved)) {
                    currentViewMode.value = saved;
                }
            } catch (e) {}
        }

        function scrollToToday() {
            if (ganttInstance.value) {
                ganttInstance.value.scroll_today();
            }
        }

        // Handle external task updates
        function handleTaskUpdate(event) {
            const { taskId, field, value, startDate, dueDate } = event.detail || {};
            if (!taskId) return;

            const taskIndex = tasks.value.findIndex(t => t.id === taskId);
            if (taskIndex === -1) return;

            if (field === 'dates' || field === 'dueDate' || field === 'startDate') {
                if (startDate) tasks.value[taskIndex].startDate = startDate;
                if (dueDate) tasks.value[taskIndex].dueDate = dueDate;
                // Refresh gantt
                nextTick(() => initGantt());
            } else if (field === 'status') {
                tasks.value[taskIndex].status = value;
                nextTick(() => initGantt());
            }
        }

        onMounted(() => {
            loadViewPreference();

            // Wait for Gantt library to be available
            const checkGantt = () => {
                if (window.Gantt) {
                    nextTick(() => initGantt());
                } else {
                    setTimeout(checkGantt, 100);
                }
            };
            checkGantt();

            // Listen for task updates from other components
            document.addEventListener('task-updated', handleTaskUpdate);
        });

        onUnmounted(() => {
            document.removeEventListener('task-updated', handleTaskUpdate);
        });

        // Watch for task changes
        watch(() => props.initialTasks, (newTasks) => {
            tasks.value = Array.isArray(newTasks) ? [...newTasks] : [];
            nextTick(() => initGantt());
        }, { deep: true });

        return {
            tasks,
            ganttContainer,
            currentViewMode,
            viewModes,
            ganttTasks,
            unscheduledTasks,
            changeViewMode,
            scrollToToday,
            isUpdating
        };
    },

    template: `
        <div class="gantt-view-container">
            <!-- Toolbar -->
            <div class="flex items-center justify-between mb-4 bg-white rounded-lg shadow-sm p-3">
                <div class="flex items-center gap-2">
                    <!-- View Mode Buttons -->
                    <div class="inline-flex rounded-lg bg-gray-100 p-1">
                        <button
                            v-for="mode in viewModes"
                            :key="mode"
                            @click="changeViewMode(mode)"
                            :class="[
                                'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                                currentViewMode === mode
                                    ? 'bg-white text-primary-600 shadow-sm'
                                    : 'text-gray-600 hover:text-gray-900'
                            ]"
                        >
                            {{ mode }}
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Today Button -->
                    <button
                        @click="scrollToToday"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        Today
                    </button>

                    <!-- Task Count -->
                    <span class="text-sm text-gray-500">
                        {{ ganttTasks.length }} task{{ ganttTasks.length !== 1 ? 's' : '' }} scheduled
                    </span>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex gap-4">
                <!-- Gantt Chart -->
                <div class="flex-1 bg-white rounded-lg shadow-sm overflow-hidden">
                    <div v-if="ganttTasks.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-500">
                        <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                        </svg>
                        <p class="text-lg font-medium mb-1">No scheduled tasks</p>
                        <p class="text-sm">Add start and due dates to tasks to see them in the Gantt chart</p>
                    </div>
                    <div ref="ganttContainer" class="gantt-chart-wrapper"></div>
                </div>

                <!-- Unscheduled Tasks Sidebar -->
                <div v-if="unscheduledTasks.length > 0" class="w-64 flex-shrink-0">
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="font-medium text-gray-900 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Unscheduled ({{ unscheduledTasks.length }})
                        </h3>
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            <div
                                v-for="task in unscheduledTasks"
                                :key="task.id"
                                class="p-2 rounded border border-gray-200 hover:border-primary-300 hover:bg-primary-50 cursor-pointer transition-colors"
                                @click="$emit('task-click', task.id)"
                            >
                                <div class="text-sm font-medium text-gray-900 truncate">{{ task.title }}</div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                        :style="{
                                            backgroundColor: (task.status?.value === 'completed' ? '#22c55e' :
                                                task.status?.value === 'in_review' ? '#eab308' :
                                                task.status?.value === 'in_progress' ? '#3b82f6' : '#6b7280') + '20',
                                            color: task.status?.value === 'completed' ? '#22c55e' :
                                                task.status?.value === 'in_review' ? '#eab308' :
                                                task.status?.value === 'in_progress' ? '#3b82f6' : '#6b7280'
                                        }"
                                    >
                                        {{ task.status?.label || 'To Do' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div v-if="isUpdating" class="fixed inset-0 bg-black/10 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-lg p-4 flex items-center gap-3">
                    <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-gray-700">Updating task...</span>
                </div>
            </div>
        </div>
    `
};
