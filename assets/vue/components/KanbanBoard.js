import { ref, computed, onMounted, onUnmounted } from 'vue';
import TaskCard from './TaskCard.js';
import QuickAddCard from './QuickAddCard.js';

export default {
    name: 'KanbanBoard',

    components: { TaskCard, QuickAddCard },

    props: {
        initialTasks: {
            type: Array,
            default: () => []
        },
        milestones: {
            type: Array,
            default: () => []
        },
        modeStorageKey: {
            type: String,
            default: 'kanban_mode'
        },
        basePath: {
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
        milestoneUrlTemplate: {
            type: String,
            default: ''
        },
        reorderUrl: {
            type: String,
            default: ''
        },
        projectId: {
            type: String,
            default: ''
        },
        createUrl: {
            type: String,
            default: ''
        },
        membersUrl: {
            type: String,
            default: ''
        },
        assignUrlTemplate: {
            type: String,
            default: ''
        },
        subtaskUrlTemplate: {
            type: String,
            default: ''
        }
    },

    setup(props) {
        const tasks = ref(Array.isArray(props.initialTasks) ? [...props.initialTasks] : []);
        const currentMode = ref('status');
        const collapsedKeys = ref(new Set());
        const draggedTask = ref(null);
        const dragOverColumn = ref(null);
        const dropIndex = ref(null);
        const isUpdating = ref(false);
        const loadingTaskIds = ref(new Set());

        const basePath = props.basePath || window.BASE_PATH || '';

        // Column configurations
        const statusColumns = [
            { value: 'todo', label: 'To Do', badgeClass: 'kb-badge-gray', bgClass: 'kb-bg-gray' },
            { value: 'in_progress', label: 'In Progress', badgeClass: 'kb-badge-blue', bgClass: 'kb-bg-blue' },
            { value: 'in_review', label: 'In Review', badgeClass: 'kb-badge-yellow', bgClass: 'kb-bg-yellow' },
            { value: 'completed', label: 'Completed', badgeClass: 'kb-badge-green', bgClass: 'kb-bg-green' }
        ];

        const priorityColumns = [
            { value: 'none', label: 'None', badgeClass: 'kb-badge-gray', bgClass: 'kb-bg-gray' },
            { value: 'low', label: 'Low', badgeClass: 'kb-badge-blue', bgClass: 'kb-bg-blue' },
            { value: 'medium', label: 'Medium', badgeClass: 'kb-badge-yellow', bgClass: 'kb-bg-yellow' },
            { value: 'high', label: 'High', badgeClass: 'kb-badge-red', bgClass: 'kb-bg-red' }
        ];

        const milestoneColorCycle = ['indigo', 'purple', 'pink', 'teal', 'orange', 'cyan'];

        const columns = computed(() => {
            if (currentMode.value === 'status') return statusColumns;
            if (currentMode.value === 'priority') return priorityColumns;
            if (currentMode.value === 'milestone') {
                return props.milestones.map((m, i) => {
                    const color = milestoneColorCycle[i % milestoneColorCycle.length];
                    return {
                        value: m.id,
                        label: m.name,
                        dueDate: m.dueDate,
                        badgeClass: `kb-badge-${color}`,
                        bgClass: `kb-bg-${color} kb-milestone-col`
                    };
                });
            }
            return statusColumns;
        });

        const tasksByColumn = computed(() => {
            const grouped = {};
            columns.value.forEach(col => { grouped[col.value] = []; });

            tasks.value.forEach(task => {
                let colVal;
                if (currentMode.value === 'status') {
                    colVal = task.status?.value || task.status || 'todo';
                } else if (currentMode.value === 'priority') {
                    colVal = task.priority?.value || task.priority || 'none';
                } else if (currentMode.value === 'milestone') {
                    colVal = task.milestoneId || task.milestone?.id;
                }
                if (grouped[colVal]) grouped[colVal].push(task);
            });

            Object.keys(grouped).forEach(key => {
                grouped[key].sort((a, b) => (a.position || 0) - (b.position || 0));
            });

            return grouped;
        });

        const hasMilestones = computed(() => props.milestones.length > 0);

        // Collapse state
        const COLLAPSE_KEY = 'kanban_collapsed_columns';

        const isCollapsed = (colValue) => {
            return collapsedKeys.value.has(`${currentMode.value}:${colValue}`);
        };

        const toggleCollapse = (colValue) => {
            const key = `${currentMode.value}:${colValue}`;
            const next = new Set(collapsedKeys.value);
            if (next.has(key)) { next.delete(key); } else { next.add(key); }
            collapsedKeys.value = next;
            try { localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...next])); } catch (e) {}
        };

        const loadCollapseState = () => {
            try {
                const saved = localStorage.getItem(COLLAPSE_KEY);
                if (saved) collapsedKeys.value = new Set(JSON.parse(saved));
            } catch (e) {}
        };

        // Mode
        const setMode = (mode) => {
            currentMode.value = mode;
            try { localStorage.setItem(props.modeStorageKey, mode); } catch (e) {}
            // Notify external switcher
            document.dispatchEvent(new CustomEvent('kanban-mode-changed', { detail: { mode } }));
        };

        const loadMode = () => {
            try {
                const saved = localStorage.getItem(props.modeStorageKey);
                if (saved && ['status', 'priority', 'milestone'].includes(saved)) {
                    currentMode.value = saved;
                }
            } catch (e) {}
        };

        // Drag & Drop
        const handleDragStart = ({ event, task }) => {
            draggedTask.value = task;
        };

        const handleDragEnd = () => {
            draggedTask.value = null;
            dragOverColumn.value = null;
            dropIndex.value = null;
        };

        const handleColumnDragOver = (event, colValue) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            dragOverColumn.value = colValue;

            const dropzone = event.currentTarget.querySelector('.kanban-dropzone');
            if (!dropzone) return;

            const taskCards = dropzone.querySelectorAll('.task-card');
            const mouseY = event.clientY;
            let newIndex = taskCards.length;
            for (let i = 0; i < taskCards.length; i++) {
                const rect = taskCards[i].getBoundingClientRect();
                if (mouseY < rect.top + rect.height / 2) { newIndex = i; break; }
            }
            dropIndex.value = newIndex;
        };

        const handleColumnDragLeave = (event) => {
            if (!event.currentTarget.contains(event.relatedTarget)) {
                dragOverColumn.value = null;
                dropIndex.value = null;
            }
        };

        const getCurrentValue = (task) => {
            if (currentMode.value === 'status') return task.status?.value || task.status || 'todo';
            if (currentMode.value === 'priority') return task.priority?.value || task.priority || 'none';
            if (currentMode.value === 'milestone') return task.milestoneId || task.milestone?.id;
            return null;
        };

        const updateTaskValue = (task, newValue) => {
            const idx = tasks.value.findIndex(t => t.id === task.id);
            if (idx === -1) return;
            const col = columns.value.find(c => c.value === newValue);
            const label = col?.label || newValue;

            if (currentMode.value === 'status') {
                tasks.value[idx].status = { value: newValue, label };
            } else if (currentMode.value === 'priority') {
                tasks.value[idx].priority = { value: newValue, label };
            } else if (currentMode.value === 'milestone') {
                tasks.value[idx].milestoneId = newValue;
                tasks.value[idx].milestone = { id: newValue, name: label };
            }
        };

        const reorderInColumn = (task, colValue, targetIdx) => {
            const all = tasksByColumn.value[colValue] || [];
            const currentIdx = all.findIndex(t => t.id === task.id);
            const without = all.filter(t => t.id !== task.id);
            let insertAt = targetIdx != null ? targetIdx : without.length;
            if (currentIdx !== -1 && insertAt > currentIdx) insertAt = Math.max(0, insertAt - 1);
            insertAt = Math.min(insertAt, without.length);
            without.splice(insertAt, 0, task);
            without.forEach((t, i) => {
                const ti = tasks.value.findIndex(tt => tt.id === t.id);
                if (ti !== -1) tasks.value[ti].position = i;
            });
        };

        const getUpdateUrl = (taskId) => {
            let tmpl = '';
            if (currentMode.value === 'status') tmpl = props.statusUrlTemplate;
            else if (currentMode.value === 'priority') tmpl = props.priorityUrlTemplate;
            else if (currentMode.value === 'milestone') tmpl = props.milestoneUrlTemplate;
            return tmpl.replace('__TASK_ID__', taskId);
        };

        const getFieldName = () => {
            if (currentMode.value === 'status') return 'status';
            if (currentMode.value === 'priority') return 'priority';
            if (currentMode.value === 'milestone') return 'milestone';
            return '';
        };

        const persistColumnOrder = async (colValue) => {
            const colTasks = tasksByColumn.value[colValue] || [];
            const taskIds = colTasks.map(t => t.id);
            if (!taskIds.length || !props.reorderUrl) return;
            try {
                await fetch(props.reorderUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ taskIds })
                });
            } catch (e) {
                console.error('Error saving order:', e);
            }
        };

        const handleColumnDrop = async (event, newValue) => {
            event.preventDefault();
            if (!draggedTask.value || isUpdating.value) return;

            const task = draggedTask.value;
            const oldValue = getCurrentValue(task);
            const targetIdx = dropIndex.value;

            dragOverColumn.value = null;
            dropIndex.value = null;

            if (oldValue === newValue) {
                reorderInColumn(task, newValue, targetIdx);
                draggedTask.value = null;
                const next = new Set(loadingTaskIds.value);
                next.add(task.id);
                loadingTaskIds.value = next;
                try {
                    await persistColumnOrder(newValue);
                } finally {
                    const after = new Set(loadingTaskIds.value);
                    after.delete(task.id);
                    loadingTaskIds.value = after;
                }
                return;
            }

            // Optimistic update
            updateTaskValue(task, newValue);
            reorderInColumn(task, newValue, targetIdx);
            isUpdating.value = true;
            const nextLoading = new Set(loadingTaskIds.value);
            nextLoading.add(task.id);
            loadingTaskIds.value = nextLoading;

            try {
                const url = getUpdateUrl(task.id);
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ [getFieldName()]: newValue })
                });
                if (!resp.ok) throw new Error('Failed');
                await persistColumnOrder(newValue);
                const colLabel = columns.value.find(c => c.value === newValue)?.label || newValue;
                if (typeof Toastr !== 'undefined') Toastr.success('Task Updated', `"${task.title}" moved to ${colLabel}`);
            } catch (err) {
                console.error('Error updating task:', err);
                updateTaskValue(task, oldValue);
                if (typeof Toastr !== 'undefined') Toastr.error('Update Failed', 'Could not update task.');
            } finally {
                isUpdating.value = false;
                const afterLoading = new Set(loadingTaskIds.value);
                afterLoading.delete(task.id);
                loadingTaskIds.value = afterLoading;
                draggedTask.value = null;
            }
        };

        const handleTaskClick = (task) => {
            if (typeof window.openTaskPanel === 'function') window.openTaskPanel(task.id);
        };

        // Live updates
        const handleTaskUpdate = (e) => {
            const { taskId, field, value, label, milestoneId } = e.detail || {};
            const idx = tasks.value.findIndex(t => t.id == taskId);
            if (idx === -1) return;
            const task = tasks.value[idx];

            if (field === 'status') {
                task.status = { value, label: label || value };
            } else if (field === 'priority') {
                task.priority = { value, label: label || value };
            } else if (field === 'milestone') {
                task.milestoneId = milestoneId || value;
                task.milestone = { id: milestoneId || value, name: label || value };
            } else if (field === 'title') {
                task.title = value;
            } else if (field === 'dueDate') {
                task.dueDate = value;
            }
        };

        const handleAssigneesUpdate = (e) => {
            const { taskId, assignees } = e.detail || {};
            const idx = tasks.value.findIndex(t => t.id == taskId);
            if (idx === -1) return;
            tasks.value[idx].assignees = assignees || [];
        };

        const handleExternalModeChange = (e) => {
            const mode = e.detail?.mode;
            if (mode && ['status', 'priority', 'milestone'].includes(mode)) {
                currentMode.value = mode;
                try { localStorage.setItem(props.modeStorageKey, mode); } catch (ex) {}
            }
        };

        onMounted(() => {
            loadMode();
            loadCollapseState();
            document.addEventListener('task-updated', handleTaskUpdate);
            document.addEventListener('task-assignees-updated', handleAssigneesUpdate);
            document.addEventListener('kanban-set-mode', handleExternalModeChange);
            // Notify external switcher of initial mode
            document.dispatchEvent(new CustomEvent('kanban-mode-changed', { detail: { mode: currentMode.value, hasMilestones: hasMilestones.value } }));
        });

        onUnmounted(() => {
            document.removeEventListener('task-updated', handleTaskUpdate);
            document.removeEventListener('task-assignees-updated', handleAssigneesUpdate);
            document.removeEventListener('kanban-set-mode', handleExternalModeChange);
        });

        const isTaskLoading = (taskId) => loadingTaskIds.value.has(taskId);

        // Quick add state
        const quickAddColumn = ref(null);
        const quickAddAfterTask = ref(null);

        const openColumnQuickAdd = (colValue) => {
            quickAddAfterTask.value = null;
            quickAddColumn.value = quickAddColumn.value === colValue ? null : colValue;
        };

        const openSubtaskQuickAdd = (task) => {
            quickAddColumn.value = null;
            quickAddAfterTask.value = quickAddAfterTask.value === task.id ? null : task.id;
        };

        const closeQuickAdd = () => {
            quickAddColumn.value = null;
            quickAddAfterTask.value = null;
        };

        const handleColumnTaskCreated = (taskData, colValue) => {
            if (taskData) {
                // Ensure defaults for kanban card display
                taskData.assignees = taskData.assignees || [];
                taskData.tags = taskData.tags || [];
                taskData.commentCount = taskData.commentCount || 0;
                taskData.checklistCount = taskData.checklistCount || 0;
                taskData.completedChecklistCount = taskData.completedChecklistCount || 0;
                taskData.subtaskCount = taskData.subtaskCount || 0;
                taskData.completedSubtaskCount = taskData.completedSubtaskCount || 0;
                taskData.depth = 0;
                tasks.value.push(taskData);
            }
        };

        const handleSubtaskCreated = (taskData, parentTask) => {
            if (taskData) {
                taskData.assignees = taskData.assignees || [];
                taskData.tags = taskData.tags || [];
                taskData.commentCount = taskData.commentCount || 0;
                taskData.checklistCount = taskData.checklistCount || 0;
                taskData.completedChecklistCount = taskData.completedChecklistCount || 0;
                taskData.subtaskCount = taskData.subtaskCount || 0;
                taskData.completedSubtaskCount = taskData.completedSubtaskCount || 0;
                taskData.parentId = parentTask.id;
                taskData.parentChain = parentTask.title;
                taskData.depth = (parentTask.depth || 0) + 1;
                // Inherit milestone from parent
                if (!taskData.milestoneId) {
                    taskData.milestoneId = parentTask.milestoneId;
                }
                tasks.value.push(taskData);
                // Increment parent subtask count
                const pi = tasks.value.findIndex(t => t.id === parentTask.id);
                if (pi !== -1) {
                    tasks.value[pi].subtaskCount = (tasks.value[pi].subtaskCount || 0) + 1;
                }
            }
        };

        const getParentTaskForQuickAdd = computed(() => {
            if (!quickAddAfterTask.value) return null;
            return tasks.value.find(t => t.id === quickAddAfterTask.value) || null;
        });

        return {
            tasks, currentMode, columns, tasksByColumn, hasMilestones,
            draggedTask, dragOverColumn, dropIndex, isUpdating,
            isCollapsed, toggleCollapse, setMode,
            handleDragStart, handleDragEnd, handleColumnDragOver,
            handleColumnDragLeave, handleColumnDrop, handleTaskClick,
            isTaskLoading, basePath,
            quickAddColumn, quickAddAfterTask,
            openColumnQuickAdd, openSubtaskQuickAdd, closeQuickAdd,
            handleColumnTaskCreated, handleSubtaskCreated, getParentTaskForQuickAdd
        };
    },

    template: `
        <div class="kanban-board-vue">
            <!-- Kanban Grid -->
            <div class="kanban-grid">
                <div
                    v-for="col in columns"
                    :key="col.value"
                    class="kanban-col rounded-lg"
                    :class="[col.bgClass, { collapsed: isCollapsed(col.value) }]"
                    :data-column-key="col.value"
                    @dragover="handleColumnDragOver($event, col.value)"
                    @dragleave="handleColumnDragLeave($event)"
                    @drop="handleColumnDrop($event, col.value)"
                >
                    <!-- Toggle button -->
                    <button type="button" class="kanban-toggle-btn" title="Toggle column"
                        @click.stop="toggleCollapse(col.value)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h12M10 18l-6-6 6-6"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 5v14"/>
                        </svg>
                    </button>

                    <!-- Badge -->
                    <div class="kanban-badge-wrap">
                        <span class="kanban-badge" :class="col.badgeClass">
                            {{ col.label }} ({{ tasksByColumn[col.value]?.length || 0 }})
                        </span>
                        <button
                            v-if="createUrl"
                            type="button"
                            class="kanban-col-add-btn ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/60 hover:bg-white text-gray-500 hover:text-primary-600 transition-colors"
                            title="Quick add task"
                            @click.stop="openColumnQuickAdd(col.value)"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="kanban-body">
                        <div class="kanban-dropzone">
                            <!-- Column quick-add card at top -->
                            <QuickAddCard
                                v-if="quickAddColumn === col.value"
                                :project-id="projectId"
                                :milestones="milestones"
                                :column-value="col.value"
                                :column-mode="currentMode"
                                :base-path="basePath"
                                :members-url="membersUrl"
                                :create-url="createUrl"
                                :assign-url-template="assignUrlTemplate"
                                :subtask-url-template="subtaskUrlTemplate"
                                @task-created="(data) => handleColumnTaskCreated(data, col.value)"
                                @cancel="closeQuickAdd"
                            />

                            <template v-for="(task, index) in tasksByColumn[col.value]" :key="task.id">
                                <div v-if="dragOverColumn === col.value && draggedTask && dropIndex === index"
                                    class="drop-placeholder"><span>Drop here</span></div>
                                <TaskCard
                                    :task="task"
                                    :draggable="true"
                                    :base-path="basePath"
                                    :loading="isTaskLoading(task.id)"
                                    :can-add-subtask="(task.depth || 0) < 2"
                                    @click="handleTaskClick(task)"
                                    @dragstart="handleDragStart"
                                    @dragend="handleDragEnd"
                                    @add-subtask="openSubtaskQuickAdd"
                                />
                                <!-- Subtask quick-add card after this task -->
                                <QuickAddCard
                                    v-if="quickAddAfterTask === task.id"
                                    :project-id="projectId"
                                    :milestones="milestones"
                                    :column-value="col.value"
                                    :column-mode="currentMode"
                                    :base-path="basePath"
                                    :members-url="membersUrl"
                                    :create-url="createUrl"
                                    :assign-url-template="assignUrlTemplate"
                                    :subtask-url-template="subtaskUrlTemplate"
                                    :parent-task="task"
                                    @task-created="(data) => handleSubtaskCreated(data, task)"
                                    @cancel="closeQuickAdd"
                                />
                            </template>
                            <div v-if="dragOverColumn === col.value && draggedTask && dropIndex >= (tasksByColumn[col.value]?.length || 0)"
                                class="drop-placeholder"><span>Drop here</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="tasks.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No tasks yet</h3>
                <p class="mt-1 text-sm text-gray-500">Tasks will appear here once created.</p>
            </div>
        </div>
    `
};
