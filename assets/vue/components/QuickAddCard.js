import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

export default {
    name: 'QuickAddCard',

    props: {
        projectId: { type: String, required: true },
        milestones: { type: Array, default: () => [] },
        columnValue: { type: [String, Number], default: null },
        columnMode: { type: String, default: 'status' },
        basePath: { type: String, default: '' },
        membersUrl: { type: String, default: '' },
        createUrl: { type: String, default: '' },
        assignUrlTemplate: { type: String, default: '' },
        subtaskUrlTemplate: { type: String, default: '' },
        parentTask: { type: Object, default: null },
    },

    emits: ['task-created', 'cancel'],

    setup(props, { emit }) {
        const title = ref('');
        const inputEl = ref(null);
        const selectedAssignee = ref(null);
        const selectedDueDate = ref('');
        const selectedMilestone = ref('');
        const submitting = ref(false);

        // Trigger states
        const showMemberDropdown = ref(false);
        const showDatePicker = ref(false);
        const memberSearch = ref('');
        const members = ref([]);
        const membersLoaded = ref(false);
        const triggerStart = ref(-1);

        // Milestone logic
        const showMilestoneSelect = computed(() => {
            return !props.parentTask && props.columnMode !== 'milestone' && props.milestones.length > 1;
        });

        const defaultMilestone = computed(() => {
            if (props.columnMode === 'milestone') return props.columnValue;
            if (props.milestones.length === 1) return props.milestones[0].id;
            return '';
        });

        onMounted(() => {
            selectedMilestone.value = defaultMilestone.value;
            nextTick(() => { inputEl.value?.focus(); });
            document.addEventListener('keydown', handleGlobalKey);
        });

        onUnmounted(() => {
            document.removeEventListener('keydown', handleGlobalKey);
        });

        const handleGlobalKey = (e) => {
            if (e.key === 'Escape') {
                showMemberDropdown.value = false;
                showDatePicker.value = false;
                emit('cancel');
            }
        };

        const fetchMembers = async () => {
            if (membersLoaded.value) return;
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

        const filteredMembers = computed(() => {
            if (!memberSearch.value) return members.value;
            const q = memberSearch.value.toLowerCase();
            return members.value.filter(m =>
                m.fullName.toLowerCase().includes(q) || (m.email && m.email.toLowerCase().includes(q))
            );
        });

        const handleInput = (e) => {
            const val = e.target.value;
            const pos = e.target.selectionStart;

            // Check for # trigger → member dropdown
            if (val[pos - 1] === '#') {
                triggerStart.value = pos - 1;
                memberSearch.value = '';
                showMemberDropdown.value = true;
                showDatePicker.value = false;
                fetchMembers();
                return;
            }

            // Check for @ trigger → date picker
            if (val[pos - 1] === '@') {
                showDatePicker.value = true;
                showMemberDropdown.value = false;
                // Remove the @ from title
                title.value = val.slice(0, pos - 1) + val.slice(pos);
                nextTick(() => {
                    const dateInput = document.querySelector('.quick-add-date-input');
                    dateInput?.focus();
                });
                return;
            }

            // Update member search if dropdown is open
            if (showMemberDropdown.value && triggerStart.value >= 0) {
                memberSearch.value = val.slice(triggerStart.value + 1, pos);
            }
        };

        const selectMember = (member) => {
            selectedAssignee.value = member;
            showMemberDropdown.value = false;
            // Remove #search from title
            if (triggerStart.value >= 0) {
                const val = title.value;
                const afterTrigger = val.indexOf(' ', triggerStart.value);
                const end = afterTrigger === -1 ? val.length : afterTrigger;
                title.value = val.slice(0, triggerStart.value) + val.slice(end);
            }
            triggerStart.value = -1;
            memberSearch.value = '';
            nextTick(() => inputEl.value?.focus());
        };

        const removeMember = () => {
            selectedAssignee.value = null;
            nextTick(() => inputEl.value?.focus());
        };

        const selectDate = (e) => {
            selectedDueDate.value = e.target.value;
            showDatePicker.value = false;
            nextTick(() => inputEl.value?.focus());
        };

        const removeDate = () => {
            selectedDueDate.value = '';
            nextTick(() => inputEl.value?.focus());
        };

        const handleKeydown = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (showMemberDropdown.value) return;
                submit();
            }
            if (e.key === 'Escape') {
                if (showMemberDropdown.value) {
                    showMemberDropdown.value = false;
                    // Remove the partial #search
                    if (triggerStart.value >= 0) {
                        title.value = title.value.slice(0, triggerStart.value);
                        triggerStart.value = -1;
                    }
                } else if (showDatePicker.value) {
                    showDatePicker.value = false;
                } else {
                    emit('cancel');
                }
            }
        };

        const submit = async () => {
            const trimmedTitle = title.value.trim();
            if (!trimmedTitle || submitting.value) return;

            submitting.value = true;

            try {
                let taskData;

                if (props.parentTask) {
                    // Create subtask
                    const url = props.subtaskUrlTemplate.replace('__TASK_ID__', props.parentTask.id);
                    const resp = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ title: trimmedTitle })
                    });
                    if (!resp.ok) throw new Error('Failed to create subtask');
                    const result = await resp.json();
                    taskData = result.subtask;
                } else {
                    // Create top-level task
                    const body = { title: trimmedTitle };

                    // Set milestone
                    body.milestone = selectedMilestone.value || defaultMilestone.value;
                    if (!body.milestone && props.milestones.length > 0) {
                        body.milestone = props.milestones[0].id;
                    }

                    // Column value inheritance
                    if (props.columnMode === 'status') body.status = props.columnValue;
                    else if (props.columnMode === 'priority') body.priority = props.columnValue;

                    if (selectedDueDate.value) body.dueDate = selectedDueDate.value;

                    const resp = await fetch(props.createUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(body)
                    });
                    if (!resp.ok) throw new Error('Failed to create task');
                    const result = await resp.json();
                    taskData = result.task;
                }

                // Assign if selected
                if (selectedAssignee.value && taskData?.id) {
                    const assignUrl = props.assignUrlTemplate.replace('__TASK_ID__', taskData.id);
                    await fetch(assignUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ action: 'add', userId: selectedAssignee.value.id })
                    });
                    // Add assignee to task data for immediate display
                    taskData.assignees = [{
                        id: selectedAssignee.value.id,
                        user: {
                            id: selectedAssignee.value.id,
                            firstName: selectedAssignee.value.fullName.split(' ')[0] || '',
                            lastName: selectedAssignee.value.fullName.split(' ').slice(1).join(' ') || '',
                            fullName: selectedAssignee.value.fullName,
                        }
                    }];
                }

                emit('task-created', taskData);

                // Reset for next entry
                title.value = '';
                selectedAssignee.value = null;
                selectedDueDate.value = '';
                if (showMilestoneSelect.value) {
                    selectedMilestone.value = defaultMilestone.value;
                }
                nextTick(() => inputEl.value?.focus());

            } catch (err) {
                console.error('Quick add error:', err);
                if (typeof Toastr !== 'undefined') Toastr.error('Error', 'Failed to create task.');
            } finally {
                submitting.value = false;
            }
        };

        return {
            title, inputEl, selectedAssignee, selectedDueDate, selectedMilestone,
            submitting, showMemberDropdown, showDatePicker, filteredMembers,
            showMilestoneSelect, handleInput, handleKeydown, selectMember,
            removeMember, selectDate, removeDate, submit
        };
    },

    template: `
        <div class="quick-add-card bg-white rounded-lg shadow-sm border-2 border-primary-300 p-3" @click.stop>
            <div class="relative">
                <input
                    ref="inputEl"
                    v-model="title"
                    type="text"
                    class="w-full text-sm border-0 outline-none bg-transparent placeholder-gray-400 p-0"
                    placeholder="Task title... (#assign, @date)"
                    enterkeyhint="send"
                    @input="handleInput"
                    @keydown="handleKeydown"
                    :disabled="submitting"
                />

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

                <!-- Date picker -->
                <div v-if="showDatePicker" class="absolute z-20 top-full left-0 mt-1">
                    <input type="date" class="quick-add-date-input text-sm border border-gray-300 rounded px-2 py-1" @change="selectDate" />
                </div>
            </div>

            <!-- Chips -->
            <div v-if="selectedAssignee || selectedDueDate" class="flex flex-wrap gap-1.5 mt-2">
                <span v-if="selectedAssignee" class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 px-2 py-0.5 text-xs">
                    {{ selectedAssignee.fullName }}
                    <button type="button" class="hover:text-blue-900" @click.stop="removeMember">&times;</button>
                </span>
                <span v-if="selectedDueDate" class="inline-flex items-center gap-1 rounded-full bg-green-50 text-green-700 px-2 py-0.5 text-xs">
                    {{ selectedDueDate }}
                    <button type="button" class="hover:text-green-900" @click.stop="removeDate">&times;</button>
                </span>
            </div>

            <!-- Milestone select -->
            <div v-if="showMilestoneSelect" class="mt-2">
                <select v-model="selectedMilestone" class="text-xs border border-gray-200 rounded px-2 py-1 w-full bg-white">
                    <option value="" disabled>Select milestone</option>
                    <option v-for="m in milestones" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
            </div>

            <!-- Footer hint -->
            <div class="mt-2 flex items-center justify-between">
                <span class="text-[10px] text-gray-400">Enter to save, Esc to cancel</span>
                <div v-if="submitting" class="flex items-center">
                    <svg class="animate-spin h-3 w-3 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </div>
    `
};
