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

        // Drag and drop state
        const draggedItem = ref(null);
        const dragOverIndex = ref(null);

        // Smart input state
        const selectedAssignee = ref(null);
        const selectedDueDate = ref('');
        const showMemberDropdown = ref(false);
        const showDateDropdown = ref(false);
        const dropUp = ref(false);
        const updateDropDirection = () => {
            nextTick(() => {
                const el = inputEl.value?.closest('.relative');
                if (!el) return;
                const rect = el.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                dropUp.value = spaceBelow < 200;
            });
        };
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

        const incompleteCount = computed(() =>
            subtasks.value.filter(s => s.status?.value !== 'completed').length
        );

        const completingAll = ref(false);

        const completeAllSubtasks = async () => {
            if (completingAll.value || incompleteCount.value === 0) return;

            completingAll.value = true;
            error.value = '';

            try {
                const res = await fetch(`${basePath}/tasks/${props.taskId}/subtasks/complete-all`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                if (!res.ok) {
                    error.value = data.error || 'Failed to complete subtasks';
                    return;
                }

                // Update all subtasks to completed status
                const completedIds = new Set(data.completedIds || []);
                subtasks.value.forEach(subtask => {
                    if (completedIds.has(subtask.id)) {
                        subtask.status = { value: 'completed', label: 'Completed' };
                    }
                });

                if (typeof Toastr !== 'undefined') {
                    Toastr.success('Subtasks Completed', `${data.completedCount} subtask(s) marked as completed`);
                }
            } catch (e) {
                error.value = 'Network error';
            } finally {
                completingAll.value = false;
            }
        };

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
                updateDropDirection();
                return;
            }

            if (val[pos - 1] === '@') {
                showDateDropdown.value = true;
                showMemberDropdown.value = false;
                updateDropDirection();
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

        const dateInputEl = ref(null);

        const selectQuickDate = (option) => {
            if (option.value === '__custom__') {
                showDateDropdown.value = false;
                // showPicker must be called synchronously from user gesture
                dateInputEl.value?.showPicker?.();
                return;
            }
            selectedDueDate.value = option.value;
            showDateDropdown.value = false;
            nextTick(() => inputEl.value?.focus());
        };

        const selectCustomDate = (e) => {
            selectedDueDate.value = e.target.value;
            showDateDropdown.value = false;
            nextTick(() => inputEl.value?.focus());
        };

        const removeDate = () => {
            selectedDueDate.value = '';
            nextTick(() => inputEl.value?.focus());
        };

        const scrollInputIntoView = () => {
            setTimeout(() => {
                inputEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 350);
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
                    // Add assignee to subtask data for immediate display
                    subtaskData.assignees = [{
                        id: selectedAssignee.value.id,
                        user: {
                            id: selectedAssignee.value.id,
                            firstName: selectedAssignee.value.fullName.split(' ')[0] || '',
                            lastName: selectedAssignee.value.fullName.split(' ').slice(1).join(' ') || '',
                            fullName: selectedAssignee.value.fullName,
                            avatar: selectedAssignee.value.avatar || null
                        }
                    }];
                }

                // Add dueDate to subtask data for immediate display
                if (selectedDueDate.value) {
                    subtaskData.dueDate = selectedDueDate.value;
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
                } else if (showDateDropdown.value) {
                    showDateDropdown.value = false;
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

        const isOverdue = (dateStr) => {
            if (!dateStr) return false;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const due = new Date(dateStr);
            return due < today;
        };

        const formatDisplayDate = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const due = new Date(date);
            due.setHours(0, 0, 0, 0);

            if (due.getTime() === today.getTime()) return 'Today';
            if (due.getTime() === tomorrow.getTime()) return 'Tomorrow';

            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        };

        const getInitials = (user) => {
            if (!user) return '?';
            const first = user.firstName?.[0] || '';
            const last = user.lastName?.[0] || '';
            return (first + last).toUpperCase() || user.fullName?.[0]?.toUpperCase() || '?';
        };

        // Drag and drop handlers
        const handleDragStart = (event, item) => {
            draggedItem.value = item;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.id);
            setTimeout(() => {
                event.target.classList.add('dragging');
            }, 0);
        };

        const handleDragEnd = (event) => {
            event.target.classList.remove('dragging');
            draggedItem.value = null;
            dragOverIndex.value = null;
        };

        const handleDragOver = (event, index) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            dragOverIndex.value = index;
        };

        const handleDragLeave = (event) => {
            if (!event.currentTarget.contains(event.relatedTarget)) {
                dragOverIndex.value = null;
            }
        };

        const handleDrop = async (event, targetIndex) => {
            event.preventDefault();
            event.stopPropagation();

            const dragged = draggedItem.value;
            if (!dragged || saving.value) return;

            draggedItem.value = null;
            dragOverIndex.value = null;

            const draggedId = dragged.id;
            const currentIndex = subtasks.value.findIndex(s => s.id === draggedId);

            if (currentIndex === -1 || currentIndex === targetIndex) {
                return;
            }

            // Reorder items locally
            const itemsCopy = [...subtasks.value];
            const [removed] = itemsCopy.splice(currentIndex, 1);

            let insertIndex = targetIndex;
            if (currentIndex < targetIndex) {
                insertIndex = targetIndex - 1;
            }
            itemsCopy.splice(insertIndex, 0, removed);
            subtasks.value = itemsCopy;

            // Persist to server
            await saveOrder();
        };

        const saveOrder = async () => {
            if (saving.value) return;
            saving.value = true;
            const subtaskIds = subtasks.value.map(s => s.id);

            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/subtasks/reorder`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ subtaskIds })
                });

                if (response.ok) {
                    if (typeof Toastr !== 'undefined') {
                        Toastr.success('Subtasks Reordered', 'Order updated');
                    }
                } else {
                    console.error('Failed to save subtask order');
                    if (typeof Toastr !== 'undefined') {
                        Toastr.error('Reorder Failed', 'Could not save new order');
                    }
                }
            } catch (err) {
                console.error('Error saving subtask order:', err);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Reorder Failed', 'Could not save new order');
                }
            } finally {
                saving.value = false;
            }
        };

        return {
            subtasks, newTitle, saving, error, completedCount, incompleteCount, addSubtask, handleKeydown, handleInput,
            openSubtask, statusClass, basePath, inputEl,
            selectedAssignee, selectedDueDate, showMemberDropdown, showDateDropdown, dropUp, scrollInputIntoView,
            filteredMembers, selectMember, removeMember, quickDateOptions, selectQuickDate, selectCustomDate, removeDate, dateInputEl,
            isOverdue, formatDisplayDate, getInitials,
            // Drag and drop
            draggedItem, dragOverIndex,
            handleDragStart, handleDragEnd, handleDragOver, handleDragLeave, handleDrop,
            // Complete all
            completingAll, completeAllSubtasks
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
                <!-- Complete all subtasks button -->
                <button v-if="canEdit && incompleteCount > 0"
                        @click="completeAllSubtasks"
                        :disabled="completingAll"
                        class="mt-2 inline-flex items-center text-xs text-primary-600 hover:text-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg v-if="completingAll" class="animate-spin -ml-0.5 mr-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Complete all subtasks
                </button>
            </div>

            <div class="space-y-1">
                <div v-for="(subtask, index) in subtasks" :key="subtask.id"
                     class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer group transition-all"
                     :class="{
                         'opacity-50': draggedItem && draggedItem.id === subtask.id,
                         'border-t-2 border-primary-500': dragOverIndex === index && draggedItem && draggedItem.id !== subtask.id
                     }"
                     :draggable="canEdit"
                     @dragstart="canEdit && handleDragStart($event, subtask)"
                     @dragend="handleDragEnd"
                     @dragover="canEdit && handleDragOver($event, index)"
                     @dragleave="handleDragLeave"
                     @drop="canEdit && handleDrop($event, index)"
                     @click="openSubtask(subtask)">
                    <!-- Drag Handle -->
                    <div v-if="canEdit" class="cursor-grab text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity select-none flex-shrink-0" @click.stop>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </div>

                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                          :class="statusClass(subtask.status)">
                        {{ subtask.status?.label || 'To Do' }}
                    </span>
                    <span class="text-sm text-gray-900 group-hover:text-primary-600 flex-1 truncate">{{ subtask.title }}</span>

                    <!-- Due date indicator -->
                    <span v-if="subtask.dueDate" class="inline-flex items-center gap-1 text-xs text-gray-500" :class="{ 'text-red-500': isOverdue(subtask.dueDate) }">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span class="hidden sm:inline">{{ formatDisplayDate(subtask.dueDate) }}</span>
                    </span>
                    <span v-else class="text-gray-300">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </span>

                    <!-- Assignee indicator -->
                    <span v-if="subtask.assignees && subtask.assignees.length > 0" class="inline-flex -space-x-1">
                        <template v-for="(assignee, idx) in subtask.assignees.slice(0, 2)" :key="assignee.id">
                            <img v-if="assignee.user?.avatar" :src="assignee.user.avatar" :alt="assignee.user.fullName" :title="assignee.user.fullName" class="h-5 w-5 rounded-full ring-1 ring-white object-cover" />
                            <span v-else class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 text-[9px] font-medium text-white ring-1 ring-white" :title="assignee.user?.fullName">
                                {{ getInitials(assignee.user) }}
                            </span>
                        </template>
                        <span v-if="subtask.assignees.length > 2" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-200 text-[9px] font-medium text-gray-600 ring-1 ring-white">
                            +{{ subtask.assignees.length - 2 }}
                        </span>
                    </span>
                    <span v-else class="text-gray-300" title="Unassigned">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </span>

                    <svg class="h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </div>

                <!-- Drop zone at end of list -->
                <div
                    v-if="canEdit && subtasks.length > 0 && draggedItem"
                    class="h-8 rounded-md transition-all"
                    :class="{ 'border-t-2 border-primary-500 bg-primary-50': dragOverIndex === subtasks.length }"
                    @dragover="handleDragOver($event, subtasks.length)"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop($event, subtasks.length)"
                ></div>
            </div>

            <div v-if="subtasks.length === 0 && !canEdit" class="text-sm text-gray-400 italic py-4">No subtasks yet</div>

            <div v-if="canEdit && !maxDepthReached" class="mt-3 subtask-smart-input">
                <div class="relative">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 flex items-center flex-wrap gap-1 border border-gray-300 rounded-md px-2 py-1 focus-within:ring-1 focus-within:ring-primary-500 focus-within:border-primary-500" @click="inputEl?.focus()">
                            <span v-if="selectedAssignee" class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 px-2 py-0.5 text-xs whitespace-nowrap">
                                <span class="text-blue-400">assigned to</span> {{ selectedAssignee.fullName }}
                                <button type="button" class="hover:text-blue-900" @click.stop="removeMember">&times;</button>
                            </span>
                            <span v-if="selectedDueDate" class="inline-flex items-center gap-1 rounded-full bg-green-50 text-green-700 px-2 py-0.5 text-xs whitespace-nowrap">
                                <span class="text-green-400">due by</span> {{ selectedDueDate }}
                                <button type="button" class="hover:text-green-900" @click.stop="removeDate">&times;</button>
                            </span>
                            <input type="text"
                                   ref="inputEl"
                                   v-model="newTitle"
                                   @input="handleInput"
                                   @keydown="handleKeydown"
                                   @focus="scrollInputIntoView"
                                   placeholder="Add a subtask... (#assign, @date)"
                                   class="flex-1 min-w-[120px] text-sm border-0 outline-none bg-transparent p-0.5"
                                   :disabled="saving">
                        </div>
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
                    <div v-if="showMemberDropdown" class="absolute z-20 left-0 w-56 bg-white rounded-lg shadow-lg border border-gray-200 max-h-40 overflow-y-auto" :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'">
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
                    <div v-if="showDateDropdown" class="absolute z-20 left-0 w-44 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden" :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'">
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

                    <!-- Hidden date input for native picker -->
                    <input type="date" ref="dateInputEl" class="sr-only" tabindex="-1" @change="selectCustomDate" />
                </div>



                <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
            </div>
            <div v-if="maxDepthReached && canEdit" class="mt-3 text-xs text-gray-400 italic">Maximum nesting depth reached</div>
        </div>
    `
};
