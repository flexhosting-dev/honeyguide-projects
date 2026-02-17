import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import ContextMenu from './TaskTable/ContextMenu.js';
import ConfirmDialog from './ConfirmDialog.js';

export default {
    name: 'GanttView',

    components: {
        ContextMenu,
        ConfirmDialog
    },

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
        statusUrlTemplate: {
            type: String,
            default: ''
        },
        priorityUrlTemplate: {
            type: String,
            default: ''
        },
        assigneeUrlTemplate: {
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
        },
        taskUrlTemplate: {
            type: String,
            default: '/tasks/__TASK_ID__'
        },
        members: {
            type: Array,
            default: () => []
        },
        statusOptions: {
            type: Array,
            default: () => []
        },
        canEdit: {
            type: Boolean,
            default: false
        },
        createUrl: {
            type: String,
            default: ''
        },
        subtaskUrlTemplate: {
            type: String,
            default: ''
        },
        defaultMilestoneId: {
            type: String,
            default: ''
        },
        bulkDeleteUrl: {
            type: String,
            default: ''
        }
    },

    setup(props) {
        const tasks = ref(Array.isArray(props.initialTasks) ? [...props.initialTasks] : []);
        const ganttContainer = ref(null);
        const taskListRef = ref(null);
        const currentViewMode = ref(props.viewMode);
        const ganttInstance = ref(null);
        const isUpdating = ref(false);
        const isSyncingScroll = ref(false);
        const hoveredTaskId = ref(null);
        const taskSortMode = ref('start_date'); // 'start_date', 'position', 'title'
        const collapsedTaskIds = ref(new Set());
        const groupBy = ref('milestone'); // 'none' or 'milestone'
        const collapsedGroups = ref(new Set());
        const taskListWidth = ref(192); // Default w-48 = 192px
        const isResizing = ref(false);
        const minTaskListWidth = 120;
        const maxTaskListWidth = 400;
        const isMobile = ref(window.innerWidth < 768);

        // Frappe Gantt dimensions
        const GANTT_ROW_HEIGHT = 38; // bar_height (20) + padding (18)
        const ganttHeaderHeight = ref(60); // Approximate header height

        // Context menu state
        const contextMenu = ref({
            visible: false,
            x: 0,
            y: 0,
            tasks: []
        });

        // Long press timer for mobile context menu
        let longPressTimer = null;
        const longPressDelay = 500;

        // Confirm dialog ref
        const confirmDialogRef = ref(null);
        const isDeleting = ref(false);

        const viewModes = ['Day', 'Week', 'Month', 'Year'];

        // Default status and priority configs (fallback if not passed from props)
        const defaultStatusConfig = [
            { value: 'todo', label: 'To Do', color: '#6B7280' },
            { value: 'in_progress', label: 'In Progress', color: '#3B82F6' },
            { value: 'completed', label: 'Completed', color: '#10B981' }
        ];

        const priorityConfig = [
            { value: 'high', label: 'High', color: '#ef4444' },
            { value: 'medium', label: 'Medium', color: '#eab308' },
            { value: 'low', label: 'Low', color: '#3b82f6' },
            { value: 'none', label: 'None', color: '#6b7280' }
        ];

        // Computed options for ContextMenu - use props if available, otherwise fallback
        const computedStatusOptions = computed(() => {
            if (props.statusOptions && props.statusOptions.length > 0) {
                return props.statusOptions.map(s => ({
                    value: s.value,
                    label: s.label,
                    color: s.color
                }));
            }
            return defaultStatusConfig.map(s => ({
                value: s.value,
                label: s.label,
                color: s.color
            }));
        });

        const priorityOptions = computed(() => priorityConfig.map(p => ({
            value: p.value,
            label: p.label
        })));

        // Get status color from task or fallback to default
        const getStatusColor = (task) => {
            if (task?.status?.color) return task.status.color;
            // Fallback for legacy statuses
            const fallbackColors = {
                'todo': '#6B7280',
                'open': '#6B7280',
                'in_progress': '#3B82F6',
                'in_review': '#3B82F6',
                'completed': '#10B981',
                'cancelled': '#EF4444'
            };
            return fallbackColors[task?.status?.value] || '#6B7280';
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
            const filtered = tasks.value
                .map((task, index) => ({ ...task, _originalIndex: index }))
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
                    const statusValue = task.status?.value || 'todo';
                    if (statusValue === 'completed' || statusValue === 'cancelled') progress = 100;
                    else if (statusValue === 'in_review') progress = 75;
                    else if (statusValue === 'in_progress') progress = 50;
                    else progress = 0; // todo, open, etc.

                    // Find dependencies (parent tasks)
                    const dependencies = task.parentId ? [task.parentId] : [];

                    return {
                        id: task.id,
                        name: task.title || 'Untitled Task',
                        start: formatDate(startDate),
                        end: formatDate(endDate),
                        progress: progress,
                        dependencies: dependencies.join(', '),
                        custom_class: `gantt-task-${task.status?.value || 'todo'} gantt-priority-${task.priority?.value || 'none'}`,
                        _originalIndex: task._originalIndex,
                        depth: task.depth || 0,
                        parentId: task.parentId || null,
                        statusColor: task.status?.color || getStatusColor(task)
                    };
                });

            // Apply sorting based on selected mode
            // Use _originalIndex as secondary sort for stable ordering
            let sorted = [...filtered];
            switch (taskSortMode.value) {
                case 'start_date':
                    sorted.sort((a, b) => {
                        const dateDiff = new Date(a.start) - new Date(b.start);
                        if (dateDiff !== 0) return dateDiff;
                        return a._originalIndex - b._originalIndex;
                    });
                    break;
                case 'title':
                    sorted.sort((a, b) => {
                        const titleDiff = a.name.localeCompare(b.name);
                        if (titleDiff !== 0) return titleDiff;
                        return a._originalIndex - b._originalIndex;
                    });
                    break;
                case 'position':
                default:
                    sorted.sort((a, b) => a._originalIndex - b._originalIndex);
                    break;
            }

            // After sorting, ensure tasks with _insertedAdjacentTo are placed correctly
            // This ensures newly added tasks appear next to their target regardless of sort
            const tasksToReposition = sorted.filter(t => t._insertedAdjacentTo);
            if (tasksToReposition.length > 0) {
                // Remove these tasks from sorted array
                sorted = sorted.filter(t => !t._insertedAdjacentTo);

                // Re-insert each one next to its target
                for (const task of tasksToReposition) {
                    const targetIndex = sorted.findIndex(t => t.id === task._insertedAdjacentTo);
                    if (targetIndex !== -1) {
                        // Insert after the target (for "below") or at target position (for "above")
                        const insertPos = task._insertedAbove ? targetIndex : targetIndex + 1;
                        sorted.splice(insertPos, 0, task);
                    } else {
                        // Target not found, add to end
                        sorted.push(task);
                    }
                }
            }

            return sorted;
        });

        // Tasks without dates (shown in sidebar)
        const unscheduledTasks = computed(() => {
            return tasks.value.filter(task => !task.startDate && !task.dueDate);
        });

        // Set of task IDs that have children
        const tasksWithChildren = computed(() => {
            const parentIds = new Set();
            ganttTasks.value.forEach(task => {
                if (task.parentId) {
                    parentIds.add(task.parentId);
                }
            });
            return parentIds;
        });

        // Effective task list width (same as desktop, user can resize)
        const effectiveTaskListWidth = computed(() => {
            return taskListWidth.value;
        });

        // Check if a task or any of its ancestors is collapsed
        function isTaskHidden(task, allTasks) {
            if (!task.parentId) return false;

            // Check if direct parent is collapsed
            if (collapsedTaskIds.value.has(task.parentId)) {
                return true;
            }

            // Check ancestors recursively
            const parent = allTasks.find(t => t.id === task.parentId);
            if (parent) {
                return isTaskHidden(parent, allTasks);
            }

            return false;
        }

        // Visible tasks (filtering out children of collapsed tasks)
        const visibleGanttTasks = computed(() => {
            const allTasks = ganttTasks.value;
            return allTasks.filter(task => !isTaskHidden(task, allTasks));
        });

        // Get milestone info for a task
        const getMilestoneInfo = (milestoneId) => {
            if (!milestoneId) return { label: 'No Milestone', color: '#6b7280' };
            const milestone = props.milestones.find(m => m.id === milestoneId);
            return {
                label: milestone?.name || 'Unknown Milestone',
                color: '#6366f1',
                dueDate: milestone?.dueDate
            };
        };

        // Get milestone ID for a task (from original tasks array)
        const getTaskMilestoneId = (taskId) => {
            const task = tasks.value.find(t => t.id === taskId);
            return task?.milestoneId || '__no_milestone__';
        };

        // Computed: display items with milestone grouping
        const displayItems = computed(() => {
            const visibleTasks = visibleGanttTasks.value;

            if (groupBy.value === 'none') {
                return visibleTasks.map(task => ({
                    type: 'task',
                    task
                }));
            }

            // Group by milestone
            const groups = {};
            const groupOrder = [];

            // Use milestone order from props if available
            props.milestones.forEach(m => {
                groups[m.id] = [];
                groupOrder.push(m.id);
            });
            // Add no-milestone group
            groups['__no_milestone__'] = [];

            // Group tasks
            visibleTasks.forEach(task => {
                const milestoneId = getTaskMilestoneId(task.id);
                if (!groups[milestoneId]) {
                    groups[milestoneId] = [];
                    groupOrder.push(milestoneId);
                }
                groups[milestoneId].push(task);
            });

            // Add no-milestone group to end if it has tasks
            if (groups['__no_milestone__'].length > 0 && !groupOrder.includes('__no_milestone__')) {
                groupOrder.push('__no_milestone__');
            }

            // Build result with group headers
            const result = [];
            groupOrder.forEach(key => {
                const groupTasks = groups[key];
                if (!groupTasks || groupTasks.length === 0) return;

                const info = key === '__no_milestone__'
                    ? { label: 'No Milestone', color: '#6b7280' }
                    : getMilestoneInfo(key);

                const completedCount = groupTasks.filter(t => {
                    const originalTask = tasks.value.find(ot => ot.id === t.id);
                    return originalTask?.status?.value === 'completed';
                }).length;

                result.push({
                    type: 'group',
                    groupKey: key,
                    groupLabel: info.label,
                    groupColor: info.color,
                    dueDate: info.dueDate,
                    taskCount: groupTasks.length,
                    completedCount,
                    isCollapsed: collapsedGroups.value.has(key)
                });

                if (!collapsedGroups.value.has(key)) {
                    groupTasks.forEach(task => {
                        result.push({ type: 'task', task });
                    });
                }
            });

            return result;
        });

        // Tasks to show in Gantt (excludes tasks in collapsed groups)
        const ganttDisplayTasks = computed(() => {
            return displayItems.value
                .filter(item => item.type === 'task')
                .map(item => item.task);
        });

        // Toggle milestone group collapse
        function toggleGroupCollapse(groupKey) {
            const newCollapsed = new Set(collapsedGroups.value);
            if (newCollapsed.has(groupKey)) {
                newCollapsed.delete(groupKey);
            } else {
                newCollapsed.add(groupKey);
            }
            collapsedGroups.value = newCollapsed;
            nextTick(() => initGantt());
        }

        // Expand all milestone groups
        function expandAllGroups() {
            collapsedGroups.value = new Set();
            nextTick(() => initGantt());
        }

        // Collapse all milestone groups
        function collapseAllGroups() {
            const allGroupKeys = displayItems.value
                .filter(item => item.type === 'group')
                .map(item => item.groupKey);
            collapsedGroups.value = new Set(allGroupKeys);
            nextTick(() => initGantt());
        }

        // Toggle grouping mode
        function toggleGroupBy() {
            groupBy.value = groupBy.value === 'none' ? 'milestone' : 'none';
            saveGroupPreference();
            nextTick(() => initGantt());
        }

        function saveGroupPreference() {
            try {
                localStorage.setItem(`${props.storageKey}_groupBy`, groupBy.value);
            } catch (e) {}
        }

        function loadGroupPreference() {
            try {
                const saved = localStorage.getItem(`${props.storageKey}_groupBy`);
                if (saved && ['none', 'milestone'].includes(saved)) {
                    groupBy.value = saved;
                }
            } catch (e) {}
        }

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

            // Use ganttTasks (all tasks with dates), native Gantt handles collapsed groups
            const ganttData = ganttTasks.value.map(task => {
                const originalTask = tasks.value.find(t => t.id === task.id);
                return {
                    ...task,
                    // Add milestoneId for grouping
                    milestoneId: getTaskMilestoneId(task.id),
                    // Add status info for display
                    status: originalTask?.status,
                    statusColor: getStatusColor(originalTask),
                    depth: task.depth || 0
                };
            });

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
                // Build groups array from milestones
                const milestoneGroups = props.milestones.map(m => ({
                    id: m.id,
                    name: m.name,
                    color: '#6366f1'
                }));

                ganttInstance.value = new window.Gantt(ganttContainer.value, ganttData, {
                    view_mode: currentViewMode.value,
                    date_format: 'YYYY-MM-DD',
                    language: 'en',
                    popup_trigger: 'click',
                    // Native sidebar with grouping support
                    sidebar: {
                        enabled: true,
                        width: taskListWidth.value,
                        min_width: 120,
                        max_width: 400,
                        resizable: true
                    },
                    // Enable grouping by milestone
                    grouping: {
                        enabled: groupBy.value === 'milestone',
                        field: 'milestoneId',
                        groups: milestoneGroups,
                        collapsed_groups: [...collapsedGroups.value],
                        ungrouped_label: 'No Milestone'
                    },
                    // Callbacks for sidebar interactions
                    on_group_collapse: (group, collapsed) => {
                        if (collapsed) {
                            collapsedGroups.value.add(group.id);
                        } else {
                            collapsedGroups.value.delete(group.id);
                        }
                    },
                    on_sidebar_task_click: (task) => {
                        openTask(task.id);
                    },
                    on_sidebar_resize: (newWidth) => {
                        taskListWidth.value = newWidth;
                    },
                    custom_popup_html: function(task) {
                        const originalTask = tasks.value.find(t => t.id === task.id);
                        if (!originalTask) return '';

                        const statusColor = getStatusColor(originalTask);
                        const statusBadge = originalTask.status ?
                            `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                   style="background-color: ${statusColor}20;
                                          color: ${statusColor}">
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
                                    <button onclick="if(window.openTaskPanel){window.openTaskPanel('${task.id}');document.querySelector('.gantt-popup')?.closest('.popup-wrapper')?.remove();}"
                                       class="text-sm text-primary-600 hover:text-primary-800 font-medium cursor-pointer bg-transparent border-none p-0">
                                        View Details →
                                    </button>
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

                // Apply styling after render
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        drawTodayLine();
                        applyBarColors();
                    }, 100);
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
                // Redraw today line after view mode change
                nextTick(() => drawTodayLine());
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
            if (!ganttInstance.value || !ganttContainer.value) return;

            const container = ganttContainer.value.querySelector('.gantt-container');
            if (!container) return;

            // Calculate today's position
            const today = new Date();
            const ganttStart = ganttInstance.value.gantt_start;
            const step = ganttInstance.value.options.step;
            const columnWidth = ganttInstance.value.options.column_width;

            // Calculate hours difference
            const hoursDiff = (today - ganttStart) / (1000 * 60 * 60);
            const scrollPosition = (hoursDiff / step) * columnWidth - (container.clientWidth / 2);

            container.scrollLeft = Math.max(0, scrollPosition);
        }

        // Draw today line on the Gantt chart
        function drawTodayLine() {
            if (!ganttInstance.value || !ganttContainer.value) return;

            const svg = ganttContainer.value.querySelector('svg.gantt');
            if (!svg) return;

            // Remove existing today line
            const existingLine = svg.querySelector('.today-line');
            if (existingLine) existingLine.remove();

            const today = new Date();
            const ganttStart = ganttInstance.value.gantt_start;
            const step = ganttInstance.value.options.step;
            const columnWidth = ganttInstance.value.options.column_width;

            // Calculate x position for today
            const hoursDiff = (today - ganttStart) / (1000 * 60 * 60);
            const x = (hoursDiff / step) * columnWidth;

            // Get chart height
            const gridBackground = svg.querySelector('.grid-background');
            if (!gridBackground) return;
            const height = gridBackground.getAttribute('height');

            // Create today line group
            const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            g.setAttribute('class', 'today-line');

            // Create the line
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', x);
            line.setAttribute('y1', 0);
            line.setAttribute('x2', x);
            line.setAttribute('y2', height);
            line.setAttribute('stroke', '#3b82f6');
            line.setAttribute('stroke-width', '2');
            line.setAttribute('stroke-dasharray', '4,4');

            // Create a small circle at the top
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', x);
            circle.setAttribute('cy', '8');
            circle.setAttribute('r', '4');
            circle.setAttribute('fill', '#3b82f6');

            g.appendChild(line);
            g.appendChild(circle);
            svg.appendChild(g);
        }

        // Apply status colors to Gantt bars
        function applyBarColors() {
            if (!ganttContainer.value) return;

            const svg = ganttContainer.value.querySelector('svg.gantt');
            if (!svg) return;

            // Get all bar wrappers
            const barWrappers = svg.querySelectorAll('.bar-wrapper');
            barWrappers.forEach(wrapper => {
                const taskId = wrapper.getAttribute('data-id');
                if (!taskId) return;

                // Find the task to get its status color
                const ganttTask = visibleGanttTasks.value.find(t => t.id === taskId);
                if (!ganttTask || !ganttTask.statusColor) return;

                // Find the bar and bar-progress elements
                const bar = wrapper.querySelector('.bar');
                const barProgress = wrapper.querySelector('.bar-progress');

                if (bar) {
                    bar.style.fill = ganttTask.statusColor;
                }
                if (barProgress) {
                    // Make progress bar slightly darker
                    barProgress.style.fill = ganttTask.statusColor;
                    barProgress.style.filter = 'brightness(0.85)';
                }
            });
        }

        // Scroll synchronization between task list and Gantt chart
        function syncScrollFromGantt(event) {
            if (isSyncingScroll.value || !taskListRef.value) return;
            isSyncingScroll.value = true;
            taskListRef.value.scrollTop = event.target.scrollTop;
            requestAnimationFrame(() => {
                isSyncingScroll.value = false;
            });
        }

        function syncScrollFromTaskList(event) {
            if (isSyncingScroll.value || !ganttContainer.value) return;
            const ganttScrollContainer = ganttContainer.value.querySelector('.gantt-container');
            if (!ganttScrollContainer) return;
            isSyncingScroll.value = true;
            ganttScrollContainer.scrollTop = event.target.scrollTop;
            requestAnimationFrame(() => {
                isSyncingScroll.value = false;
            });
        }

        function setupScrollSync() {
            if (!ganttContainer.value) return;
            const ganttScrollContainer = ganttContainer.value.querySelector('.gantt-container');
            if (ganttScrollContainer) {
                ganttScrollContainer.addEventListener('scroll', syncScrollFromGantt);
            }
        }

        function cleanupScrollSync() {
            if (!ganttContainer.value) return;
            const ganttScrollContainer = ganttContainer.value.querySelector('.gantt-container');
            if (ganttScrollContainer) {
                ganttScrollContainer.removeEventListener('scroll', syncScrollFromGantt);
            }
        }

        function handleTaskHover(taskId) {
            hoveredTaskId.value = taskId;
        }

        function handleTaskLeave() {
            hoveredTaskId.value = null;
        }

        function openTask(taskId) {
            if (typeof window.openTaskPanel === 'function') {
                window.openTaskPanel(taskId);
            }
        }

        // Reorder Gantt bars to match our display order (including milestone groups)
        function reorderGanttBars() {
            if (!ganttContainer.value || !ganttInstance.value) return;

            const svg = ganttContainer.value.querySelector('svg.gantt');
            if (!svg) return;

            const barWrappers = Array.from(svg.querySelectorAll('.bar-wrapper'));
            if (barWrappers.length === 0) return;

            const barMap = new Map();
            barWrappers.forEach(bar => {
                const taskId = bar.getAttribute('data-id');
                if (taskId) {
                    barMap.set(taskId, bar);
                }
            });

            const updateBarY = (barWrapper, newY) => {
                const barRect = barWrapper.querySelector('.bar');
                if (barRect) barRect.setAttribute('y', newY);
                const progressRect = barWrapper.querySelector('.bar-progress');
                if (progressRect) progressRect.setAttribute('y', newY);
                const label = barWrapper.querySelector('.bar-label');
                if (label) label.setAttribute('y', newY + 10);
                const handles = barWrapper.querySelectorAll('.handle');
                handles.forEach(handle => handle.setAttribute('y', newY + 1));
            };

            // Build display index map accounting for milestone headers
            let displayIndex = 0;
            const taskDisplayIndexMap = new Map();
            const groupRowIndices = [];

            displayItems.value.forEach(item => {
                if (item.type === 'group') {
                    groupRowIndices.push(displayIndex);
                    displayIndex++;
                } else if (item.type === 'task') {
                    taskDisplayIndexMap.set(item.task.id, displayIndex);
                    displayIndex++;
                }
            });

            // Position each bar at its correct display index
            taskDisplayIndexMap.forEach((rowIndex, taskId) => {
                const barWrapper = barMap.get(taskId);
                if (barWrapper) {
                    const newY = ganttHeaderHeight.value + 9 + (rowIndex * GANTT_ROW_HEIGHT);
                    updateBarY(barWrapper, newY);
                }
            });

            // Update SVG and grid heights
            const totalRows = displayIndex;
            const newHeight = ganttHeaderHeight.value + (totalRows * GANTT_ROW_HEIGHT) + 20;
            svg.setAttribute('height', newHeight);

            const gridBackground = svg.querySelector('.grid-background');
            const gridWidth = gridBackground?.getAttribute('width') || '2000';
            if (gridBackground) gridBackground.setAttribute('height', newHeight);

            // Rebuild grid rows
            const gridLayer = svg.querySelector('.grid');
            if (gridLayer) {
                const gridRowsGroup = gridLayer.querySelector('g:nth-child(2)');
                const gridLinesGroup = gridLayer.querySelector('g:nth-child(3)');

                if (gridRowsGroup) {
                    gridRowsGroup.innerHTML = '';
                    for (let i = 0; i < totalRows; i++) {
                        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                        rect.setAttribute('class', 'grid-row');
                        rect.setAttribute('x', '0');
                        rect.setAttribute('y', (ganttHeaderHeight.value + i * GANTT_ROW_HEIGHT).toString());
                        rect.setAttribute('width', gridWidth);
                        rect.setAttribute('height', GANTT_ROW_HEIGHT.toString());
                        rect.setAttribute('fill', groupRowIndices.includes(i) ? '#f3f4f6' : (i % 2 === 0 ? '#ffffff' : '#f9fafb'));
                        gridRowsGroup.appendChild(rect);
                    }
                }

                if (gridLinesGroup) {
                    gridLinesGroup.innerHTML = '';
                    for (let i = 0; i < totalRows; i++) {
                        const lineY = ganttHeaderHeight.value + (i + 1) * GANTT_ROW_HEIGHT;
                        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                        line.setAttribute('class', 'row-line');
                        line.setAttribute('x1', '0');
                        line.setAttribute('y1', lineY.toString());
                        line.setAttribute('x2', gridWidth);
                        line.setAttribute('y2', lineY.toString());
                        gridLinesGroup.appendChild(line);
                    }
                }
            }
        }

        // Resize functionality
        function startResize(event) {
            isResizing.value = true;
            document.addEventListener('mousemove', handleResize);
            document.addEventListener('mouseup', stopResize);
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        }

        function handleResize(event) {
            if (!isResizing.value) return;
            const container = ganttContainer.value?.closest('.flex.gap-0');
            if (!container) return;
            const containerRect = container.getBoundingClientRect();
            let newWidth = event.clientX - containerRect.left;
            newWidth = Math.max(minTaskListWidth, Math.min(maxTaskListWidth, newWidth));
            taskListWidth.value = newWidth;
        }

        function stopResize() {
            isResizing.value = false;
            document.removeEventListener('mousemove', handleResize);
            document.removeEventListener('mouseup', stopResize);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            saveTaskListWidth();
        }

        function saveTaskListWidth() {
            try {
                localStorage.setItem(`${props.storageKey}_taskListWidth`, taskListWidth.value.toString());
            } catch (e) {}
        }

        function loadTaskListWidth() {
            try {
                const saved = localStorage.getItem(`${props.storageKey}_taskListWidth`);
                if (saved) {
                    const width = parseInt(saved, 10);
                    if (width >= minTaskListWidth && width <= maxTaskListWidth) {
                        taskListWidth.value = width;
                    }
                }
            } catch (e) {}
        }

        // Handle external task updates
        function handleTaskUpdate(event) {
            const { taskId, field, value, startDate, dueDate, label } = event.detail || {};
            if (!taskId) return;

            const taskIndex = tasks.value.findIndex(t => t.id === taskId);
            if (taskIndex === -1) return;

            let needsRefresh = false;

            switch (field) {
                case 'dates':
                    if (startDate) tasks.value[taskIndex].startDate = startDate;
                    if (dueDate) tasks.value[taskIndex].dueDate = dueDate;
                    needsRefresh = true;
                    break;
                case 'startDate':
                    tasks.value[taskIndex].startDate = value;
                    needsRefresh = true;
                    break;
                case 'dueDate':
                    tasks.value[taskIndex].dueDate = value;
                    needsRefresh = true;
                    break;
                case 'status':
                    const statusColor = event.detail.color;
                    tasks.value[taskIndex].status = { value: value, label: label || value, color: statusColor || tasks.value[taskIndex].status?.color };
                    needsRefresh = true;
                    break;
                case 'title':
                    tasks.value[taskIndex].title = value;
                    needsRefresh = true;
                    break;
                case 'priority':
                    tasks.value[taskIndex].priority = { value: value, label: label || value };
                    needsRefresh = true;
                    break;
            }

            if (needsRefresh) {
                nextTick(() => initGantt());
            }
        }

        // Handle window resize for mobile detection
        function handleWindowResize() {
            isMobile.value = window.innerWidth < 768;
        }

        // Context menu functions
        function showContextMenu(task, event) {
            event.preventDefault();
            event.stopPropagation();

            // Get the original task data (not the Gantt-formatted one)
            const originalTask = tasks.value.find(t => t.id === task.id);
            if (!originalTask) return;

            contextMenu.value = {
                visible: true,
                x: event.clientX,
                y: event.clientY,
                tasks: [originalTask]
            };
        }

        function hideContextMenu() {
            contextMenu.value.visible = false;
        }

        function handleTaskContextMenu(task, event) {
            showContextMenu(task, event);
        }

        // Long press handlers for mobile
        function handleTouchStart(task, event) {
            longPressTimer = setTimeout(() => {
                // Create a synthetic event with touch coordinates
                const touch = event.touches[0];
                showContextMenu(task, {
                    preventDefault: () => {},
                    stopPropagation: () => {},
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
            }, longPressDelay);
        }

        function handleTouchEnd() {
            if (longPressTimer) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
        }

        function handleTouchMove() {
            if (longPressTimer) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
        }

        // Context menu action handlers
        function handleContextEdit(task) {
            openTask(task.id);
        }

        function handleContextCopyLink(task) {
            const url = props.taskUrlTemplate.replace('__TASK_ID__', task.id);
            const fullUrl = window.location.origin + url;
            navigator.clipboard.writeText(fullUrl).then(() => {
                if (window.Toastr) {
                    window.Toastr.success('Link copied to clipboard');
                }
            }).catch(() => {
                if (window.Toastr) {
                    window.Toastr.error('Failed to copy link');
                }
            });
        }

        async function handleContextSetStatus(taskList, status) {
            if (!props.statusUrlTemplate) return;

            const statusOpt = computedStatusOptions.value.find(s => s.value === status);

            for (const task of taskList) {
                try {
                    const url = props.statusUrlTemplate.replace('__TASK_ID__', task.id);
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ status })
                    });

                    if (response.ok) {
                        // Update local state
                        const taskIndex = tasks.value.findIndex(t => t.id === task.id);
                        if (taskIndex !== -1) {
                            tasks.value[taskIndex].status = {
                                value: status,
                                label: statusOpt?.label || status,
                                color: statusOpt?.color
                            };
                        }

                        // Dispatch event for other components
                        document.dispatchEvent(new CustomEvent('task-updated', {
                            detail: {
                                taskId: task.id,
                                field: 'status',
                                value: status,
                                label: statusOpt?.label || status,
                                color: statusOpt?.color
                            }
                        }));
                    }
                } catch (error) {
                    console.error('Failed to update status:', error);
                }
            }

            if (window.Toastr) {
                window.Toastr.success('Status updated');
            }
            nextTick(() => initGantt());
        }

        async function handleContextSetPriority(taskList, priority) {
            if (!props.priorityUrlTemplate) return;

            const priorityOpt = priorityConfig.find(p => p.value === priority);

            for (const task of taskList) {
                try {
                    const url = props.priorityUrlTemplate.replace('__TASK_ID__', task.id);
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ priority })
                    });

                    if (response.ok) {
                        // Update local state
                        const taskIndex = tasks.value.findIndex(t => t.id === task.id);
                        if (taskIndex !== -1) {
                            tasks.value[taskIndex].priority = {
                                value: priority,
                                label: priorityOpt?.label || priority
                            };
                        }

                        // Dispatch event for other components
                        document.dispatchEvent(new CustomEvent('task-updated', {
                            detail: {
                                taskId: task.id,
                                field: 'priority',
                                value: priority,
                                label: priorityOpt?.label || priority
                            }
                        }));
                    }
                } catch (error) {
                    console.error('Failed to update priority:', error);
                }
            }

            if (window.Toastr) {
                window.Toastr.success('Priority updated');
            }
            nextTick(() => initGantt());
        }

        // Quick add handlers - these open the task panel for creation since the inline UI is now in the native sidebar
        function handleContextAddSubtask(task) {
            if (!props.subtaskUrlTemplate) {
                console.warn('No subtask URL template configured');
                return;
            }
            // Open task panel or redirect to create subtask
            if (window.Toastr) {
                window.Toastr.info('Use the task panel to add subtasks');
            }
            openTask(task.id);
        }

        function handleContextAddAbove(task) {
            if (!props.createUrl) {
                console.warn('No create URL configured');
                return;
            }
            // For now, just open the task to view context
            if (window.Toastr) {
                window.Toastr.info('Use the task list view to add tasks above');
            }
        }

        function handleContextAddBelow(task) {
            if (!props.createUrl) {
                console.warn('No create URL configured');
                return;
            }
            // For now, just open the task to view context
            if (window.Toastr) {
                window.Toastr.info('Use the task list view to add tasks below');
            }
        }

        // Check if task can have subtasks (depth limit)
        function canAddSubtaskTo(task) {
            const maxDepth = 3;
            return (task.depth || 0) < maxDepth;
        }

        // Delete task handler
        async function handleContextDelete(contextTasks) {
            if (!props.bulkDeleteUrl) {
                console.warn('No delete URL configured');
                return;
            }

            const count = contextTasks.length;
            const title = count === 1 ? 'Delete Task' : `Delete ${count} Tasks`;
            const message = count === 1
                ? `Are you sure you want to delete "${contextTasks[0].title}"? This action cannot be undone.`
                : `Are you sure you want to delete ${count} tasks? This action cannot be undone.`;

            const confirmed = await confirmDialogRef.value?.show({
                title,
                message,
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger'
            });

            if (!confirmed) return;

            isDeleting.value = true;

            try {
                const taskIds = contextTasks.map(t => t.id);
                const response = await fetch(props.bulkDeleteUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ taskIds })
                });

                if (!response.ok) {
                    throw new Error('Failed to delete task(s)');
                }

                // Remove deleted tasks from local state
                tasks.value = tasks.value.filter(t => !taskIds.includes(t.id));

                if (window.Toastr) {
                    window.Toastr.success(count === 1 ? 'Task deleted' : `${count} tasks deleted`);
                }

                // Dispatch event for other components
                document.dispatchEvent(new CustomEvent('task-deleted', {
                    detail: { taskIds }
                }));

                nextTick(() => initGantt());
            } catch (error) {
                console.error('Failed to delete task(s):', error);
                if (window.Toastr) {
                    window.Toastr.error('Failed to delete task(s)');
                }
            } finally {
                isDeleting.value = false;
            }
        }

        onMounted(() => {
            loadViewPreference();
            loadTaskListWidth();
            loadGroupPreference();

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
            window.addEventListener('resize', handleWindowResize);
        });

        onUnmounted(() => {
            document.removeEventListener('task-updated', handleTaskUpdate);
            window.removeEventListener('resize', handleWindowResize);
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
            visibleGanttTasks,
            unscheduledTasks,
            changeViewMode,
            scrollToToday,
            isUpdating,
            openTask,
            // Milestone grouping (still needed for toolbar toggle)
            groupBy,
            toggleGroupBy,
            // Context menu
            contextMenu,
            hideContextMenu,
            handleTaskContextMenu,
            handleContextEdit,
            handleContextCopyLink,
            handleContextSetStatus,
            handleContextSetPriority,
            handleContextAddSubtask,
            handleContextAddAbove,
            handleContextAddBelow,
            canAddSubtaskTo,
            // Delete
            confirmDialogRef,
            isDeleting,
            handleContextDelete,
            statusOptions: computedStatusOptions,
            priorityOptions
        };
    },

    template: `
        <div class="gantt-view-container">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4 bg-white rounded-lg shadow-sm p-2 sm:p-3">
                <div class="flex items-center gap-2">
                    <!-- View Mode Buttons -->
                    <div class="inline-flex rounded-lg bg-gray-100 p-1">
                        <button
                            v-for="mode in viewModes"
                            :key="mode"
                            @click="changeViewMode(mode)"
                            :class="[
                                'px-2 sm:px-3 py-1 sm:py-1.5 text-xs sm:text-sm font-medium rounded-md transition-colors',
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
                    <!-- Group by Milestone Toggle -->
                    <button
                        @click="toggleGroupBy"
                        :class="[
                            'inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 text-xs sm:text-sm font-medium rounded-md border transition-colors',
                            groupBy === 'milestone'
                                ? 'bg-indigo-50 text-indigo-700 border-indigo-300 hover:bg-indigo-100'
                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                        ]"
                        title="Group by milestone"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0l-3.75-3.75M17.25 21l3.75-3.75" />
                        </svg>
                        <span class="hidden sm:inline">Milestones</span>
                    </button>

                    <!-- Today Button -->
                    <button
                        @click="scrollToToday"
                        class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        Today
                    </button>

                    <!-- Task Count - hidden on mobile -->
                    <span class="hidden sm:inline text-sm text-gray-500">
                        <template v-if="visibleGanttTasks.length < ganttTasks.length">
                            {{ visibleGanttTasks.length }} of {{ ganttTasks.length }} tasks shown
                        </template>
                        <template v-else>
                            {{ ganttTasks.length }} task{{ ganttTasks.length !== 1 ? 's' : '' }} scheduled
                        </template>
                    </span>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex gap-0">
                <!-- Gantt Chart with native sidebar -->
                <div class="flex-1 bg-white shadow-sm overflow-hidden rounded-lg">
                    <div v-if="ganttTasks.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-500">
                        <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                        </svg>
                        <p class="text-lg font-medium mb-1">No scheduled tasks</p>
                        <p class="text-sm">Add start and due dates to tasks to see them in the Gantt chart</p>
                    </div>
                    <div ref="ganttContainer" class="gantt-chart-wrapper"></div>
                </div>

                <!-- Unscheduled Tasks Sidebar - large screens only -->
                <div v-if="unscheduledTasks.length > 0" class="hidden lg:block w-64 flex-shrink-0 ml-4">
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
                                @click="openTask(task.id)"
                            >
                                <div class="text-sm font-medium text-gray-900 truncate">{{ task.title }}</div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                        :style="{
                                            backgroundColor: (task.status?.color || '#6b7280') + '20',
                                            color: task.status?.color || '#6b7280'
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

            <!-- Unscheduled Tasks - below Gantt on mobile/tablet -->
            <div v-if="unscheduledTasks.length > 0" class="lg:hidden mt-4">
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="font-medium text-gray-900 mb-3 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Unscheduled ({{ unscheduledTasks.length }})
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                        <div
                            v-for="task in unscheduledTasks"
                            :key="task.id"
                            class="p-2 rounded border border-gray-200 hover:border-primary-300 hover:bg-primary-50 cursor-pointer transition-colors"
                            @click="openTask(task.id)"
                        >
                            <div class="text-sm font-medium text-gray-900 truncate">{{ task.title }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                    :style="{
                                        backgroundColor: (task.status?.color || '#6b7280') + '20',
                                        color: task.status?.color || '#6b7280'
                                    }"
                                >
                                    {{ task.status?.label || 'To Do' }}
                                </span>
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

            <!-- Context Menu -->
            <ContextMenu
                :visible="contextMenu.visible"
                :x="contextMenu.x"
                :y="contextMenu.y"
                :tasks="contextMenu.tasks"
                :status-options="statusOptions"
                :priority-options="priorityOptions"
                :milestone-options="[]"
                :members="[]"
                :can-edit="$props.canEdit"
                :can-add-subtask="contextMenu.tasks.length === 1 && $props.subtaskUrlTemplate && canAddSubtaskTo(contextMenu.tasks[0])"
                :can-duplicate="false"
                :can-promote="false"
                :can-demote="false"
                :can-set-due-date="false"
                @close="hideContextMenu"
                @edit="handleContextEdit"
                @copy-link="handleContextCopyLink"
                @set-status="handleContextSetStatus"
                @set-priority="handleContextSetPriority"
                @add-subtask="handleContextAddSubtask"
                @add-above="handleContextAddAbove"
                @add-below="handleContextAddBelow"
                @delete="handleContextDelete"
            />

            <!-- Confirm Dialog -->
            <ConfirmDialog ref="confirmDialogRef" />
        </div>
    `
};
