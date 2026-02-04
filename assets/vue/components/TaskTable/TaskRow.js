import { ref, computed, nextTick, watch } from 'vue';

export default {
    name: 'TaskRow',

    props: {
        task: {
            type: Object,
            required: true
        },
        columns: {
            type: Array,
            required: true
        },
        basePath: {
            type: String,
            default: ''
        },
        selected: {
            type: Boolean,
            default: false
        },
        canEdit: {
            type: Boolean,
            default: false
        },
        milestones: {
            type: Array,
            default: () => []
        },
        depth: {
            type: Number,
            default: 0
        },
        isExpanded: {
            type: Boolean,
            default: false
        },
        hasChildren: {
            type: Boolean,
            default: false
        },
        isLoadingChildren: {
            type: Boolean,
            default: false
        },
        editingField: {
            type: String,
            default: null
        },
        isUpdating: {
            type: Boolean,
            default: false
        },
        statusOptions: {
            type: Array,
            default: () => []
        },
        priorityOptions: {
            type: Array,
            default: () => []
        },
        milestoneOptions: {
            type: Array,
            default: () => []
        },
        searchHighlight: {
            type: String,
            default: ''
        }
    },

    emits: ['click', 'select', 'toggle-expand', 'cell-click', 'save-edit', 'cancel-edit'],

    setup(props, { emit }) {
        const visibleColumns = computed(() => {
            return props.columns.filter(col => col.visible);
        });

        // Inline editing state
        const editValue = ref(null);
        const inputRef = ref(null);

        // Watch for editingField changes to initialize edit value
        watch(() => props.editingField, async (newField) => {
            if (newField) {
                // Initialize edit value based on field type
                switch (newField) {
                    case 'title':
                        editValue.value = props.task.title || '';
                        break;
                    case 'status':
                        editValue.value = props.task.status?.value || props.task.status || 'todo';
                        break;
                    case 'priority':
                        editValue.value = props.task.priority?.value || props.task.priority || 'none';
                        break;
                    case 'dueDate':
                        editValue.value = props.task.dueDate || '';
                        break;
                    case 'startDate':
                        editValue.value = props.task.startDate || '';
                        break;
                    case 'milestone':
                        editValue.value = props.task.milestoneId || '';
                        break;
                }
                await nextTick();
                if (inputRef.value) {
                    inputRef.value.focus();
                    if (newField === 'title') {
                        inputRef.value.select();
                    }
                }
            }
        });

        const handleRowClick = (event) => {
            // Don't trigger row click if clicking on checkbox or interactive elements
            if (event.target.closest('input, button, a, select, .cell-editor')) return;
            emit('click', props.task);
        };

        const handleSelect = (event) => {
            event.stopPropagation();
            emit('select', props.task.id, event.target.checked);
        };

        const handleCellClick = (event, columnKey) => {
            event.stopPropagation();
            emit('cell-click', props.task, columnKey, event);
        };

        const handleToggleExpand = (event) => {
            event.stopPropagation();
            emit('toggle-expand', props.task.id);
        };

        const getColumnWidth = (column) => {
            if (column.width === 'flex') return '';
            return typeof column.width === 'number' ? `${column.width}px` : column.width;
        };

        // Save inline edit
        const saveEdit = () => {
            emit('save-edit', props.task.id, props.editingField, editValue.value);
        };

        // Cancel inline edit
        const cancelEdit = () => {
            emit('cancel-edit');
        };

        // Handle keyboard in edit mode
        const handleEditKeydown = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveEdit();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                cancelEdit();
            }
        };

        // Handle select change
        const handleSelectChange = (event) => {
            editValue.value = event.target.value;
            saveEdit();
        };

        // Status styling
        const statusColors = {
            'todo': 'bg-gray-100 text-gray-700',
            'in_progress': 'bg-blue-100 text-blue-700',
            'in_review': 'bg-yellow-100 text-yellow-700',
            'completed': 'bg-green-100 text-green-700'
        };

        const getStatusClass = computed(() => {
            const status = props.task.status?.value || props.task.status || 'todo';
            return statusColors[status] || statusColors['todo'];
        });

        // Priority styling
        const priorityColors = {
            'none': 'bg-gray-100 text-gray-700',
            'low': 'bg-blue-100 text-blue-700',
            'medium': 'bg-yellow-100 text-yellow-700',
            'high': 'bg-red-100 text-red-700'
        };

        const getPriorityClass = computed(() => {
            const priority = props.task.priority?.value || props.task.priority || 'none';
            return priorityColors[priority] || priorityColors['none'];
        });

        // Date formatting
        const formatDate = (dateStr) => {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        };

        const isOverdue = computed(() => {
            if (!props.task.dueDate) return false;
            const status = props.task.status?.value || props.task.status || 'todo';
            if (status === 'completed') return false;
            const dueDate = new Date(props.task.dueDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return dueDate < today;
        });

        // Milestone name
        const milestoneName = computed(() => {
            if (!props.task.milestoneId) return '-';
            const milestone = props.milestones.find(m => m.id === props.task.milestoneId);
            return milestone?.name || props.task.milestone?.name || '-';
        });

        // Subtask count display
        const subtaskDisplay = computed(() => {
            const total = props.task.subtaskCount || 0;
            const completed = props.task.completedSubtaskCount || 0;
            if (total === 0) return '-';
            return `${completed}/${total}`;
        });

        // Tag colors
        const getTagStyle = (tag) => {
            const color = tag.color || '#6b7280';
            return {
                backgroundColor: `${color}20`,
                color: color,
                borderColor: `${color}40`
            };
        };

        // Highlight search text in title
        const highlightedTitle = computed(() => {
            if (!props.searchHighlight || !props.task.title) {
                return props.task.title || '';
            }
            const query = props.searchHighlight.toLowerCase();
            const title = props.task.title;
            const lowerTitle = title.toLowerCase();
            const index = lowerTitle.indexOf(query);
            if (index === -1) return title;

            const before = title.substring(0, index);
            const match = title.substring(index, index + query.length);
            const after = title.substring(index + query.length);
            return { before, match, after, hasMatch: true };
        });

        return {
            visibleColumns,
            handleRowClick,
            handleSelect,
            handleCellClick,
            handleToggleExpand,
            getColumnWidth,
            getStatusClass,
            getPriorityClass,
            formatDate,
            isOverdue,
            milestoneName,
            subtaskDisplay,
            getTagStyle,
            editValue,
            inputRef,
            saveEdit,
            cancelEdit,
            handleEditKeydown,
            handleSelectChange,
            highlightedTitle
        };
    },

    template: `
        <tr
            role="row"
            :class="[
                'task-table-row hover:bg-gray-50 cursor-pointer transition-colors',
                selected ? 'bg-primary-50' : '',
                depth > 0 ? 'task-table-row-child' : ''
            ]"
            :data-task-id="task.id"
            :data-depth="depth"
            :aria-selected="selected"
            :aria-level="depth + 1"
            :aria-expanded="hasChildren || task.subtaskCount > 0 ? isExpanded : undefined"
            tabindex="0"
            @click="handleRowClick"
            @keydown.enter="handleRowClick"
            @keydown.space.prevent="$emit('select', task.id, !selected)">

            <td v-for="column in visibleColumns"
                :key="column.key"
                :style="{ width: getColumnWidth(column), minWidth: getColumnWidth(column) }"
                :class="[
                    'px-3 py-3 text-sm',
                    column.key === 'title' ? 'max-w-xs' : 'whitespace-nowrap'
                ]">

                <!-- Checkbox -->
                <template v-if="column.key === 'checkbox'">
                    <input
                        v-if="canEdit"
                        type="checkbox"
                        :checked="selected"
                        @click.stop
                        @change="handleSelect"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        :aria-label="'Select task: ' + task.title"
                    />
                </template>

                <!-- Title -->
                <template v-else-if="column.key === 'title'">
                    <div class="flex items-center gap-2" :style="{ paddingLeft: (depth * 24) + 'px' }">
                        <!-- Tree toggle -->
                        <button
                            v-if="hasChildren || task.subtaskCount > 0"
                            type="button"
                            class="flex-shrink-0 w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded"
                            :disabled="isLoadingChildren"
                            @click="handleToggleExpand">
                            <!-- Loading spinner -->
                            <svg v-if="isLoadingChildren" class="animate-spin w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <svg v-else-if="isExpanded" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <span v-else-if="depth > 0" class="flex-shrink-0 w-5"></span>

                        <div class="min-w-0 flex-1">
                            <!-- Edit mode -->
                            <input
                                v-if="editingField === 'title'"
                                ref="inputRef"
                                type="text"
                                v-model="editValue"
                                @keydown="handleEditKeydown"
                                @blur="saveEdit"
                                class="w-full px-2 py-1 text-sm font-medium border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                            <!-- Display mode -->
                            <template v-else>
                                <span class="task-title font-medium text-gray-900 line-clamp-2 hover:text-primary-600 cursor-pointer"
                                      @click="handleCellClick($event, 'title')">
                                    <template v-if="highlightedTitle.hasMatch">
                                        {{ highlightedTitle.before }}<mark class="bg-yellow-200 px-0.5 rounded">{{ highlightedTitle.match }}</mark>{{ highlightedTitle.after }}
                                    </template>
                                    <template v-else>{{ task.title }}</template>
                                </span>
                                <span v-if="task.parentChain && depth === 0" class="text-xs text-gray-400 ml-1" :title="task.parentChain">
                                    in {{ task.parentChain }}
                                </span>
                            </template>
                        </div>

                        <!-- Loading indicator -->
                        <svg v-if="isUpdating" class="animate-spin h-4 w-4 text-primary-600 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                </template>

                <!-- Status -->
                <template v-else-if="column.key === 'status'">
                    <!-- Edit mode -->
                    <select
                        v-if="editingField === 'status'"
                        ref="inputRef"
                        v-model="editValue"
                        @change="handleSelectChange"
                        @blur="saveEdit"
                        @keydown="handleEditKeydown"
                        class="text-sm border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-2">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <!-- Display mode -->
                    <span
                        v-else
                        :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer hover:ring-2 hover:ring-primary-200', getStatusClass]"
                        @click="handleCellClick($event, 'status')">
                        {{ task.status?.label || task.status }}
                    </span>
                </template>

                <!-- Priority -->
                <template v-else-if="column.key === 'priority'">
                    <!-- Edit mode -->
                    <select
                        v-if="editingField === 'priority'"
                        ref="inputRef"
                        v-model="editValue"
                        @change="handleSelectChange"
                        @blur="saveEdit"
                        @keydown="handleEditKeydown"
                        class="text-sm border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-2">
                        <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <!-- Display mode -->
                    <span
                        v-else
                        :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer hover:ring-2 hover:ring-primary-200', getPriorityClass]"
                        @click="handleCellClick($event, 'priority')">
                        {{ task.priority?.label || task.priority }}
                    </span>
                </template>

                <!-- Assignees -->
                <template v-else-if="column.key === 'assignees'">
                    <div class="flex -space-x-1" @click="handleCellClick($event, 'assignees')">
                        <template v-if="task.assignees && task.assignees.length > 0">
                            <span
                                v-for="(assignee, idx) in task.assignees.slice(0, 3)"
                                :key="assignee.id"
                                class="inline-flex h-6 w-6 items-center justify-center rounded-full overflow-hidden ring-2 ring-white"
                                :title="assignee.user?.fullName || assignee.fullName">
                                <img
                                    v-if="assignee.user?.avatar || assignee.avatar"
                                    :src="assignee.user?.avatar || assignee.avatar"
                                    :alt="assignee.user?.fullName || assignee.fullName"
                                    class="w-full h-full object-cover"
                                />
                                <span v-else class="w-full h-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center">
                                    <span class="text-xs font-medium text-white">
                                        {{ (assignee.user?.firstName || assignee.firstName || '?').charAt(0).toUpperCase() }}
                                    </span>
                                </span>
                            </span>
                            <span
                                v-if="task.assignees.length > 3"
                                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 ring-2 ring-white">
                                <span class="text-xs font-medium text-gray-600">+{{ task.assignees.length - 3 }}</span>
                            </span>
                        </template>
                        <span v-else class="text-gray-400">-</span>
                    </div>
                </template>

                <!-- Due Date -->
                <template v-else-if="column.key === 'dueDate'">
                    <!-- Edit mode -->
                    <input
                        v-if="editingField === 'dueDate'"
                        ref="inputRef"
                        type="date"
                        v-model="editValue"
                        @change="saveEdit"
                        @blur="saveEdit"
                        @keydown="handleEditKeydown"
                        class="text-sm border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-2"
                    />
                    <!-- Display mode -->
                    <span
                        v-else
                        :class="[isOverdue ? 'text-red-600 font-medium' : 'text-gray-500', 'cursor-pointer hover:text-primary-600']"
                        @click="handleCellClick($event, 'dueDate')">
                        {{ formatDate(task.dueDate) }}
                    </span>
                </template>

                <!-- Start Date -->
                <template v-else-if="column.key === 'startDate'">
                    <!-- Edit mode -->
                    <input
                        v-if="editingField === 'startDate'"
                        ref="inputRef"
                        type="date"
                        v-model="editValue"
                        @change="saveEdit"
                        @blur="saveEdit"
                        @keydown="handleEditKeydown"
                        class="text-sm border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-2"
                    />
                    <!-- Display mode -->
                    <span
                        v-else
                        class="text-gray-500 cursor-pointer hover:text-primary-600"
                        @click="handleCellClick($event, 'startDate')">
                        {{ formatDate(task.startDate) }}
                    </span>
                </template>

                <!-- Tags -->
                <template v-else-if="column.key === 'tags'">
                    <div class="flex flex-wrap gap-1" @click="handleCellClick($event, 'tags')">
                        <template v-if="task.tags && task.tags.length > 0">
                            <span
                                v-for="tag in task.tags.slice(0, 2)"
                                :key="tag.id"
                                :style="getTagStyle(tag)"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border">
                                {{ tag.name }}
                            </span>
                            <span
                                v-if="task.tags.length > 2"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600">
                                +{{ task.tags.length - 2 }}
                            </span>
                        </template>
                        <span v-else class="text-gray-400">-</span>
                    </div>
                </template>

                <!-- Milestone -->
                <template v-else-if="column.key === 'milestone'">
                    <!-- Edit mode -->
                    <select
                        v-if="editingField === 'milestone'"
                        ref="inputRef"
                        v-model="editValue"
                        @change="handleSelectChange"
                        @blur="saveEdit"
                        @keydown="handleEditKeydown"
                        class="text-sm border border-primary-500 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-2">
                        <option v-for="opt in milestoneOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <!-- Display mode -->
                    <span
                        v-else
                        class="text-gray-500 cursor-pointer hover:text-primary-600"
                        @click="handleCellClick($event, 'milestone')">
                        {{ milestoneName }}
                    </span>
                </template>

                <!-- Subtasks -->
                <template v-else-if="column.key === 'subtasks'">
                    <span class="text-gray-500">
                        {{ subtaskDisplay }}
                    </span>
                </template>

                <!-- Default/unknown column -->
                <template v-else>
                    <span class="text-gray-500">-</span>
                </template>
            </td>
        </tr>
    `
};
