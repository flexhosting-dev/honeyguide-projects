import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

/**
 * Modal for selecting a parent task when moving/reparenting subtasks
 *
 * Usage:
 *   <TaskPickerModal ref="taskPickerModal" />
 *
 *   // In setup:
 *   const taskPickerModal = ref(null);
 *   const selectedTask = await taskPickerModal.value.show({
 *       currentTaskId: 'task-uuid',
 *       milestoneId: 'milestone-uuid',
 *       basePath: '/app',
 *       title: 'Select New Parent'
 *   });
 */
export default {
    name: 'TaskPickerModal',

    setup(props, { expose }) {
        const isVisible = ref(false);
        const loading = ref(false);
        const searchQuery = ref('');
        const tasks = ref([]);
        const error = ref('');

        const options = ref({
            currentTaskId: null,
            milestoneId: null,
            projectId: null,
            basePath: '',
            title: 'Select Parent Task'
        });

        let resolvePromise = null;
        const searchInputRef = ref(null);

        // Filter tasks based on search query
        const filteredTasks = computed(() => {
            if (!searchQuery.value) return tasks.value;
            const q = searchQuery.value.toLowerCase();
            return tasks.value.filter(t =>
                t.title.toLowerCase().includes(q)
            );
        });

        // Load tasks from the same milestone
        const loadTasks = async () => {
            if (!options.value.milestoneId || !options.value.projectId) return;

            loading.value = true;
            error.value = '';

            try {
                // Fetch tasks for re-parenting (uses the dedicated endpoint)
                const url = `${options.value.basePath}/tasks/${options.value.currentTaskId}/eligible-parents`;
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    throw new Error('Failed to load tasks');
                }

                const data = await response.json();
                tasks.value = data.tasks || [];
            } catch (e) {
                console.error('Error loading tasks:', e);
                error.value = 'Failed to load tasks';
            } finally {
                loading.value = false;
            }
        };

        // Get all descendant IDs of a task
        const getDescendantIds = (allTasks, taskId) => {
            const descendants = new Set();
            const findDescendants = (parentId) => {
                allTasks.forEach(t => {
                    if (t.parentId === parentId) {
                        descendants.add(t.id);
                        findDescendants(t.id);
                    }
                });
            };
            findDescendants(taskId);
            return descendants;
        };

        // Show the modal and return a promise
        const show = async (opts = {}) => {
            options.value = {
                currentTaskId: opts.currentTaskId || null,
                milestoneId: opts.milestoneId || null,
                projectId: opts.projectId || null,
                basePath: opts.basePath || window.BASE_PATH || '',
                title: opts.title || 'Select Parent Task'
            };
            searchQuery.value = '';
            tasks.value = [];
            isVisible.value = true;

            // Load tasks
            await loadTasks();

            nextTick(() => {
                if (searchInputRef.value) {
                    searchInputRef.value.focus();
                }
            });

            return new Promise((resolve) => {
                resolvePromise = resolve;
            });
        };

        // Select a task
        const selectTask = (task) => {
            isVisible.value = false;
            if (resolvePromise) {
                resolvePromise(task);
                resolvePromise = null;
            }
        };

        // Cancel action
        const cancel = () => {
            isVisible.value = false;
            if (resolvePromise) {
                resolvePromise(null);
                resolvePromise = null;
            }
        };

        // Handle keyboard events
        const handleKeydown = (event) => {
            if (!isVisible.value) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                cancel();
            }
        };

        // Handle backdrop click
        const handleBackdropClick = (event) => {
            if (event.target === event.currentTarget) {
                cancel();
            }
        };

        // Get depth indicator
        const getDepthIndicator = (depth) => {
            if (!depth) return '';
            return '\u00A0'.repeat(depth * 4) + '└─ ';
        };

        onMounted(() => {
            document.addEventListener('keydown', handleKeydown);
        });

        onUnmounted(() => {
            document.removeEventListener('keydown', handleKeydown);
        });

        // Expose the show method for parent components
        expose({ show });

        return {
            isVisible,
            loading,
            searchQuery,
            filteredTasks,
            error,
            options,
            searchInputRef,
            selectTask,
            cancel,
            handleBackdropClick,
            getDepthIndicator
        };
    },

    template: `
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div
                    v-if="isVisible"
                    class="fixed inset-0 z-[10000] overflow-y-auto"
                    aria-labelledby="modal-title"
                    role="dialog"
                    aria-modal="true">
                    <!-- Backdrop -->
                    <div
                        class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0"
                        @click="handleBackdropClick">
                        <!-- Backdrop overlay -->
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                        <!-- Dialog panel -->
                        <Transition
                            enter-active-class="transition ease-out duration-200"
                            enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                            leave-active-class="transition ease-in duration-150"
                            leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                            leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                            <div
                                v-if="isVisible"
                                class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                                <div class="bg-white px-4 pb-4 pt-5 sm:p-6">
                                    <!-- Header -->
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900">{{ options.title }}</h3>
                                        <button type="button" @click="cancel" class="text-gray-400 hover:text-gray-500">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Search input -->
                                    <div class="mb-4">
                                        <input
                                            ref="searchInputRef"
                                            type="text"
                                            v-model="searchQuery"
                                            placeholder="Search tasks..."
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                                    </div>

                                    <!-- Task list -->
                                    <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-md">
                                        <!-- Loading -->
                                        <div v-if="loading" class="px-4 py-8 text-center text-gray-500">
                                            <svg class="animate-spin h-6 w-6 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Loading tasks...
                                        </div>

                                        <!-- Error -->
                                        <div v-else-if="error" class="px-4 py-8 text-center text-red-500">
                                            {{ error }}
                                        </div>

                                        <!-- Empty state -->
                                        <div v-else-if="filteredTasks.length === 0" class="px-4 py-8 text-center text-gray-500">
                                            <span v-if="searchQuery">No tasks match "{{ searchQuery }}"</span>
                                            <span v-else>No eligible parent tasks found</span>
                                        </div>

                                        <!-- Task list -->
                                        <div v-else class="divide-y divide-gray-100">
                                            <button
                                                v-for="task in filteredTasks"
                                                :key="task.id"
                                                type="button"
                                                @click="selectTask(task)"
                                                class="w-full text-left px-4 py-3 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                <div class="flex items-center">
                                                    <span class="text-gray-400 text-sm font-mono whitespace-pre">{{ getDepthIndicator(task.depth) }}</span>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-medium text-gray-900 truncate">{{ task.title }}</div>
                                                        <div class="text-xs text-gray-500 mt-0.5">
                                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
                                                                  :class="{
                                                                      'bg-green-100 text-green-700': task.status?.value === 'completed',
                                                                      'bg-blue-100 text-blue-700': task.status?.value === 'in_progress',
                                                                      'bg-yellow-100 text-yellow-700': task.status?.value === 'in_review',
                                                                      'bg-gray-100 text-gray-700': !task.status?.value || task.status?.value === 'todo'
                                                                  }">
                                                                {{ task.status?.label || 'To Do' }}
                                                            </span>
                                                            <span v-if="task.subtaskCount > 0" class="ml-2 text-gray-400">
                                                                {{ task.subtaskCount }} subtask{{ task.subtaskCount > 1 ? 's' : '' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <svg class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                    </svg>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="bg-gray-50 px-4 py-3 flex justify-end sm:px-6">
                                    <button
                                        type="button"
                                        @click="cancel"
                                        class="inline-flex justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </Transition>
        </Teleport>
    `
};
