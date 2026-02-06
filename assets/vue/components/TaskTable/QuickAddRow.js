import { ref, computed, watch, nextTick } from 'vue';

export default {
    name: 'QuickAddRow',

    props: {
        columns: {
            type: Array,
            required: true
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
        members: {
            type: Array,
            default: () => []
        },
        defaultStatus: {
            type: String,
            default: 'todo'
        },
        defaultPriority: {
            type: String,
            default: 'none'
        },
        defaultMilestone: {
            type: String,
            default: ''
        },
        isCreating: {
            type: Boolean,
            default: false
        }
    },

    emits: ['save', 'cancel'],

    setup(props, { emit }) {
        // Form data
        const formData = ref({
            title: '',
            status: props.defaultStatus,
            priority: props.defaultPriority,
            milestone: props.defaultMilestone,
            dueDate: '',
            startDate: '',
            assignees: []
        });

        // Refs for focus management
        const titleInputRef = ref(null);
        const selectedAssignee = ref('');

        // Watch for default changes (when group changes)
        watch(() => props.defaultStatus, (val) => { formData.value.status = val; });
        watch(() => props.defaultPriority, (val) => { formData.value.priority = val; });
        watch(() => props.defaultMilestone, (val) => { formData.value.milestone = val; });

        // Available members (not already selected)
        const availableMembers = computed(() => {
            const selectedIds = new Set(formData.value.assignees);
            return props.members.filter(m => !selectedIds.has(m.id));
        });

        // Add assignee from dropdown
        const addAssignee = () => {
            if (selectedAssignee.value && !formData.value.assignees.includes(selectedAssignee.value)) {
                formData.value.assignees.push(selectedAssignee.value);
            }
            selectedAssignee.value = '';
        };

        // Visible columns
        const visibleColumns = computed(() => {
            return props.columns.filter(col => col.visible);
        });

        // Column width helper
        const getColumnWidth = (column) => {
            if (column.width === 'flex') return 'auto';
            return typeof column.width === 'number' ? `${column.width}px` : column.width;
        };

        // Focus title input on mount
        const focusTitle = async () => {
            await nextTick();
            if (titleInputRef.value) {
                titleInputRef.value.focus();
            }
        };

        // Handle keyboard navigation
        const handleKeydown = (event, columnKey) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                save(false);
            } else if (event.key === 'Enter' && event.shiftKey) {
                event.preventDefault();
                save(true);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                cancel();
            }
        };

        // Save task
        const save = (continueAdding = false) => {
            if (!formData.value.title.trim()) return;
            emit('save', { ...formData.value }, continueAdding);
            if (continueAdding) {
                // Reset form for next task but keep status/priority/milestone defaults
                formData.value.title = '';
                formData.value.dueDate = '';
                formData.value.startDate = '';
                formData.value.assignees = [];
                focusTitle();
            }
        };

        // Cancel
        const cancel = () => {
            emit('cancel');
        };

        // Expose focus method
        const focus = () => focusTitle();

        return {
            formData,
            titleInputRef,
            visibleColumns,
            getColumnWidth,
            handleKeydown,
            save,
            cancel,
            focus,
            selectedAssignee,
            availableMembers,
            addAssignee
        };
    },

    template: `
        <tr class="bg-primary-50">
            <td v-for="column in visibleColumns"
                :key="column.key"
                :style="{ width: getColumnWidth(column), minWidth: getColumnWidth(column) }"
                class="px-3 py-2 text-sm">

                <!-- Checkbox column - empty -->
                <template v-if="column.key === 'checkbox'">
                    <span class="block w-4"></span>
                </template>

                <!-- Title -->
                <template v-else-if="column.key === 'title'">
                    <div class="flex items-center gap-2">
                        <span class="w-5 flex-shrink-0"></span>
                        <input
                            ref="titleInputRef"
                            type="text"
                            v-model="formData.title"
                            placeholder="Task title..."
                            :disabled="isCreating"
                            @keydown="handleKeydown($event, 'title')"
                            class="flex-1 min-w-0 px-2 py-1 text-sm font-medium border border-primary-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white"
                        />
                    </div>
                </template>

                <!-- Status -->
                <template v-else-if="column.key === 'status'">
                    <select
                        v-model="formData.status"
                        :disabled="isCreating"
                        @keydown="handleKeydown($event, 'status')"
                        class="w-full text-xs border border-primary-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-1 bg-white">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </template>

                <!-- Priority -->
                <template v-else-if="column.key === 'priority'">
                    <select
                        v-model="formData.priority"
                        :disabled="isCreating"
                        @keydown="handleKeydown($event, 'priority')"
                        class="w-full text-xs border border-primary-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-1 bg-white">
                        <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </template>

                <!-- Due Date -->
                <template v-else-if="column.key === 'dueDate'">
                    <input
                        type="date"
                        v-model="formData.dueDate"
                        :disabled="isCreating"
                        @keydown="handleKeydown($event, 'dueDate')"
                        class="w-full text-xs border border-primary-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-1 bg-white"
                    />
                </template>

                <!-- Milestone -->
                <template v-else-if="column.key === 'milestone'">
                    <select
                        v-model="formData.milestone"
                        :disabled="isCreating"
                        @keydown="handleKeydown($event, 'milestone')"
                        class="w-full text-xs border border-primary-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-1 bg-white">
                        <option v-for="opt in milestoneOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </template>

                <!-- Assignees -->
                <template v-else-if="column.key === 'assignees'">
                    <div class="flex items-center gap-1">
                        <span
                            v-for="assigneeId in formData.assignees"
                            :key="assigneeId"
                            class="inline-flex h-5 w-5 items-center justify-center rounded-full overflow-hidden bg-gradient-to-br from-emerald-400 to-cyan-500 ring-1 ring-white"
                            :title="members.find(m => m.id === assigneeId)?.fullName">
                            <span class="text-xs font-medium text-white">
                                {{ (members.find(m => m.id === assigneeId)?.firstName || '?').charAt(0).toUpperCase() }}
                            </span>
                        </span>
                        <select
                            v-model="selectedAssignee"
                            @change="addAssignee"
                            :disabled="isCreating"
                            class="text-xs border border-primary-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-0.5 px-1 bg-white min-w-[70px]">
                            <option value="">+ Add</option>
                            <option v-for="member in availableMembers" :key="member.id" :value="member.id">
                                {{ member.fullName }}
                            </option>
                        </select>
                    </div>
                </template>

                <!-- Tags - placeholder -->
                <template v-else-if="column.key === 'tags'">
                    <span class="text-gray-400 text-xs">-</span>
                </template>

                <!-- Start Date -->
                <template v-else-if="column.key === 'startDate'">
                    <input
                        type="date"
                        v-model="formData.startDate"
                        :disabled="isCreating"
                        @keydown="handleKeydown($event, 'startDate')"
                        class="w-full text-xs border border-primary-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 py-1 px-1 bg-white"
                    />
                </template>

                <!-- Subtasks - empty for new task -->
                <template v-else-if="column.key === 'subtasks'">
                    <span class="text-gray-400 text-xs">-</span>
                </template>

                <!-- Other columns - empty -->
                <template v-else>
                    <span class="text-gray-400 text-xs">-</span>
                </template>
            </td>
        </tr>
        <!-- Action buttons row - sticky -->
        <tr class="bg-primary-50 border-b border-primary-200">
            <td :colspan="visibleColumns.length" class="py-1.5">
                <div class="sticky left-0 w-fit flex items-center gap-2 pl-12">
                    <button
                        type="button"
                        @click="save(false)"
                        :disabled="!formData.title.trim() || isCreating"
                        class="text-xs text-primary-600 hover:text-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span v-if="isCreating" class="flex items-center gap-1">
                            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Adding...
                        </span>
                        <span v-else>Save</span>
                    </button>
                    <span class="text-gray-300">|</span>
                    <button
                        type="button"
                        @click="save(true)"
                        :disabled="!formData.title.trim() || isCreating"
                        class="text-xs text-gray-500 hover:text-primary-600 disabled:opacity-50 disabled:cursor-not-allowed">
                        Save &amp; add another
                    </button>
                    <span class="text-gray-300">|</span>
                    <button
                        type="button"
                        @click="cancel"
                        :disabled="isCreating"
                        class="text-xs text-gray-400 hover:text-gray-600">
                        Cancel
                    </button>
                </div>
            </td>
        </tr>
    `
};
