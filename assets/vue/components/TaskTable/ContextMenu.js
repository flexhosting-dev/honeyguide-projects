import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

export default {
    name: 'ContextMenu',

    props: {
        visible: {
            type: Boolean,
            default: false
        },
        x: {
            type: Number,
            default: 0
        },
        y: {
            type: Number,
            default: 0
        },
        tasks: {
            type: Array,
            default: () => []
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
        canEdit: {
            type: Boolean,
            default: false
        },
        canAddSubtask: {
            type: Boolean,
            default: true
        },
        canDuplicate: {
            type: Boolean,
            default: true
        },
        canPromote: {
            type: Boolean,
            default: false
        },
        canDemote: {
            type: Boolean,
            default: false
        }
    },

    emits: [
        'close',
        'edit',
        'copy-link',
        'set-status',
        'set-priority',
        'assign-to',
        'set-due-date',
        'set-milestone',
        'add-subtask',
        'add-above',
        'add-below',
        'duplicate',
        'delete',
        'promote',
        'demote'
    ],

    setup(props, { emit }) {
        const menuRef = ref(null);
        const activeSubmenu = ref(null);
        const submenuPosition = ref({ left: true, dropDown: true }); // horizontal and vertical directions

        // Adjusted position state (reactive)
        const adjustedPos = ref({ x: 0, y: 0, ready: false, dropDown: true });

        // Is multi-select mode
        const isMultiSelect = computed(() => props.tasks.length > 1);

        // Single task (when not multi-select)
        const singleTask = computed(() => props.tasks.length === 1 ? props.tasks[0] : null);

        // Get current status of single task
        const currentStatus = computed(() => {
            if (!singleTask.value) return null;
            return singleTask.value.status?.value || singleTask.value.status || 'todo';
        });

        // Get current priority of single task
        const currentPriority = computed(() => {
            if (!singleTask.value) return null;
            return singleTask.value.priority?.value || singleTask.value.priority || 'none';
        });

        // Get current milestone of single task
        const currentMilestone = computed(() => {
            if (!singleTask.value) return null;
            return singleTask.value.milestoneId || '';
        });

        // Get current assignee IDs
        const currentAssigneeIds = computed(() => {
            if (!singleTask.value) return new Set();
            return new Set((singleTask.value.assignees || []).map(a => a.user?.id || a.id));
        });

        // Menu style using adjusted position
        const menuStyle = computed(() => {
            return {
                position: 'fixed',
                left: `${adjustedPos.value.x}px`,
                top: `${adjustedPos.value.y}px`,
                zIndex: 9999,
                visibility: adjustedPos.value.ready ? 'visible' : 'hidden',
                transformOrigin: adjustedPos.value.dropDown ? 'top left' : 'bottom left'
            };
        });

        // Calculate and adjust position
        const adjustPosition = async () => {
            // Reset ready state
            adjustedPos.value.ready = false;

            await nextTick();
            if (!menuRef.value) return;

            const rect = menuRef.value.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const menuHeight = rect.height;
            const menuWidth = rect.width;

            let adjustedX = props.x;
            let adjustedY = props.y;
            let shouldDropDown = true;

            // Check if menu would overflow bottom of viewport
            const spaceBelow = viewportHeight - props.y;
            const spaceAbove = props.y;

            if (spaceBelow < menuHeight + 10) {
                // Not enough space below
                if (spaceAbove >= menuHeight + 10) {
                    // Enough space above - drop up
                    shouldDropDown = false;
                    adjustedY = props.y - menuHeight;
                } else {
                    // Not enough space above either - position to fit
                    if (spaceBelow > spaceAbove) {
                        // More space below, align to bottom edge
                        adjustedY = viewportHeight - menuHeight - 10;
                        shouldDropDown = true;
                    } else {
                        // More space above, align to top edge
                        adjustedY = 10;
                        shouldDropDown = true;
                    }
                }
            }

            // Check right edge
            if (props.x + menuWidth > viewportWidth - 10) {
                adjustedX = viewportWidth - menuWidth - 10;
            }

            // Check left edge
            if (adjustedX < 10) {
                adjustedX = 10;
            }

            // Check top edge (for drop up case)
            if (adjustedY < 10) {
                adjustedY = 10;
            }

            // Update reactive position state
            adjustedPos.value = {
                x: adjustedX,
                y: adjustedY,
                ready: true,
                dropDown: shouldDropDown
            };

            // Determine submenu direction (horizontal)
            submenuPosition.value.left = adjustedX < viewportWidth / 2;

            // Determine submenu vertical direction based on remaining space
            const menuBottom = adjustedY + menuHeight;
            submenuPosition.value.dropDown = menuBottom < viewportHeight - 100;
        };

        // Watch for visibility changes to recalculate position
        watch(() => props.visible, async (newVisible) => {
            if (newVisible) {
                // Set initial position before measuring
                adjustedPos.value = { x: props.x, y: props.y, ready: false, dropDown: true };
                await nextTick();
                adjustPosition();
            }
        });

        // Also watch for position changes while visible
        watch([() => props.x, () => props.y], () => {
            if (props.visible) {
                adjustedPos.value = { x: props.x, y: props.y, ready: false, dropDown: true };
                nextTick(() => adjustPosition());
            }
        });

        // Handle click outside
        const handleClickOutside = (event) => {
            if (menuRef.value && !menuRef.value.contains(event.target)) {
                emit('close');
            }
        };

        // Handle escape key
        const handleKeydown = (event) => {
            if (event.key === 'Escape') {
                if (activeSubmenu.value) {
                    activeSubmenu.value = null;
                } else {
                    emit('close');
                }
            }
        };

        // Handle scroll - close menu
        const handleScroll = () => {
            emit('close');
        };

        // Toggle submenu
        const toggleSubmenu = (name) => {
            activeSubmenu.value = activeSubmenu.value === name ? null : name;
        };

        // Show submenu on hover
        const showSubmenu = (name) => {
            activeSubmenu.value = name;
        };

        // Hide submenu
        const hideSubmenu = () => {
            activeSubmenu.value = null;
        };

        // Menu item handlers
        const handleEdit = () => {
            emit('edit', singleTask.value);
            emit('close');
        };

        const handleCopyLink = () => {
            emit('copy-link', singleTask.value);
            emit('close');
        };

        const handleSetStatus = (status) => {
            emit('set-status', props.tasks, status);
            emit('close');
        };

        const handleSetPriority = (priority) => {
            emit('set-priority', props.tasks, priority);
            emit('close');
        };

        const handleAssignTo = (userId) => {
            emit('assign-to', props.tasks, userId);
            emit('close');
        };

        const handleSetDueDate = () => {
            emit('set-due-date', singleTask.value);
            emit('close');
        };

        const handleSetMilestone = (milestoneId) => {
            emit('set-milestone', props.tasks, milestoneId);
            emit('close');
        };

        const handleAddSubtask = () => {
            emit('add-subtask', singleTask.value);
            emit('close');
        };

        const handleAddAbove = () => {
            emit('add-above', singleTask.value);
            emit('close');
        };

        const handleAddBelow = () => {
            emit('add-below', singleTask.value);
            emit('close');
        };

        const handleDuplicate = () => {
            emit('duplicate', singleTask.value);
            emit('close');
        };

        const handleDelete = () => {
            emit('delete', props.tasks);
            emit('close');
        };

        const handlePromote = () => {
            emit('promote', singleTask.value);
            emit('close');
        };

        const handleDemote = () => {
            emit('demote', singleTask.value);
            emit('close');
        };

        // Lifecycle
        onMounted(() => {
            // Initial position calculation if already visible
            if (props.visible) {
                adjustedPos.value = { x: props.x, y: props.y, ready: false, dropDown: true };
                nextTick(() => adjustPosition());
            }
            document.addEventListener('click', handleClickOutside);
            document.addEventListener('keydown', handleKeydown);
            document.addEventListener('scroll', handleScroll, true);
        });

        onUnmounted(() => {
            document.removeEventListener('click', handleClickOutside);
            document.removeEventListener('keydown', handleKeydown);
            document.removeEventListener('scroll', handleScroll, true);
        });

        return {
            menuRef,
            menuStyle,
            activeSubmenu,
            adjustedPos,
            submenuPosition,
            isMultiSelect,
            singleTask,
            currentStatus,
            currentPriority,
            currentMilestone,
            currentAssigneeIds,
            toggleSubmenu,
            showSubmenu,
            hideSubmenu,
            handleEdit,
            handleCopyLink,
            handleSetStatus,
            handleSetPriority,
            handleAssignTo,
            handleSetDueDate,
            handleSetMilestone,
            handleAddSubtask,
            handleAddAbove,
            handleAddBelow,
            handleDuplicate,
            handleDelete,
            handlePromote,
            handleDemote
        };
    },

    template: `
        <Teleport to="body">
            <div
                v-if="visible"
                ref="menuRef"
                :style="menuStyle"
                class="bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[200px] select-none"
                role="menu"
                aria-orientation="vertical">

                <!-- Multi-select header -->
                <div v-if="isMultiSelect" class="px-3 py-2 text-sm font-medium text-gray-700 border-b border-gray-100">
                    {{ tasks.length }} tasks selected
                </div>

                <!-- Single task actions -->
                <template v-if="!isMultiSelect && canEdit">
                    <!-- Edit Task -->
                    <button
                        type="button"
                        @click="handleEdit"
                        class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        role="menuitem">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Task
                    </button>

                    <!-- Copy Link -->
                    <button
                        type="button"
                        @click="handleCopyLink"
                        class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        role="menuitem">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                        Copy Link
                    </button>

                    <div class="border-t border-gray-100 my-1"></div>
                </template>

                <!-- Status submenu -->
                <div
                    v-if="canEdit"
                    class="relative"
                    @mouseenter="showSubmenu('status')"
                    @mouseleave="hideSubmenu">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        role="menuitem"
                        aria-haspopup="true"
                        :aria-expanded="activeSubmenu === 'status'">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span v-if="isMultiSelect">Set Status</span>
                            <span v-else>Status</span>
                        </span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <!-- Status submenu -->
                    <div
                        v-if="activeSubmenu === 'status'"
                        :class="[
                            'absolute bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[150px]',
                            submenuPosition.left ? 'left-full ml-1' : 'right-full mr-1',
                            submenuPosition.dropDown ? 'top-0' : 'bottom-0'
                        ]"
                        role="menu">
                        <button
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            type="button"
                            @click="handleSetStatus(opt.value)"
                            class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            role="menuitem">
                            <span>{{ opt.label }}</span>
                            <svg v-if="!isMultiSelect && currentStatus === opt.value" class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Priority submenu -->
                <div
                    v-if="canEdit"
                    class="relative"
                    @mouseenter="showSubmenu('priority')"
                    @mouseleave="hideSubmenu">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        role="menuitem"
                        aria-haspopup="true"
                        :aria-expanded="activeSubmenu === 'priority'">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                            </svg>
                            <span v-if="isMultiSelect">Set Priority</span>
                            <span v-else>Priority</span>
                        </span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <!-- Priority submenu -->
                    <div
                        v-if="activeSubmenu === 'priority'"
                        :class="[
                            'absolute bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[150px]',
                            submenuPosition.left ? 'left-full ml-1' : 'right-full mr-1',
                            submenuPosition.dropDown ? 'top-0' : 'bottom-0'
                        ]"
                        role="menu">
                        <button
                            v-for="opt in priorityOptions"
                            :key="opt.value"
                            type="button"
                            @click="handleSetPriority(opt.value)"
                            class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            role="menuitem">
                            <span>{{ opt.label }}</span>
                            <svg v-if="!isMultiSelect && currentPriority === opt.value" class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Assign to submenu -->
                <div
                    v-if="canEdit && members.length > 0"
                    class="relative"
                    @mouseenter="showSubmenu('assignee')"
                    @mouseleave="hideSubmenu">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        role="menuitem"
                        aria-haspopup="true"
                        :aria-expanded="activeSubmenu === 'assignee'">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Assign to
                        </span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <!-- Assignee submenu -->
                    <div
                        v-if="activeSubmenu === 'assignee'"
                        :class="[
                            'absolute bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[180px] max-h-[250px] overflow-y-auto',
                            submenuPosition.left ? 'left-full ml-1' : 'right-full mr-1',
                            submenuPosition.dropDown ? 'top-0' : 'bottom-0'
                        ]"
                        role="menu">
                        <button
                            v-for="member in members"
                            :key="member.id"
                            type="button"
                            @click="handleAssignTo(member.id)"
                            class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            role="menuitem">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full overflow-hidden bg-gradient-to-br from-emerald-400 to-cyan-500 flex-shrink-0">
                                    <span class="text-xs font-medium text-white">
                                        {{ (member.firstName || member.fullName || '?').charAt(0).toUpperCase() }}
                                    </span>
                                </span>
                                <span class="truncate">{{ member.fullName }}</span>
                            </span>
                            <svg v-if="!isMultiSelect && currentAssigneeIds.has(member.id)" class="w-4 h-4 text-primary-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Set Due Date (single task only) -->
                <button
                    v-if="canEdit && !isMultiSelect"
                    type="button"
                    @click="handleSetDueDate"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    role="menuitem">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Set Due Date
                </button>

                <!-- Move to Milestone submenu -->
                <div
                    v-if="canEdit && milestoneOptions.length > 0"
                    class="relative"
                    @mouseenter="showSubmenu('milestone')"
                    @mouseleave="hideSubmenu">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        role="menuitem"
                        aria-haspopup="true"
                        :aria-expanded="activeSubmenu === 'milestone'">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                            </svg>
                            Move to Milestone
                        </span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <!-- Milestone submenu -->
                    <div
                        v-if="activeSubmenu === 'milestone'"
                        :class="[
                            'absolute bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[180px] max-h-[250px] overflow-y-auto',
                            submenuPosition.left ? 'left-full ml-1' : 'right-full mr-1',
                            submenuPosition.dropDown ? 'top-0' : 'bottom-0'
                        ]"
                        role="menu">
                        <button
                            v-for="opt in milestoneOptions.filter(m => m.value)"
                            :key="opt.value"
                            type="button"
                            @click="handleSetMilestone(opt.value)"
                            class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            role="menuitem">
                            <span class="truncate">{{ opt.label }}</span>
                            <svg v-if="!isMultiSelect && currentMilestone === opt.value" class="w-4 h-4 text-primary-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Separator -->
                <div v-if="canEdit && !isMultiSelect && (canAddSubtask || canDuplicate)" class="border-t border-gray-100 my-1"></div>

                <!-- Add Above (single task only) -->
                <button
                    v-if="canEdit && !isMultiSelect"
                    type="button"
                    @click="handleAddAbove"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    role="menuitem">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                    </svg>
                    Add Above
                </button>

                <!-- Add Below (single task only) -->
                <button
                    v-if="canEdit && !isMultiSelect"
                    type="button"
                    @click="handleAddBelow"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    role="menuitem">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                    Add Below
                </button>

                <!-- Add Subtask (single task only) -->
                <button
                    v-if="canEdit && !isMultiSelect && canAddSubtask"
                    type="button"
                    @click="handleAddSubtask"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    role="menuitem">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Subtask
                </button>

                <!-- Promote to Parent Level (for subtasks only) -->
                <button
                    v-if="canEdit && !isMultiSelect && canPromote"
                    type="button"
                    @click="handlePromote"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    role="menuitem">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6M6 12l4-4m-4 4l4 4"/>
                    </svg>
                    Promote to Parent Level
                </button>

                <!-- Make Subtask of Above (demote) -->
                <button
                    v-if="canEdit && !isMultiSelect && canDemote"
                    type="button"
                    @click="handleDemote"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    role="menuitem">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h12m0 0l-4-4m4 4l-4 4"/>
                    </svg>
                    Make Subtask of Above
                </button>

                <!-- Duplicate (single task only) -->
                <button
                    v-if="canEdit && !isMultiSelect && canDuplicate"
                    type="button"
                    @click="handleDuplicate"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    role="menuitem">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Duplicate
                </button>

                <!-- Separator -->
                <div v-if="canEdit" class="border-t border-gray-100 my-1"></div>

                <!-- Delete -->
                <button
                    v-if="canEdit"
                    type="button"
                    @click="handleDelete"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                    role="menuitem">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span v-if="isMultiSelect">Delete All</span>
                    <span v-else>Delete</span>
                </button>
            </div>
        </Teleport>
    `
};
