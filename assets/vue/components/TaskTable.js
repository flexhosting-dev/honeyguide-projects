import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import TableHeader from './TaskTable/TableHeader.js';
import TaskRow from './TaskTable/TaskRow.js';
import ColumnConfig from './TaskTable/ColumnConfig.js';
import GroupRow from './TaskTable/GroupRow.js';
import BulkActionBar from './TaskTable/BulkActionBar.js';
import QuickAddRow from './TaskTable/QuickAddRow.js';
import ContextMenu from './TaskTable/ContextMenu.js';
import ConfirmDialog from './ConfirmDialog.js';

export default {
    name: 'TaskTable',

    components: { TableHeader, TaskRow, ColumnConfig, GroupRow, BulkActionBar, QuickAddRow, ContextMenu, ConfirmDialog },

    props: {
        initialTasks: {
            type: Array,
            default: () => []
        },
        milestones: {
            type: Array,
            default: () => []
        },
        members: {
            type: Array,
            default: () => []
        },
        basePath: {
            type: String,
            default: ''
        },
        canEdit: {
            type: Boolean,
            default: false
        },
        statusUrlTemplate: {
            type: String,
            default: ''
        },
        priorityUrlTemplate: {
            type: String,
            default: ''
        },
        titleUrlTemplate: {
            type: String,
            default: ''
        },
        dueDateUrlTemplate: {
            type: String,
            default: ''
        },
        milestoneUrlTemplate: {
            type: String,
            default: ''
        },
        createUrl: {
            type: String,
            default: ''
        },
        projectId: {
            type: String,
            default: ''
        },
        storageKey: {
            type: String,
            default: 'task_table'
        },
        childrenUrlTemplate: {
            type: String,
            default: ''
        },
        bulkUpdateUrl: {
            type: String,
            default: ''
        },
        bulkDeleteUrl: {
            type: String,
            default: ''
        },
        assigneesUrlTemplate: {
            type: String,
            default: ''
        },
        duplicateUrlTemplate: {
            type: String,
            default: ''
        },
        subtaskUrlTemplate: {
            type: String,
            default: ''
        }
    },

    setup(props) {
        // Core state
        const tasks = ref(Array.isArray(props.initialTasks) ? [...props.initialTasks] : []);
        const selectedIds = ref(new Set());
        const expandedIds = ref(new Set());
        const collapsedGroups = ref(new Set());

        // Sorting state
        const sortColumn = ref('position');
        const sortDirection = ref('asc');

        // Grouping state
        const groupBy = ref('none'); // 'none', 'status', 'priority', 'milestone', 'assignee', 'dueDate'

        // Search state
        const searchQuery = ref('');
        const searchInputRef = ref(null);
        let searchDebounceTimer = null;

        // Group options
        const groupOptions = [
            { value: 'none', label: 'No grouping' },
            { value: 'status', label: 'Status' },
            { value: 'priority', label: 'Priority' },
            { value: 'milestone', label: 'Milestone' },
            { value: 'assignee', label: 'Assignee' },
            { value: 'dueDate', label: 'Due Date' }
        ];

        // Status and priority configs
        const statusConfig = [
            { value: 'todo', label: 'To Do', color: '#6b7280' },
            { value: 'in_progress', label: 'In Progress', color: '#3b82f6' },
            { value: 'in_review', label: 'In Review', color: '#eab308' },
            { value: 'completed', label: 'Completed', color: '#22c55e' }
        ];

        const priorityConfig = [
            { value: 'high', label: 'High', color: '#ef4444' },
            { value: 'medium', label: 'Medium', color: '#eab308' },
            { value: 'low', label: 'Low', color: '#3b82f6' },
            { value: 'none', label: 'None', color: '#6b7280' }
        ];

        // Column configuration
        const defaultColumns = [
            { key: 'checkbox', label: '', width: 40, visible: true, sortable: false },
            { key: 'title', label: 'Task', width: 'flex', visible: true, sortable: true },
            { key: 'status', label: 'Status', width: 120, visible: true, sortable: true },
            { key: 'priority', label: 'Priority', width: 100, visible: true, sortable: true },
            { key: 'assignees', label: 'Assignee', width: 150, visible: true, sortable: false },
            { key: 'dueDate', label: 'Due Date', width: 120, visible: true, sortable: true },
            { key: 'tags', label: 'Tags', width: 150, visible: true, sortable: false },
            { key: 'milestone', label: 'Milestone', width: 150, visible: false, sortable: true },
            { key: 'startDate', label: 'Start Date', width: 120, visible: false, sortable: true },
            { key: 'subtasks', label: 'Subtasks', width: 80, visible: false, sortable: false }
        ];

        const columns = ref([...defaultColumns]);

        // Load/Save column config
        const loadColumnConfig = () => {
            try {
                const saved = localStorage.getItem(`${props.storageKey}_columns`);
                if (saved) {
                    const savedColumns = JSON.parse(saved);
                    const newColumns = [];
                    savedColumns.forEach(saved => {
                        const defaultCol = defaultColumns.find(d => d.key === saved.key);
                        if (defaultCol) {
                            newColumns.push({
                                ...defaultCol,
                                visible: saved.visible,
                                width: saved.width || defaultCol.width
                            });
                        }
                    });
                    defaultColumns.forEach(def => {
                        if (!newColumns.find(c => c.key === def.key)) {
                            newColumns.push({ ...def });
                        }
                    });
                    columns.value = newColumns;
                }
            } catch (e) {}
        };

        const saveColumnConfig = () => {
            try {
                const toSave = columns.value.map(c => ({
                    key: c.key,
                    visible: c.visible,
                    width: c.width
                }));
                localStorage.setItem(`${props.storageKey}_columns`, JSON.stringify(toSave));
            } catch (e) {}
        };

        const toggleColumnVisibility = (columnKey) => {
            if (columnKey === 'title') return;
            const col = columns.value.find(c => c.key === columnKey);
            if (col) {
                col.visible = !col.visible;
                saveColumnConfig();
            }
        };

        const reorderColumns = (fromIndex, toIndex) => {
            const titleIndex = columns.value.findIndex(c => c.key === 'title');
            if (fromIndex === titleIndex || toIndex === titleIndex) return;
            const newColumns = [...columns.value];
            const [moved] = newColumns.splice(fromIndex, 1);
            newColumns.splice(toIndex, 0, moved);
            columns.value = newColumns;
            saveColumnConfig();
        };

        const resetColumns = () => {
            columns.value = defaultColumns.map(c => ({ ...c }));
            saveColumnConfig();
        };

        // Load/Save group state
        const loadGroupState = () => {
            try {
                const savedGroup = localStorage.getItem(`${props.storageKey}_groupBy`);
                if (savedGroup) groupBy.value = savedGroup;

                const savedCollapsed = localStorage.getItem(`${props.storageKey}_collapsed_groups`);
                if (savedCollapsed) collapsedGroups.value = new Set(JSON.parse(savedCollapsed));
            } catch (e) {}
        };

        const saveGroupState = () => {
            try {
                localStorage.setItem(`${props.storageKey}_groupBy`, groupBy.value);
                localStorage.setItem(`${props.storageKey}_collapsed_groups`, JSON.stringify([...collapsedGroups.value]));
            } catch (e) {}
        };

        const setGroupBy = (value) => {
            try {
                groupBy.value = value;
                saveGroupState();
            } catch (e) {
                console.error('Error setting group:', e);
            }
        };

        const toggleGroupCollapse = (groupKey) => {
            if (collapsedGroups.value.has(groupKey)) {
                collapsedGroups.value.delete(groupKey);
            } else {
                collapsedGroups.value.add(groupKey);
            }
            collapsedGroups.value = new Set(collapsedGroups.value);
            saveGroupState();
        };

        // Load/Save expanded state
        const loadExpandedState = () => {
            try {
                const saved = localStorage.getItem(`${props.storageKey}_expanded`);
                if (saved) expandedIds.value = new Set(JSON.parse(saved));
            } catch (e) {}
        };

        const saveExpandedState = () => {
            try {
                localStorage.setItem(`${props.storageKey}_expanded`, JSON.stringify([...expandedIds.value]));
            } catch (e) {}
        };

        // Sorting
        const handleSort = (columnKey) => {
            if (sortColumn.value === columnKey) {
                if (sortDirection.value === 'asc') {
                    sortDirection.value = 'desc';
                } else if (sortDirection.value === 'desc') {
                    sortDirection.value = 'none';
                    sortColumn.value = 'position';
                } else {
                    sortDirection.value = 'asc';
                }
            } else {
                sortColumn.value = columnKey;
                sortDirection.value = 'asc';
            }
        };

        const getSortValue = (task, column) => {
            switch (column) {
                case 'title':
                    return (task.title || '').toLowerCase();
                case 'status':
                    const statusOrder = { 'todo': 0, 'in_progress': 1, 'in_review': 2, 'completed': 3 };
                    return statusOrder[task.status?.value || task.status || 'todo'] ?? 0;
                case 'priority':
                    const priorityOrder = { 'high': 0, 'medium': 1, 'low': 2, 'none': 3 };
                    return priorityOrder[task.priority?.value || task.priority || 'none'] ?? 3;
                case 'dueDate':
                    return task.dueDate ? new Date(task.dueDate).getTime() : Infinity;
                case 'startDate':
                    return task.startDate ? new Date(task.startDate).getTime() : Infinity;
                case 'milestone':
                    const milestone = props.milestones.find(m => m.id === task.milestoneId);
                    return (milestone?.name || '').toLowerCase();
                case 'position':
                default:
                    return task.position ?? 0;
            }
        };

        // Get group key for a task
        const getGroupKey = (task) => {
            switch (groupBy.value) {
                case 'status':
                    return task.status?.value || task.status || 'todo';
                case 'priority':
                    return task.priority?.value || task.priority || 'none';
                case 'milestone':
                    return task.milestoneId || '__no_milestone__';
                case 'assignee':
                    const firstAssignee = task.assignees?.[0];
                    return firstAssignee?.user?.id || firstAssignee?.id || '__unassigned__';
                case 'dueDate':
                    return getDueDateGroupKey(task.dueDate);
                default:
                    return '__all__';
            }
        };

        // Get due date group key
        const getDueDateGroupKey = (dueDate) => {
            if (!dueDate) return 'no_date';
            const date = new Date(dueDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const weekEnd = new Date(today);
            weekEnd.setDate(weekEnd.getDate() + 7);
            const nextWeekEnd = new Date(today);
            nextWeekEnd.setDate(nextWeekEnd.getDate() + 14);

            if (date < today) return 'overdue';
            if (date < tomorrow) return 'today';
            if (date < weekEnd) return 'this_week';
            if (date < nextWeekEnd) return 'next_week';
            return 'later';
        };

        // Get group info
        const getGroupInfo = (groupKey) => {
            switch (groupBy.value) {
                case 'status':
                    const status = statusConfig.find(s => s.value === groupKey);
                    return { label: status?.label || groupKey, color: status?.color };
                case 'priority':
                    const priority = priorityConfig.find(p => p.value === groupKey);
                    return { label: priority?.label || groupKey, color: priority?.color };
                case 'milestone':
                    if (groupKey === '__no_milestone__') return { label: 'No Milestone', color: '#6b7280' };
                    const milestone = props.milestones.find(m => m.id === groupKey);
                    return { label: milestone?.name || 'Unknown Milestone', color: '#6366f1' };
                case 'assignee':
                    if (groupKey === '__unassigned__') return { label: 'Unassigned', color: '#6b7280' };
                    const member = props.members.find(m => m.id === groupKey);
                    if (member) return { label: member.fullName, color: '#06b6d4' };
                    // Search in tasks
                    for (const task of tasks.value) {
                        const assignee = task.assignees?.find(a => (a.user?.id || a.id) === groupKey);
                        if (assignee) return { label: assignee.user?.fullName || assignee.fullName || 'Unknown', color: '#06b6d4' };
                    }
                    return { label: 'Unknown', color: '#6b7280' };
                case 'dueDate':
                    const dueDateLabels = {
                        'overdue': { label: 'Overdue', color: '#ef4444' },
                        'today': { label: 'Today', color: '#f97316' },
                        'this_week': { label: 'This Week', color: '#eab308' },
                        'next_week': { label: 'Next Week', color: '#22c55e' },
                        'later': { label: 'Later', color: '#3b82f6' },
                        'no_date': { label: 'No Due Date', color: '#6b7280' }
                    };
                    return dueDateLabels[groupKey] || { label: groupKey, color: '#6b7280' };
                default:
                    return { label: 'All Tasks', color: null };
            }
        };

        // Search filter helper
        const matchesSearch = (task, query) => {
            if (!query) return true;
            const q = query.toLowerCase();
            // Search in title
            if (task.title && task.title.toLowerCase().includes(q)) return true;
            // Search in description (if available)
            if (task.description && task.description.toLowerCase().includes(q)) return true;
            // Search in project name
            if (task.projectName && task.projectName.toLowerCase().includes(q)) return true;
            // Search in assignee names
            if (task.assignees) {
                for (const a of task.assignees) {
                    const name = a.user?.fullName || a.fullName || '';
                    if (name.toLowerCase().includes(q)) return true;
                }
            }
            // Search in tags
            if (task.tags) {
                for (const t of task.tags) {
                    if (t.name && t.name.toLowerCase().includes(q)) return true;
                }
            }
            return false;
        };

        // Get tasks matching search (including their ancestors for tree display)
        const getMatchingTaskIds = () => {
            if (!searchQuery.value.trim()) return null; // null means no filtering

            const matchingIds = new Set();
            const q = searchQuery.value.trim();

            // First pass: find directly matching tasks
            tasks.value.forEach(task => {
                if (matchesSearch(task, q)) {
                    matchingIds.add(task.id);
                }
            });

            // Second pass: add all ancestors of matching tasks
            const addAncestors = (taskId) => {
                const task = tasks.value.find(t => t.id === taskId);
                if (task && task.parentId) {
                    matchingIds.add(task.parentId);
                    addAncestors(task.parentId);
                }
            };

            [...matchingIds].forEach(id => addAncestors(id));

            return matchingIds;
        };

        // Computed: grouped and sorted tasks
        const displayItems = computed(() => {
            const matchingIds = getMatchingTaskIds();

            // Build child map for tree
            const childMap = {};
            tasks.value.forEach(t => {
                if (t.parentId) {
                    if (!childMap[t.parentId]) childMap[t.parentId] = [];
                    childMap[t.parentId].push(t);
                }
            });

            // Sort children
            Object.keys(childMap).forEach(parentId => {
                childMap[parentId].sort((a, b) => (a.position || 0) - (b.position || 0));
            });

            // Build task with children recursively (with search filtering)
            const addTaskWithChildren = (task, depth = 0) => {
                // Skip if not matching search
                if (matchingIds && !matchingIds.has(task.id)) return [];

                // Highlight matching text in title
                const displayTask = { ...task, displayDepth: depth };
                if (searchQuery.value.trim()) {
                    displayTask.searchHighlight = searchQuery.value.trim();
                }

                const items = [{ type: 'task', task: displayTask }];

                // Auto-expand to show matching children when searching
                const shouldExpand = matchingIds ? true : expandedIds.value.has(task.id);

                if (shouldExpand && childMap[task.id]) {
                    childMap[task.id].forEach(child => {
                        items.push(...addTaskWithChildren(child, depth + 1));
                    });
                }
                return items;
            };

            // Get root tasks (filtered by search)
            let rootTasks = tasks.value.filter(t => !t.parentId);
            if (matchingIds) {
                rootTasks = rootTasks.filter(t => matchingIds.has(t.id));
            }

            // Sort root tasks
            const sortedRoots = [...rootTasks].sort((a, b) => {
                const aVal = getSortValue(a, sortColumn.value);
                const bVal = getSortValue(b, sortColumn.value);
                let cmp = 0;
                if (typeof aVal === 'string' && typeof bVal === 'string') {
                    cmp = aVal.localeCompare(bVal);
                } else {
                    cmp = aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
                }
                return sortDirection.value === 'desc' ? -cmp : cmp;
            });

            // No grouping
            if (groupBy.value === 'none') {
                const result = [];
                sortedRoots.forEach(task => {
                    result.push(...addTaskWithChildren(task, 0));
                });
                return result;
            }

            // Group tasks
            const groups = {};
            const groupOrder = [];

            // Define group order based on type
            if (groupBy.value === 'status') {
                statusConfig.forEach(s => { groups[s.value] = []; groupOrder.push(s.value); });
            } else if (groupBy.value === 'priority') {
                priorityConfig.forEach(p => { groups[p.value] = []; groupOrder.push(p.value); });
            } else if (groupBy.value === 'dueDate') {
                ['overdue', 'today', 'this_week', 'next_week', 'later', 'no_date'].forEach(k => {
                    groups[k] = [];
                    groupOrder.push(k);
                });
            }

            // Group tasks
            sortedRoots.forEach(task => {
                const key = getGroupKey(task);
                if (!groups[key]) {
                    groups[key] = [];
                    groupOrder.push(key);
                }
                groups[key].push(task);
            });

            // Build result with group headers
            const result = [];
            groupOrder.forEach(key => {
                const groupTasks = groups[key];
                if (!groupTasks || groupTasks.length === 0) return;

                const info = getGroupInfo(key);
                const completedCount = groupTasks.filter(t =>
                    (t.status?.value || t.status) === 'completed'
                ).length;

                result.push({
                    type: 'group',
                    groupKey: key,
                    groupLabel: info.label,
                    groupColor: info.color,
                    taskCount: groupTasks.length,
                    completedCount,
                    isCollapsed: collapsedGroups.value.has(key)
                });

                if (!collapsedGroups.value.has(key)) {
                    groupTasks.forEach(task => {
                        result.push(...addTaskWithChildren(task, 0));
                    });
                }
            });

            return result;
        });

        // Computed: total task count (for display)
        const totalTaskCount = computed(() => {
            return displayItems.value.filter(item => item.type === 'task').length;
        });

        // Check if task has children
        const hasChildren = (taskId) => {
            return tasks.value.some(t => t.parentId === taskId);
        };

        // Selection helpers
        const allSelected = computed(() => {
            const taskItems = displayItems.value.filter(item => item.type === 'task');
            if (taskItems.length === 0) return false;
            return taskItems.every(item => selectedIds.value.has(item.task.id));
        });

        const someSelected = computed(() => {
            return selectedIds.value.size > 0 && !allSelected.value;
        });

        const handleSelectAll = (checked) => {
            if (checked) {
                displayItems.value
                    .filter(item => item.type === 'task')
                    .forEach(item => selectedIds.value.add(item.task.id));
            } else {
                selectedIds.value.clear();
            }
            selectedIds.value = new Set(selectedIds.value);
        };

        const handleSelect = (taskId, checked) => {
            if (checked) {
                selectedIds.value.add(taskId);
            } else {
                selectedIds.value.delete(taskId);
            }
            selectedIds.value = new Set(selectedIds.value);
        };

        // Lazy loading state for children
        const loadingChildrenIds = ref(new Set());
        const loadedChildrenIds = ref(new Set());

        // Toggle expanded state for tree with lazy loading
        const handleToggleExpand = async (taskId) => {
            try {
                if (expandedIds.value.has(taskId)) {
                    // Collapse
                    expandedIds.value.delete(taskId);
                    expandedIds.value = new Set(expandedIds.value);
                    saveExpandedState();
                    return;
                }

            // Expand - check if we need to load children
            const task = tasks.value.find(t => t.id === taskId);
            const hasSubtasks = task?.subtaskCount > 0;
            const childrenAlreadyLoaded = loadedChildrenIds.value.has(taskId) ||
                tasks.value.some(t => t.parentId === taskId);

            if (hasSubtasks && !childrenAlreadyLoaded && props.childrenUrlTemplate) {
                // Lazy load children
                loadingChildrenIds.value.add(taskId);
                loadingChildrenIds.value = new Set(loadingChildrenIds.value);

                try {
                    const url = props.childrenUrlTemplate.replace('__TASK_ID__', taskId);
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const data = await response.json();

                    if (data.success && data.children) {
                        // Add children to tasks array
                        data.children.forEach(child => {
                            // Check if already exists
                            if (!tasks.value.find(t => t.id === child.id)) {
                                tasks.value.push(child);
                            }
                        });
                        loadedChildrenIds.value.add(taskId);
                        loadedChildrenIds.value = new Set(loadedChildrenIds.value);
                    }
                } catch (error) {
                    console.error('Error loading children:', error);
                } finally {
                    loadingChildrenIds.value.delete(taskId);
                    loadingChildrenIds.value = new Set(loadingChildrenIds.value);
                }
            }

            // Expand
            expandedIds.value.add(taskId);
            expandedIds.value = new Set(expandedIds.value);
            saveExpandedState();
            } catch (e) {
                console.error('Error toggling expand:', e);
            }
        };

        // Check if children are loading for a task
        const isLoadingChildren = (taskId) => {
            return loadingChildrenIds.value.has(taskId);
        };

        // Expand all tasks with children
        const expandAll = () => {
            tasks.value.forEach(task => {
                if (hasChildren(task.id) || task.subtaskCount > 0) {
                    expandedIds.value.add(task.id);
                }
            });
            expandedIds.value = new Set(expandedIds.value);
            saveExpandedState();
        };

        // Collapse all
        const collapseAll = () => {
            expandedIds.value.clear();
            expandedIds.value = new Set();
            saveExpandedState();
        };

        // Row click
        const handleRowClick = (task) => {
            if (typeof window.openTaskPanel === 'function') {
                window.openTaskPanel(task.id);
            }
        };

        // Editing state
        const editingCell = ref(null); // { taskId, field }
        const isUpdating = ref(new Set());

        // Cell click - start inline editing or open panel
        const handleCellClick = (task, columnKey, event) => {
            // Editable fields: title, status, priority, dueDate, startDate, milestone, assignees
            const editableFields = ['title', 'status', 'priority', 'dueDate', 'startDate', 'milestone', 'assignees'];

            if (props.canEdit && editableFields.includes(columnKey)) {
                event.stopPropagation();
                editingCell.value = { taskId: task.id, field: columnKey };
            } else {
                // Non-editable cells or no edit permission - open panel
                if (typeof window.openTaskPanel === 'function') {
                    window.openTaskPanel(task.id);
                }
            }
        };

        // Cancel editing
        const cancelEditing = () => {
            editingCell.value = null;
        };

        // Save inline edit
        const saveInlineEdit = async (taskId, field, value) => {
            const task = tasks.value.find(t => t.id === taskId);
            if (!task) return;

            // Get the API URL template
            let urlTemplate = '';
            let payload = {};

            switch (field) {
                case 'title':
                    urlTemplate = props.titleUrlTemplate;
                    payload = { title: value };
                    break;
                case 'status':
                    urlTemplate = props.statusUrlTemplate;
                    payload = { status: value };
                    break;
                case 'priority':
                    urlTemplate = props.priorityUrlTemplate;
                    payload = { priority: value };
                    break;
                case 'dueDate':
                    urlTemplate = props.dueDateUrlTemplate;
                    payload = { dueDate: value || null };
                    break;
                case 'startDate':
                    // Assuming same URL pattern
                    urlTemplate = props.dueDateUrlTemplate.replace('due-date', 'start-date');
                    payload = { startDate: value || null };
                    break;
                case 'milestone':
                    urlTemplate = props.milestoneUrlTemplate;
                    payload = { milestone: value };
                    break;
                default:
                    return;
            }

            if (!urlTemplate) {
                console.warn('No URL template for field:', field);
                cancelEditing();
                return;
            }

            const url = urlTemplate.replace('__TASK_ID__', taskId);

            // Optimistic update
            const oldValue = getTaskFieldValue(task, field);
            updateTaskField(task, field, value);

            // Set updating state
            isUpdating.value.add(taskId);
            isUpdating.value = new Set(isUpdating.value);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to update');
                }

                // Dispatch event for other components
                const eventDetail = { taskId, field, value };
                if (field === 'status') {
                    eventDetail.label = data.statusLabel || value;
                } else if (field === 'priority') {
                    eventDetail.label = data.priorityLabel || value;
                } else if (field === 'milestone') {
                    eventDetail.label = data.milestoneName || value;
                    eventDetail.milestoneId = data.milestone || value;
                }
                document.dispatchEvent(new CustomEvent('task-updated', { detail: eventDetail }));

                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Task Updated', `${field} updated successfully`);
                }
            } catch (error) {
                console.error('Error updating task:', error);
                // Rollback
                updateTaskField(task, field, oldValue);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Update Failed', error.message || 'Could not update task');
                }
            } finally {
                isUpdating.value.delete(taskId);
                isUpdating.value = new Set(isUpdating.value);
                cancelEditing();
            }
        };

        // Helper to get field value from task
        const getTaskFieldValue = (task, field) => {
            switch (field) {
                case 'title': return task.title;
                case 'status': return task.status?.value || task.status;
                case 'priority': return task.priority?.value || task.priority;
                case 'dueDate': return task.dueDate;
                case 'startDate': return task.startDate;
                case 'milestone': return task.milestoneId;
                default: return null;
            }
        };

        // Helper to update field on task
        const updateTaskField = (task, field, value) => {
            switch (field) {
                case 'title':
                    task.title = value;
                    break;
                case 'status':
                    const statusOpt = statusConfig.find(s => s.value === value);
                    task.status = { value, label: statusOpt?.label || value };
                    break;
                case 'priority':
                    const priorityOpt = priorityConfig.find(p => p.value === value);
                    task.priority = { value, label: priorityOpt?.label || value };
                    break;
                case 'dueDate':
                    task.dueDate = value;
                    break;
                case 'startDate':
                    task.startDate = value;
                    break;
                case 'milestone':
                    task.milestoneId = value;
                    const milestone = props.milestones.find(m => m.id === value);
                    task.milestone = milestone ? { id: value, name: milestone.name } : null;
                    break;
            }
        };

        // Check if cell is being edited
        const isEditingCell = (taskId, field) => {
            return editingCell.value?.taskId === taskId && editingCell.value?.field === field;
        };

        // Check if task is updating
        const isTaskUpdating = (taskId) => {
            return isUpdating.value.has(taskId);
        };

        // Handle assignee add/remove
        const handleAssigneeChange = async (taskId, userId, action) => {
            if (!props.assigneesUrlTemplate) {
                console.warn('No assignees URL template configured');
                return;
            }

            const task = tasks.value.find(t => t.id === taskId);
            if (!task) return;

            const url = props.assigneesUrlTemplate.replace('__TASK_ID__', taskId);

            isUpdating.value.add(taskId);
            isUpdating.value = new Set(isUpdating.value);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ action, userId })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to update assignees');
                }

                // Update local task assignees
                task.assignees = data.assignees || [];

                // Dispatch event for other components
                document.dispatchEvent(new CustomEvent('task-assignees-updated', {
                    detail: { taskId, assignees: task.assignees }
                }));

                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Task Updated', action === 'add' ? 'Assignee added' : 'Assignee removed');
                }
            } catch (error) {
                console.error('Error updating assignees:', error);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Update Failed', error.message || 'Could not update assignees');
                }
            } finally {
                isUpdating.value.delete(taskId);
                isUpdating.value = new Set(isUpdating.value);
            }
        };

        // Quick add state
        const quickAddGroupKey = ref(null);
        const quickAddRowRef = ref(null);
        const isCreating = ref(false);

        // Compute default values based on current group
        const quickAddDefaults = computed(() => {
            const defaults = {
                status: 'todo',
                priority: 'none',
                milestone: props.milestones.length > 0 ? props.milestones[0].id : ''
            };

            if (groupBy.value === 'status' && quickAddGroupKey.value && quickAddGroupKey.value !== '__all__') {
                defaults.status = quickAddGroupKey.value;
            } else if (groupBy.value === 'priority' && quickAddGroupKey.value && quickAddGroupKey.value !== '__all__') {
                defaults.priority = quickAddGroupKey.value;
            } else if (groupBy.value === 'milestone' && quickAddGroupKey.value && quickAddGroupKey.value !== '__no_milestone__' && quickAddGroupKey.value !== '__all__') {
                defaults.milestone = quickAddGroupKey.value;
            }

            return defaults;
        });

        // Handle add task in group
        const handleAddTaskInGroup = async (groupKey) => {
            // Expand the group if it's collapsed
            if (collapsedGroups.value.has(groupKey)) {
                collapsedGroups.value.delete(groupKey);
                collapsedGroups.value = new Set(collapsedGroups.value);
                saveGroupState();
            }
            quickAddGroupKey.value = groupKey;
            await nextTick();
            if (quickAddRowRef.value && quickAddRowRef.value.focus) {
                quickAddRowRef.value.focus();
            }
        };

        // Cancel quick add
        const cancelQuickAdd = () => {
            quickAddGroupKey.value = null;
        };

        // Save quick add task (receives formData from QuickAddRow component)
        const saveQuickAdd = async (formData, continueAdding = false) => {
            const title = formData.title?.trim();
            if (!title || isCreating.value) return;

            if (!props.createUrl) {
                console.warn('No create URL configured');
                cancelQuickAdd();
                return;
            }

            // Need a milestone to create task
            let milestoneId = formData.milestone;
            if (!milestoneId && props.milestones.length > 0) {
                milestoneId = props.milestones[0].id;
            }

            if (!milestoneId) {
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Cannot Create Task', 'A milestone is required');
                }
                return;
            }

            isCreating.value = true;

            try {
                const response = await fetch(props.createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        title,
                        milestone: milestoneId,
                        status: formData.status || 'todo',
                        priority: formData.priority || 'none',
                        dueDate: formData.dueDate || null,
                        startDate: formData.startDate || null,
                        assignees: formData.assignees || []
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to create task');
                }

                // Add the new task to the list
                const newTask = data.task;
                newTask.assignees = newTask.assignees || [];
                newTask.tags = newTask.tags || [];
                tasks.value.push(newTask);

                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Task Created', `"${title}" created successfully`);
                }

                if (!continueAdding) {
                    cancelQuickAdd();
                }
            } catch (error) {
                console.error('Error creating task:', error);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Create Failed', error.message || 'Could not create task');
                }
            } finally {
                isCreating.value = false;
            }
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
            } else if (field === 'startDate') {
                task.startDate = value;
            }
        };

        const handleAssigneesUpdate = (e) => {
            const { taskId, assignees } = e.detail || {};
            const idx = tasks.value.findIndex(t => t.id == taskId);
            if (idx === -1) return;
            tasks.value[idx].assignees = assignees || [];
        };

        // Visible column count for group row colspan
        const visibleColumnCount = computed(() => {
            return columns.value.filter(c => c.visible).length;
        });

        // Bulk actions
        const isBulkUpdating = ref(false);

        const selectedTaskCount = computed(() => selectedIds.value.size);

        const clearSelection = () => {
            selectedIds.value.clear();
            selectedIds.value = new Set();
        };

        const handleBulkUpdate = async (updates) => {
            if (selectedIds.value.size === 0 || isBulkUpdating.value) return;
            if (!props.bulkUpdateUrl) {
                console.error('Bulk update URL not configured');
                return;
            }

            isBulkUpdating.value = true;

            try {
                const response = await fetch(props.bulkUpdateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        taskIds: [...selectedIds.value],
                        updates
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to update tasks');
                }

                // Update local task state
                selectedIds.value.forEach(taskId => {
                    const task = tasks.value.find(t => t.id === taskId);
                    if (task) {
                        if (updates.status) {
                            const statusOpt = statusConfig.find(s => s.value === updates.status);
                            task.status = { value: updates.status, label: statusOpt?.label || updates.status };
                        }
                        if (updates.priority) {
                            const priorityOpt = priorityConfig.find(p => p.value === updates.priority);
                            task.priority = { value: updates.priority, label: priorityOpt?.label || updates.priority };
                        }
                        if (updates.milestone) {
                            task.milestoneId = updates.milestone;
                            const milestone = props.milestones.find(m => m.id === updates.milestone);
                            task.milestone = milestone ? { id: updates.milestone, name: milestone.name } : null;
                        }
                    }
                });

                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Tasks Updated', `${data.updated} tasks updated`);
                }

                clearSelection();
            } catch (error) {
                console.error('Bulk update error:', error);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Update Failed', error.message || 'Could not update tasks');
                }
            } finally {
                isBulkUpdating.value = false;
            }
        };

        const handleBulkDelete = async () => {
            if (selectedIds.value.size === 0 || isBulkUpdating.value) return;
            if (!props.bulkDeleteUrl) {
                console.error('Bulk delete URL not configured');
                return;
            }

            isBulkUpdating.value = true;

            try {
                const response = await fetch(props.bulkDeleteUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        taskIds: [...selectedIds.value]
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to delete tasks');
                }

                // Remove deleted tasks from local state
                const deletedIds = new Set(selectedIds.value);
                tasks.value = tasks.value.filter(t => !deletedIds.has(t.id));

                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Tasks Deleted', `${data.deleted} tasks deleted`);
                }

                clearSelection();
            } catch (error) {
                console.error('Bulk delete error:', error);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Delete Failed', error.message || 'Could not delete tasks');
                }
            } finally {
                isBulkUpdating.value = false;
            }
        };

        // Search handlers
        const handleSearchInput = (event) => {
            // Debounce search
            if (searchDebounceTimer) {
                clearTimeout(searchDebounceTimer);
            }
            searchDebounceTimer = setTimeout(() => {
                searchQuery.value = event.target.value;
            }, 200);
        };

        const clearSearch = () => {
            searchQuery.value = '';
            if (searchInputRef.value) {
                searchInputRef.value.value = '';
            }
        };

        const focusSearch = () => {
            if (searchInputRef.value) {
                searchInputRef.value.focus();
            }
        };

        // Table ref for keyboard navigation
        const tableRef = ref(null);

        // Confirm dialog ref
        const confirmDialogRef = ref(null);

        // Context menu state
        const contextMenu = ref({
            visible: false,
            x: 0,
            y: 0,
            tasks: []
        });

        // Long press timer for mobile
        let longPressTimer = null;

        // Show context menu
        const showContextMenu = (task, event) => {
            // If task is in current selection, use selection
            // Otherwise, use just this task
            let contextTasks = [];
            if (selectedIds.value.has(task.id)) {
                contextTasks = tasks.value.filter(t => selectedIds.value.has(t.id));
            } else {
                contextTasks = [task];
            }

            contextMenu.value = {
                visible: true,
                x: event.clientX,
                y: event.clientY,
                tasks: contextTasks
            };
        };

        // Hide context menu
        const hideContextMenu = () => {
            contextMenu.value.visible = false;
        };

        // Handle row context menu (right-click)
        const handleRowContextMenu = (task, event) => {
            showContextMenu(task, event);
        };

        // Context menu action handlers
        const handleContextEdit = (task) => {
            if (typeof window.openTaskPanel === 'function') {
                window.openTaskPanel(task.id);
            }
        };

        const handleContextCopyLink = (task) => {
            const basePath = props.basePath || window.BASE_PATH || '';
            const url = `${window.location.origin}${basePath}/tasks/${task.id}`;
            navigator.clipboard.writeText(url).then(() => {
                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Link Copied', 'Task link copied to clipboard');
                }
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        };

        const handleContextSetStatus = async (contextTasks, status) => {
            if (contextTasks.length === 1) {
                await saveInlineEdit(contextTasks[0].id, 'status', status);
            } else {
                // Bulk update
                const taskIds = contextTasks.map(t => t.id);
                selectedIds.value = new Set(taskIds);
                await handleBulkUpdate({ status });
            }
        };

        const handleContextSetPriority = async (contextTasks, priority) => {
            if (contextTasks.length === 1) {
                await saveInlineEdit(contextTasks[0].id, 'priority', priority);
            } else {
                // Bulk update
                const taskIds = contextTasks.map(t => t.id);
                selectedIds.value = new Set(taskIds);
                await handleBulkUpdate({ priority });
            }
        };

        const handleContextAssignTo = async (contextTasks, userId) => {
            // For single task, toggle assignee
            if (contextTasks.length === 1) {
                const task = contextTasks[0];
                const currentAssigneeIds = new Set((task.assignees || []).map(a => a.user?.id || a.id));
                const action = currentAssigneeIds.has(userId) ? 'remove' : 'add';
                await handleAssigneeChange(task.id, userId, action);
            } else {
                // For multiple tasks, add assignee to all
                for (const task of contextTasks) {
                    const currentAssigneeIds = new Set((task.assignees || []).map(a => a.user?.id || a.id));
                    if (!currentAssigneeIds.has(userId)) {
                        await handleAssigneeChange(task.id, userId, 'add');
                    }
                }
            }
        };

        const handleContextSetDueDate = (task) => {
            // Start inline editing for due date
            editingCell.value = { taskId: task.id, field: 'dueDate' };
        };

        const handleContextSetMilestone = async (contextTasks, milestoneId) => {
            if (contextTasks.length === 1) {
                await saveInlineEdit(contextTasks[0].id, 'milestone', milestoneId);
            } else {
                // Bulk update
                const taskIds = contextTasks.map(t => t.id);
                selectedIds.value = new Set(taskIds);
                await handleBulkUpdate({ milestone: milestoneId });
            }
        };

        // Subtask quick add state
        const subtaskQuickAddParentId = ref(null);
        const subtaskQuickAddRef = ref(null);
        const isCreatingSubtask = ref(false);

        const handleContextAddSubtask = async (task) => {
            if (!props.subtaskUrlTemplate) {
                console.warn('No subtask URL template configured');
                return;
            }

            // Show quick add row below this task
            subtaskQuickAddParentId.value = task.id;

            // Expand parent to show the quick add row in context
            if (!expandedIds.value.has(task.id)) {
                expandedIds.value.add(task.id);
                expandedIds.value = new Set(expandedIds.value);
                saveExpandedState();
            }

            await nextTick();
            if (subtaskQuickAddRef.value && subtaskQuickAddRef.value.focus) {
                subtaskQuickAddRef.value.focus();
            }
        };

        const cancelSubtaskQuickAdd = () => {
            subtaskQuickAddParentId.value = null;
        };

        const saveSubtaskQuickAdd = async (formData, continueAdding = false) => {
            const title = formData.title?.trim();
            if (!title || isCreatingSubtask.value) return;

            const parentId = subtaskQuickAddParentId.value;
            if (!parentId || !props.subtaskUrlTemplate) {
                cancelSubtaskQuickAdd();
                return;
            }

            const url = props.subtaskUrlTemplate.replace('__TASK_ID__', parentId);
            isCreatingSubtask.value = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ title })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to create subtask');
                }

                // Add the new subtask to tasks
                const newSubtask = data.subtask;
                tasks.value.push(newSubtask);

                // Update parent's subtask count
                const parent = tasks.value.find(t => t.id === parentId);
                if (parent) {
                    parent.subtaskCount = (parent.subtaskCount || 0) + 1;
                }

                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Subtask Created', `"${title}" created successfully`);
                }

                if (!continueAdding) {
                    cancelSubtaskQuickAdd();
                }
            } catch (error) {
                console.error('Error creating subtask:', error);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Create Failed', error.message || 'Could not create subtask');
                }
            } finally {
                isCreatingSubtask.value = false;
            }
        };

        const handleContextDuplicate = async (task) => {
            if (!props.duplicateUrlTemplate) {
                console.warn('No duplicate URL template configured');
                return;
            }

            const url = props.duplicateUrlTemplate.replace('__TASK_ID__', task.id);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to duplicate task');
                }

                // Add the duplicate task right after the original
                const newTask = data.task;
                newTask.assignees = newTask.assignees || [];
                newTask.tags = newTask.tags || [];

                // Find the index of the original task and insert after it
                const originalIndex = tasks.value.findIndex(t => t.id === task.id);
                if (originalIndex !== -1) {
                    // Update positions of tasks that come after
                    tasks.value.forEach(t => {
                        if (t.position >= newTask.position && t.id !== newTask.id) {
                            t.position = t.position + 1;
                        }
                    });
                    // Insert the new task right after the original
                    tasks.value.splice(originalIndex + 1, 0, newTask);
                } else {
                    // Fallback: just push to end
                    tasks.value.push(newTask);
                }

                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Task Duplicated', `"${newTask.title}" created`);
                }
            } catch (error) {
                console.error('Error duplicating task:', error);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Duplicate Failed', error.message || 'Could not duplicate task');
                }
            }
        };

        const handleContextDelete = async (contextTasks) => {
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

            if (count === 1) {
                // Single task delete
                const taskIds = [contextTasks[0].id];
                selectedIds.value = new Set(taskIds);
                await handleBulkDelete();
            } else {
                // Bulk delete
                const taskIds = contextTasks.map(t => t.id);
                selectedIds.value = new Set(taskIds);
                await handleBulkDelete();
            }
        };

        // Check if task can have subtasks (depth < 3)
        const canAddSubtaskTo = (task) => {
            return (task.depth || 0) < 2;
        };

        // Global keyboard shortcuts
        const handleGlobalKeydown = (event) => {
            // Ctrl/Cmd + F to focus search
            if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
                // Only if not already in an input
                if (document.activeElement?.tagName !== 'INPUT' &&
                    document.activeElement?.tagName !== 'TEXTAREA' &&
                    document.activeElement?.tagName !== 'SELECT') {
                    event.preventDefault();
                    focusSearch();
                }
            }

            // Escape to close context menu, cancel editing, or clear selection
            if (event.key === 'Escape') {
                if (contextMenu.value.visible) {
                    hideContextMenu();
                } else if (editingCell.value) {
                    cancelEditing();
                } else if (selectedIds.value.size > 0) {
                    clearSelection();
                }
            }

            // Shift+F10 or context menu key to open context menu on focused row
            if (event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) {
                const focusedRow = document.activeElement?.closest('tr[data-task-id]');
                if (focusedRow) {
                    const taskId = focusedRow.getAttribute('data-task-id');
                    const task = tasks.value.find(t => t.id === taskId);
                    if (task) {
                        event.preventDefault();
                        const rect = focusedRow.getBoundingClientRect();
                        showContextMenu(task, { clientX: rect.left + 50, clientY: rect.bottom });
                    }
                }
            }
        };

        // Handle keyboard navigation within table
        const handleTableKeydown = (event) => {
            const target = event.target;
            const row = target.closest('tr[data-task-id]');
            if (!row) return;

            const allRows = Array.from(tableRef.value?.querySelectorAll('tr[data-task-id]') || []);
            const currentIndex = allRows.indexOf(row);

            if (event.key === 'ArrowDown' && currentIndex < allRows.length - 1) {
                event.preventDefault();
                allRows[currentIndex + 1].focus();
            } else if (event.key === 'ArrowUp' && currentIndex > 0) {
                event.preventDefault();
                allRows[currentIndex - 1].focus();
            } else if (event.key === 'ArrowRight' && (row.getAttribute('aria-expanded') === 'false' || !row.hasAttribute('aria-expanded'))) {
                // Expand if has children
                const taskId = row.getAttribute('data-task-id');
                if (taskId && (hasChildren(taskId) || tasks.value.find(t => t.id === taskId)?.subtaskCount > 0)) {
                    event.preventDefault();
                    handleToggleExpand(taskId);
                }
            } else if (event.key === 'ArrowLeft' && row.getAttribute('aria-expanded') === 'true') {
                // Collapse
                const taskId = row.getAttribute('data-task-id');
                if (taskId) {
                    event.preventDefault();
                    handleToggleExpand(taskId);
                }
            }
        };

        // Lifecycle
        onMounted(() => {
            loadColumnConfig();
            loadExpandedState();
            loadGroupState();
            document.addEventListener('task-updated', handleTaskUpdate);
            document.addEventListener('task-assignees-updated', handleAssigneesUpdate);
            document.addEventListener('keydown', handleGlobalKeydown);
        });

        onUnmounted(() => {
            document.removeEventListener('task-updated', handleTaskUpdate);
            document.removeEventListener('task-assignees-updated', handleAssigneesUpdate);
            document.removeEventListener('keydown', handleGlobalKeydown);
            if (searchDebounceTimer) {
                clearTimeout(searchDebounceTimer);
            }
        });

        // Status and priority options for select dropdowns
        const statusOptions = statusConfig.map(s => ({
            value: s.value,
            label: s.label,
            badgeClass: `bg-${s.value === 'todo' ? 'gray' : s.value === 'in_progress' ? 'blue' : s.value === 'in_review' ? 'yellow' : 'green'}-100 text-${s.value === 'todo' ? 'gray' : s.value === 'in_progress' ? 'blue' : s.value === 'in_review' ? 'yellow' : 'green'}-700`
        }));

        const priorityOptions = priorityConfig.map(p => ({
            value: p.value,
            label: p.label,
            badgeClass: `bg-${p.value === 'none' ? 'gray' : p.value === 'low' ? 'blue' : p.value === 'medium' ? 'yellow' : 'red'}-100 text-${p.value === 'none' ? 'gray' : p.value === 'low' ? 'blue' : p.value === 'medium' ? 'yellow' : 'red'}-700`
        }));

        const milestoneOptions = computed(() => {
            return [
                { value: '', label: 'No Milestone' },
                ...props.milestones.map(m => ({ value: m.id, label: m.name }))
            ];
        });

        return {
            tasks,
            columns,
            sortColumn,
            sortDirection,
            selectedIds,
            expandedIds,
            displayItems,
            totalTaskCount,
            allSelected,
            someSelected,
            groupBy,
            groupOptions,
            collapsedGroups,
            visibleColumnCount,
            editingCell,
            isUpdating,
            statusOptions,
            priorityOptions,
            milestoneOptions,
            handleSort,
            handleSelectAll,
            handleSelect,
            handleToggleExpand,
            isLoadingChildren,
            expandAll,
            collapseAll,
            handleRowClick,
            handleCellClick,
            handleAddTaskInGroup,
            hasChildren,
            toggleGroupCollapse,
            setGroupBy,
            toggleColumnVisibility,
            reorderColumns,
            resetColumns,
            saveInlineEdit,
            cancelEditing,
            isEditingCell,
            isTaskUpdating,
            handleAssigneeChange,
            quickAddGroupKey,
            quickAddRowRef,
            quickAddDefaults,
            isCreating,
            cancelQuickAdd,
            saveQuickAdd,
            selectedTaskCount,
            isBulkUpdating,
            clearSelection,
            handleBulkUpdate,
            handleBulkDelete,
            searchQuery,
            searchInputRef,
            handleSearchInput,
            clearSearch,
            focusSearch,
            tableRef,
            handleTableKeydown,
            basePath: props.basePath || window.BASE_PATH || '',
            canEdit: props.canEdit,
            milestones: props.milestones,
            members: props.members,
            createUrl: props.createUrl,
            // Context menu
            contextMenu,
            hideContextMenu,
            handleRowContextMenu,
            handleContextEdit,
            handleContextCopyLink,
            handleContextSetStatus,
            handleContextSetPriority,
            handleContextAssignTo,
            handleContextSetDueDate,
            handleContextSetMilestone,
            handleContextAddSubtask,
            handleContextDuplicate,
            handleContextDelete,
            canAddSubtaskTo,
            // Subtask quick add
            subtaskQuickAddParentId,
            subtaskQuickAddRef,
            isCreatingSubtask,
            cancelSubtaskQuickAdd,
            saveSubtaskQuickAdd,
            // Confirm dialog
            confirmDialogRef
        };
    },

    template: `
        <div class="task-table-container bg-white shadow rounded-lg overflow-hidden" role="region" aria-label="Task list">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                    <span class="text-sm text-gray-500 hidden sm:inline">{{ totalTaskCount }} tasks</span>

                    <!-- Group By Dropdown -->
                    <div class="flex items-center gap-2">
                        <label for="groupBy" class="text-sm text-gray-500 hidden sm:inline">Group by:</label>
                        <select
                            id="groupBy"
                            :value="groupBy"
                            @change="setGroupBy($event.target.value)"
                            aria-label="Group tasks by"
                            class="text-sm border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500 py-1">
                            <option v-for="opt in groupOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Expand/Collapse All buttons -->
                    <div class="flex items-center gap-1 border-l border-gray-300 pl-2 sm:pl-4">
                        <button
                            type="button"
                            @click="expandAll"
                            class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded"
                            title="Expand all"
                            aria-label="Expand all tasks">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="collapseAll"
                            class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded"
                            title="Collapse all"
                            aria-label="Collapse all tasks">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Search Input -->
                    <div class="relative">
                        <input
                            ref="searchInputRef"
                            type="search"
                            placeholder="Search..."
                            aria-label="Search tasks"
                            @input="handleSearchInput"
                            class="w-32 sm:w-48 pl-8 pr-8 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <button
                            v-if="searchQuery"
                            type="button"
                            @click="clearSearch"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Add Task Button (when no grouping or for global add) -->
                    <button
                        v-if="canEdit && createUrl && groupBy === 'none'"
                        type="button"
                        @click="handleAddTaskInGroup('__all__')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Task
                    </button>
                    <ColumnConfig
                        :columns="columns"
                        @toggle-visibility="toggleColumnVisibility"
                        @reorder="reorderColumns"
                        @reset="resetColumns"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table ref="tableRef" class="min-w-full divide-y divide-gray-200" role="grid" aria-label="Tasks" @keydown="handleTableKeydown">
                    <TableHeader
                        :columns="columns"
                        :sort-column="sortColumn"
                        :sort-direction="sortDirection"
                        :all-selected="allSelected"
                        :some-selected="someSelected"
                        :can-edit="canEdit"
                        @sort="handleSort"
                        @select-all="handleSelectAll"
                    />
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <!-- Quick Add Row at top (when no grouping) -->
                        <QuickAddRow
                            v-if="groupBy === 'none' && quickAddGroupKey === '__all__'"
                            ref="quickAddRowRef"
                            :columns="columns"
                            :status-options="statusOptions"
                            :priority-options="priorityOptions"
                            :milestone-options="milestoneOptions"
                            :members="members"
                            :default-status="quickAddDefaults.status"
                            :default-priority="quickAddDefaults.priority"
                            :default-milestone="quickAddDefaults.milestone"
                            :is-creating="isCreating"
                            @save="saveQuickAdd"
                            @cancel="cancelQuickAdd"
                        />

                        <template v-for="(item, index) in displayItems" :key="item.type === 'group' ? 'g-' + item.groupKey : 't-' + item.task.id">
                            <!-- Group Header Row -->
                            <GroupRow
                                v-if="item.type === 'group'"
                                :group-key="item.groupKey"
                                :group-label="item.groupLabel"
                                :group-color="item.groupColor"
                                :task-count="item.taskCount"
                                :completed-count="item.completedCount"
                                :is-collapsed="item.isCollapsed"
                                :column-count="visibleColumnCount"
                                :can-add="canEdit && createUrl"
                                @toggle="toggleGroupCollapse"
                                @add-task="handleAddTaskInGroup"
                            />

                            <!-- Quick Add Row (after group header, when active) -->
                            <QuickAddRow
                                v-if="item.type === 'group' && quickAddGroupKey === item.groupKey && !item.isCollapsed"
                                ref="quickAddRowRef"
                                :columns="columns"
                                :status-options="statusOptions"
                                :priority-options="priorityOptions"
                                :milestone-options="milestoneOptions"
                                :members="members"
                                :default-status="quickAddDefaults.status"
                                :default-priority="quickAddDefaults.priority"
                                :default-milestone="quickAddDefaults.milestone"
                                :is-creating="isCreating"
                                @save="saveQuickAdd"
                                @cancel="cancelQuickAdd"
                            />

                            <!-- Task Row -->
                            <TaskRow
                                v-if="item.type === 'task'"
                                :task="item.task"
                                :columns="columns"
                                :base-path="basePath"
                                :selected="selectedIds.has(item.task.id)"
                                :can-edit="canEdit"
                                :milestones="milestones"
                                :members="members"
                                :depth="item.task.displayDepth || 0"
                                :is-expanded="expandedIds.has(item.task.id)"
                                :has-children="hasChildren(item.task.id)"
                                :is-loading-children="isLoadingChildren(item.task.id)"
                                :editing-field="editingCell?.taskId === item.task.id ? editingCell.field : null"
                                :is-updating="isTaskUpdating(item.task.id)"
                                :status-options="statusOptions"
                                :priority-options="priorityOptions"
                                :milestone-options="milestoneOptions"
                                :search-highlight="item.task.searchHighlight || ''"
                                @click="handleRowClick"
                                @select="handleSelect"
                                @toggle-expand="handleToggleExpand"
                                @cell-click="handleCellClick"
                                @save-edit="saveInlineEdit"
                                @cancel-edit="cancelEditing"
                                @assignee-change="handleAssigneeChange"
                                @contextmenu="handleRowContextMenu"
                            />

                            <!-- Subtask Quick Add Row (appears below parent task) -->
                            <QuickAddRow
                                v-if="item.type === 'task' && subtaskQuickAddParentId === item.task.id"
                                ref="subtaskQuickAddRef"
                                :columns="columns"
                                :status-options="statusOptions"
                                :priority-options="priorityOptions"
                                :milestone-options="milestoneOptions"
                                :members="members"
                                :default-status="'todo'"
                                :default-priority="'none'"
                                :default-milestone="item.task.milestoneId || ''"
                                :is-creating="isCreatingSubtask"
                                :depth="(item.task.displayDepth || 0) + 1"
                                :is-subtask="true"
                                :placeholder="'Add subtask...'"
                                @save="saveSubtaskQuickAdd"
                                @cancel="cancelSubtaskQuickAdd"
                            />
                        </template>

                        <!-- Empty state -->
                        <tr v-if="displayItems.length === 0">
                            <td :colspan="visibleColumnCount" class="px-6 py-12 text-center">
                                <template v-if="searchQuery">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No matching tasks</h3>
                                    <p class="mt-1 text-sm text-gray-500">No tasks match "{{ searchQuery }}". Try a different search term.</p>
                                    <button
                                        type="button"
                                        @click="clearSearch"
                                        class="mt-3 inline-flex items-center px-3 py-1.5 text-sm font-medium text-primary-600 hover:text-primary-700">
                                        Clear search
                                    </button>
                                </template>
                                <template v-else>
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No tasks</h3>
                                    <p class="mt-1 text-sm text-gray-500">Tasks will appear here once created.</p>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Action Bar -->
            <BulkActionBar
                v-if="selectedTaskCount > 0"
                :selected-count="selectedTaskCount"
                :status-options="statusOptions"
                :priority-options="priorityOptions"
                :milestone-options="milestoneOptions"
                :is-updating="isBulkUpdating"
                @clear-selection="clearSelection"
                @bulk-update="handleBulkUpdate"
                @bulk-delete="handleBulkDelete"
            />

            <!-- Context Menu -->
            <ContextMenu
                :visible="contextMenu.visible"
                :x="contextMenu.x"
                :y="contextMenu.y"
                :tasks="contextMenu.tasks"
                :status-options="statusOptions"
                :priority-options="priorityOptions"
                :milestone-options="milestoneOptions"
                :members="members"
                :can-edit="canEdit"
                :can-add-subtask="contextMenu.tasks.length === 1 && canAddSubtaskTo(contextMenu.tasks[0])"
                :can-duplicate="contextMenu.tasks.length === 1"
                @close="hideContextMenu"
                @edit="handleContextEdit"
                @copy-link="handleContextCopyLink"
                @set-status="handleContextSetStatus"
                @set-priority="handleContextSetPriority"
                @assign-to="handleContextAssignTo"
                @set-due-date="handleContextSetDueDate"
                @set-milestone="handleContextSetMilestone"
                @add-subtask="handleContextAddSubtask"
                @duplicate="handleContextDuplicate"
                @delete="handleContextDelete"
            />

            <!-- Confirm Dialog -->
            <ConfirmDialog ref="confirmDialogRef" />
        </div>
    `
};
