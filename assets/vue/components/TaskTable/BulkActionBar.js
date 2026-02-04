import { ref, computed, onMounted, onUnmounted } from 'vue';

export default {
    name: 'BulkActionBar',

    props: {
        selectedCount: {
            type: Number,
            default: 0
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
        isUpdating: {
            type: Boolean,
            default: false
        }
    },

    emits: ['clear-selection', 'bulk-update', 'bulk-delete'],

    setup(props, { emit }) {
        const showStatusDropdown = ref(false);
        const showPriorityDropdown = ref(false);
        const showMilestoneDropdown = ref(false);
        const showDeleteConfirm = ref(false);

        const closeAllDropdowns = () => {
            showStatusDropdown.value = false;
            showPriorityDropdown.value = false;
            showMilestoneDropdown.value = false;
        };

        const toggleStatusDropdown = () => {
            const isOpen = !showStatusDropdown.value;
            closeAllDropdowns();
            showStatusDropdown.value = isOpen;
        };

        const togglePriorityDropdown = () => {
            const isOpen = !showPriorityDropdown.value;
            closeAllDropdowns();
            showPriorityDropdown.value = isOpen;
        };

        const toggleMilestoneDropdown = () => {
            const isOpen = !showMilestoneDropdown.value;
            closeAllDropdowns();
            showMilestoneDropdown.value = isOpen;
        };

        const handleStatusChange = (status) => {
            emit('bulk-update', { status });
            closeAllDropdowns();
        };

        const handlePriorityChange = (priority) => {
            emit('bulk-update', { priority });
            closeAllDropdowns();
        };

        const handleMilestoneChange = (milestone) => {
            emit('bulk-update', { milestone });
            closeAllDropdowns();
        };

        const handleDelete = () => {
            showDeleteConfirm.value = true;
        };

        const confirmDelete = () => {
            emit('bulk-delete');
            showDeleteConfirm.value = false;
        };

        const cancelDelete = () => {
            showDeleteConfirm.value = false;
        };

        const clearSelection = () => {
            emit('clear-selection');
        };

        // Close dropdowns on Escape key or click outside
        const handleKeydown = (event) => {
            if (event.key === 'Escape') {
                closeAllDropdowns();
                showDeleteConfirm.value = false;
            }
        };

        const handleClickOutside = (event) => {
            const bar = event.target.closest('.bulk-action-bar');
            if (!bar) {
                closeAllDropdowns();
            }
        };

        onMounted(() => {
            document.addEventListener('keydown', handleKeydown);
            document.addEventListener('click', handleClickOutside);
        });

        onUnmounted(() => {
            document.removeEventListener('keydown', handleKeydown);
            document.removeEventListener('click', handleClickOutside);
        });

        return {
            showStatusDropdown,
            showPriorityDropdown,
            showMilestoneDropdown,
            showDeleteConfirm,
            toggleStatusDropdown,
            togglePriorityDropdown,
            toggleMilestoneDropdown,
            handleStatusChange,
            handlePriorityChange,
            handleMilestoneChange,
            handleDelete,
            confirmDelete,
            cancelDelete,
            clearSelection
        };
    },

    template: `
        <div class="bulk-action-bar fixed bottom-6 left-1/2 -translate-x-1/2 z-50" role="toolbar" aria-label="Bulk actions">
            <div class="flex items-center gap-3 px-4 py-3 bg-gray-900 text-white rounded-lg shadow-xl">
                <!-- Selection count -->
                <div class="flex items-center gap-2" aria-live="polite">
                    <span class="text-sm font-medium">{{ selectedCount }} selected</span>
                    <button
                        type="button"
                        @click="clearSelection"
                        aria-label="Clear selection"
                        class="text-gray-400 hover:text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="w-px h-6 bg-gray-700"></div>

                <!-- Loading indicator -->
                <svg v-if="isUpdating" class="animate-spin h-5 w-5 text-primary-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>

                <template v-if="!isUpdating">
                    <!-- Status dropdown -->
                    <div class="relative">
                        <button
                            type="button"
                            @click="toggleStatusDropdown"
                            :aria-expanded="showStatusDropdown"
                            aria-haspopup="listbox"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md hover:bg-gray-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Status
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div
                            v-show="showStatusDropdown"
                            role="listbox"
                            aria-label="Select status"
                            class="absolute bottom-full left-0 mb-2 w-40 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 py-1">
                            <button
                                v-for="opt in statusOptions"
                                :key="opt.value"
                                type="button"
                                role="option"
                                @click="handleStatusChange(opt.value)"
                                class="w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100">
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Priority dropdown -->
                    <div class="relative">
                        <button
                            type="button"
                            @click="togglePriorityDropdown"
                            :aria-expanded="showPriorityDropdown"
                            aria-haspopup="listbox"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md hover:bg-gray-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                            </svg>
                            Priority
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div
                            v-show="showPriorityDropdown"
                            role="listbox"
                            aria-label="Select priority"
                            class="absolute bottom-full left-0 mb-2 w-40 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 py-1">
                            <button
                                v-for="opt in priorityOptions"
                                :key="opt.value"
                                type="button"
                                role="option"
                                @click="handlePriorityChange(opt.value)"
                                class="w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100">
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Milestone dropdown -->
                    <div v-if="milestoneOptions.length > 1" class="relative">
                        <button
                            type="button"
                            @click="toggleMilestoneDropdown"
                            :aria-expanded="showMilestoneDropdown"
                            aria-haspopup="listbox"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md hover:bg-gray-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5"/>
                            </svg>
                            Milestone
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div
                            v-show="showMilestoneDropdown"
                            role="listbox"
                            aria-label="Select milestone"
                            class="absolute bottom-full left-0 mb-2 w-48 max-h-48 overflow-y-auto bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 py-1">
                            <button
                                v-for="opt in milestoneOptions.slice(1)"
                                :key="opt.value"
                                type="button"
                                role="option"
                                @click="handleMilestoneChange(opt.value)"
                                class="w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100 truncate">
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <div class="w-px h-6 bg-gray-700"></div>

                    <!-- Delete button -->
                    <div class="relative">
                        <button
                            v-if="!showDeleteConfirm"
                            type="button"
                            @click="handleDelete"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-red-400 rounded-md hover:bg-red-900/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete
                        </button>

                        <!-- Delete confirmation -->
                        <div v-else class="flex items-center gap-2">
                            <span class="text-sm text-red-400">Delete {{ selectedCount }} tasks?</span>
                            <button
                                type="button"
                                @click="confirmDelete"
                                class="px-3 py-1 text-sm bg-red-600 text-white rounded-md hover:bg-red-700">
                                Yes
                            </button>
                            <button
                                type="button"
                                @click="cancelDelete"
                                class="px-3 py-1 text-sm text-gray-400 hover:text-white">
                                No
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    `
};
