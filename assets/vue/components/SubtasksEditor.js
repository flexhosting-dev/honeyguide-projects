import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';

export default {
    name: 'SubtasksEditor',

    props: {
        taskId: { type: String, required: true },
        initialSubtasks: { type: Array, default: () => [] },
        basePath: { type: String, default: '' },
        canEdit: { type: Boolean, default: true },
        maxDepthReached: { type: Boolean, default: false },
        membersUrl: { type: String, default: '' },
        assignUrlTemplate: { type: String, default: '' }
    },

    setup(props) {
        const subtasks = ref([...props.initialSubtasks]);
        const newTitle = ref('');
        const saving = ref(false);
        const error = ref('');
        const basePath = props.basePath || window.BASE_PATH || '';
        const inputEl = ref(null);

        // Smart input state
        const selectedAssignee = ref(null);
        const selectedDueDate = ref('');
        const showMemberDropdown = ref(false);
        const showDateDropdown = ref(false);
        const showCustomDatePicker = ref(false);
        const memberSearch = ref('');
        const members = ref([]);
        const membersLoaded = ref(false);
        const triggerStart = ref(-1);

        const formatDate = (d) => d.toISOString().split('T')[0];
        const quickDateOptions = computed(() => {
            const today = new Date();
            const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
            const nextWeek = new Date(today); nextWeek.setDate(today.getDate() + 7);
            return [
                { label: 'Today', value: formatDate(today) },
                { label: 'Tomorrow', value: formatDate(tomorrow) },
                { label: 'In a week', value: formatDate(nextWeek) },
                { label: 'Custom date...', value: '__custom__' },
            ];
        });

        const completedCount = computed(() =>
            subtasks.value.filter(s => s.status?.value === 'completed').length
        );

        const filteredMembers = computed(() => {
            if (!memberSearch.value) return members.value;
            const q = memberSearch.value.toLowerCase();
            return members.value.filter(m =>
                m.fullName.toLowerCase().includes(q) || (m.email && m.email.toLowerCase().includes(q))
            );
        });

        const fetchMembers = async () => {
            if (membersLoaded.value || !props.membersUrl) return;
            try {
                const resp = await fetch(props.membersUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await resp.json();
                members.value = data.members || [];
                membersLoaded.value = true;
            } catch (e) {
                console.error('Failed to fetch members:', e);
            }
        };

        const handleInput = (e) => {
            const val = e.target.value;
            const pos = e.target.selectionStart;

            if (val[pos - 1] === '#' && props.membersUrl) {
                triggerStart.value = pos - 1;
                memberSearch.value = '';
                showMemberDropdown.value = true;
                showDateDropdown.value = false;
                fetchMembers();
                return;
            }

            if (val[pos - 1] === '@') {
                showDateDropdown.value = true;
                showCustomDatePicker.value = false;
                showMemberDropdown.value = false;
                newTitle.value = val.slice(0, pos - 1) + val.slice(pos);
                return;
            }

            if (showMemberDropdown.value && triggerStart.value >= 0) {
                memberSearch.value = val.slice(triggerStart.value + 1, pos);
            }
        };

        const selectMember = (member) => {
            selectedAssignee.value = member;
            showMemberDropdown.value = false;
            if (triggerStart.value >= 0) {
                const val = newTitle.value;
                const afterTrigger = val.indexOf(' ', triggerStart.value);
                const end = afterTrigger === -1 ? val.length : afterTrigger;
                newTitle.value = val.slice(0, triggerStart.value) + val.slice(end);
            }
            triggerStart.value = -1;
            memberSearch.value = '';
            nextTick(() => inputEl.value?.focus());
        };

        const removeMember = () => {
            selectedAssignee.value = null;
            nextTick(() => inputEl.value?.focus());
        };

        const selectQuickDate = (option) => {
            if (option.value === '__custom__') {
                showCustomDatePicker.value = true;
                showDateDropdown.value = false;
                nextTick(() => {
                    const dateInput = inputEl.value?.closest('.subtask-smart-input')?.querySelector('.subtask-date-input');
                    dateInput?.showPicker?.();
                    dateInput?.focus();
                });
                return;
            }
            selectedDueDate.value = option.value;
            showDateDropdown.value = false;
            showCustomDatePicker.value = false;
            nextTick(() => inputEl.value?.focus());
        };

        const selectCustomDate = (e) => {
            selectedDueDate.value = e.target.value;
            showCustomDatePicker.value = false;
            showDateDropdown.value = false;
            nextTick(() => inputEl.value?.focus());
        };

        const removeDate = () => {
            selectedDueDate.value = '';
            nextTick(() => inputEl.value?.focus());
        };

        const addSubtask = async () => {
            const title = newTitle.value.trim();
            if (!title || saving.value) return;

            saving.value = true;
            error.value = '';

            try {
                const body = { title };
                if (selectedDueDate.value) body.dueDate = selectedDueDate.value;

                const res = await fetch(`${basePath}/tasks/${props.taskId}/subtasks`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                const data = await res.json();
                if (!res.ok) {
                    error.value = data.error || 'Failed to create subtask';
                    return;
                }

                const subtaskData = data.subtask;

                // Assign if selected
                if (selectedAssignee.value && subtaskData?.id && props.assignUrlTemplate) {
                    const assignUrl = props.assignUrlTemplate.replace('__TASK_ID__', subtaskData.id);
                    await fetch(assignUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ action: 'add', userId: selectedAssignee.value.id })
                    });
                }

                subtasks.value.push(subtaskData);
                newTitle.value = '';
                selectedAssignee.value = null;
                selectedDueDate.value = '';
                nextTick(() => inputEl.value?.focus());
            } catch (e) {
                error.value = 'Network error';
            } finally {
                saving.value = false;
            }
        };

        const handleKeydown = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (showMemberDropdown.value) return;
                addSubtask();
            }
            if (e.key === 'Escape') {
                if (showMemberDropdown.value) {
                    showMemberDropdown.value = false;
                    if (triggerStart.value >= 0) {
                        newTitle.value = newTitle.value.slice(0, triggerStart.value);
                        triggerStart.value = -1;
                    }
                } else if (showDateDropdown.value || showCustomDatePicker.value) {
                    showDateDropdown.value = false;
                    showCustomDatePicker.value = false;
                }
            }
        };

        const openSubtask = (subtask) => {
            if (typeof window.pushSubtaskPanel === 'function') {
                window.pushSubtaskPanel(subtask.id);
            } else if (typeof window.openTaskPanel === 'function') {
                window.openTaskPanel(subtask.id);
            } else {
                window.location.href = `${basePath}/tasks/${subtask.id}`;
            }
        };

        const statusClass = (status) => {
            const v = status?.value || 'todo';
            const map = {
                completed: 'bg-green-100 text-green-700',
                in_progress: 'bg-blue-100 text-blue-700',
                in_review: 'bg-yellow-100 text-yellow-700',
                todo: 'bg-gray-100 text-gray-700'
            };
            return map[v] || map.todo;
        };

        return {
            subtasks, newTitle, saving, error, completedCount, addSubtask, handleKeydown, handleInput,
            openSubtask, statusClass, basePath, inputEl,
            selectedAssignee, selectedDueDate, showMemberDropdown, showDateDropdown, showCustomDatePicker,
            filteredMembers, selectMember, removeMember, quickDateOptions, selectQuickDate, selectCustomDate, removeDate
        };
    },

    template: `
        <div>
            <div v-if="subtasks.length > 0" class="mb-3">
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-500">Progress</span>
                    <span class="font-medium">{{ completedCount }}/{{ subtasks.length }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-primary-600 h-2 rounded-full transition-all"
                         :style="{ width: (subtasks.length > 0 ? (completedCount / subtasks.length * 100) : 0) + '%' }"></div>
                </div>
            </div>

            <div class="space-y-1">
                <div v-for="subtask in subtasks" :key="subtask.id"
                     class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer group"
                     @click="openSubtask(subtask)">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                          :class="statusClass(subtask.status)">
                        {{ subtask.status?.label || 'To Do' }}
                    </span>
                    <span class="text-sm text-gray-900 group-hover:text-primary-600 flex-1">{{ subtask.title }}</span>
                    <svg class="h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </div>
            </div>

            <div v-if="subtasks.length === 0 && !canEdit" class="text-sm text-gray-400 italic py-4">No subtasks yet</div>

            <div v-if="canEdit && !maxDepthReached" class="mt-3 subtask-smart-input">
                <div class="relative">
                    <div class="flex items-center gap-2">
                        <input type="text"
                               ref="inputEl"
                               v-model="newTitle"
                               @input="handleInput"
                               @keydown="handleKeydown"
                               placeholder="Add a subtask... (#assign, @date)"
                               class="flex-1 text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:ring-primary-500 focus:border-primary-500"
                               :disabled="saving">
                        <button @click="addSubtask"
                                :disabled="!newTitle.trim() || saving"
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg v-if="saving" class="animate-spin -ml-0.5 mr-1.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Add
                        </button>
                    </div>

                    <!-- Member dropdown -->
                    <div v-if="showMemberDropdown" class="absolute z-20 top-full left-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 max-h-40 overflow-y-auto">
                        <div v-if="filteredMembers.length === 0" class="px-3 py-2 text-xs text-gray-400">No members found</div>
                        <button
                            v-for="member in filteredMembers"
                            :key="member.id"
                            type="button"
                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 flex items-center gap-2"
                            @click.stop="selectMember(member)"
                        >
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 text-[10px] font-medium text-white flex-shrink-0">{{ member.initials }}</span>
                            <span class="truncate">{{ member.fullName }}</span>
                        </button>
                    </div>

                    <!-- Date dropdown -->
                    <div v-if="showDateDropdown" class="absolute z-20 top-full left-0 mt-1 w-44 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                        <button
                            v-for="opt in quickDateOptions"
                            :key="opt.value"
                            type="button"
                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 flex items-center gap-2"
                            @click.stop="selectQuickDate(opt)"
                        >
                            <span>{{ opt.label }}</span>
                            <span v-if="opt.value !== '__custom__'" class="ml-auto text-xs text-gray-400">{{ opt.value }}</span>
                        </button>
                    </div>

                    <!-- Custom date picker (shown after choosing "Custom date...") -->
                    <div v-if="showCustomDatePicker" class="absolute z-20 top-full left-0 mt-1">
                        <input type="date" class="subtask-date-input text-sm border border-gray-300 rounded px-2 py-1" @change="selectCustomDate" />
                    </div>
                </div>

                <!-- Chips -->
                <div v-if="selectedAssignee || selectedDueDate" class="flex flex-wrap gap-1.5 mt-1.5">
                    <span v-if="selectedAssignee" class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 px-2 py-0.5 text-xs">
                        {{ selectedAssignee.fullName }}
                        <button type="button" class="hover:text-blue-900" @click.stop="removeMember">&times;</button>
                    </span>
                    <span v-if="selectedDueDate" class="inline-flex items-center gap-1 rounded-full bg-green-50 text-green-700 px-2 py-0.5 text-xs">
                        {{ selectedDueDate }}
                        <button type="button" class="hover:text-green-900" @click.stop="removeDate">&times;</button>
                    </span>
                </div>

                <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
            </div>
            <div v-if="maxDepthReached && canEdit" class="mt-3 text-xs text-gray-400 italic">Maximum nesting depth reached</div>
        </div>
    `
};
