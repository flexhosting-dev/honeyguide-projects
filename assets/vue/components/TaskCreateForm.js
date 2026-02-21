import { ref, computed, onMounted, nextTick } from 'vue';

export default {
    name: 'TaskCreateForm',

    props: {
        projectId: {
            type: String,
            required: true
        },
        milestones: {
            type: Array,
            default: () => []
        },
        defaultMilestoneId: {
            type: String,
            default: ''
        },
        basePath: {
            type: String,
            default: ''
        },
        autoAssignToMe: {
            type: Boolean,
            default: false
        },
        currentUserId: {
            type: String,
            default: ''
        }
    },

    setup(props) {
        const basePath = props.basePath || window.BASE_PATH || '';

        // Form state
        const title = ref('');
        const milestoneId = ref(props.defaultMilestoneId || (props.milestones.length > 0 ? props.milestones[0].id : ''));
        const status = ref('todo');
        const priority = ref('none');
        const dueDate = ref('');
        const description = ref('');

        // UI state
        const isSubmitting = ref(false);
        const errors = ref({});
        const titleInput = ref(null);

        // Options
        const statusOptions = [
            { value: 'todo', label: 'To Do' },
            { value: 'in_progress', label: 'In Progress' },
            { value: 'in_review', label: 'In Review' },
            { value: 'completed', label: 'Completed' }
        ];

        const priorityOptions = [
            { value: 'none', label: 'None' },
            { value: 'low', label: 'Low' },
            { value: 'medium', label: 'Medium' },
            { value: 'high', label: 'High' }
        ];

        // Computed
        const canSubmit = computed(() => {
            return title.value.trim() && milestoneId.value && !isSubmitting.value;
        });

        // Methods
        const validateForm = () => {
            errors.value = {};

            if (!title.value.trim()) {
                errors.value.title = 'Title is required';
            }

            if (!milestoneId.value) {
                errors.value.milestone = 'Milestone is required';
            }

            return Object.keys(errors.value).length === 0;
        };

        const submitForm = async () => {
            if (!validateForm() || isSubmitting.value) return;

            isSubmitting.value = true;
            errors.value = {};

            try {
                const response = await fetch(`${basePath}/projects/${props.projectId}/tasks/json`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        title: title.value.trim(),
                        milestone: milestoneId.value,
                        status: status.value,
                        priority: priority.value,
                        dueDate: dueDate.value || null,
                        description: description.value.trim() || null,
                        assignees: props.autoAssignToMe && props.currentUserId ? [props.currentUserId] : [],
                        autoAssignedToMe: props.autoAssignToMe && props.currentUserId
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to create task');
                }

                // Success - dispatch event for KanbanBoard
                document.dispatchEvent(new CustomEvent('task-created', {
                    detail: { task: data.task }
                }));

                // Show toast
                if (typeof Toastr !== 'undefined') {
                    if (data.autoAssignedToMe) {
                        Toastr.success('Task created and assigned to you');
                    } else {
                        Toastr.success('Task created successfully');
                    }
                }

                // Open the new task panel
                if (typeof window.openTaskPanel === 'function') {
                    window.openTaskPanel(data.task.id);
                }

            } catch (error) {
                console.error('Error creating task:', error);
                errors.value.form = error.message || 'Failed to create task';
                if (typeof Toastr !== 'undefined') {
                    Toastr.error(error.message || 'Failed to create task');
                }
            } finally {
                isSubmitting.value = false;
            }
        };

        const handleKeydown = (event) => {
            // Cmd/Ctrl + Enter to submit
            if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
                event.preventDefault();
                submitForm();
            }
        };

        // Focus title input on mount
        onMounted(() => {
            nextTick(() => {
                if (titleInput.value) {
                    titleInput.value.focus();
                }
            });
        });

        return {
            // Form state
            title,
            milestoneId,
            status,
            priority,
            dueDate,
            description,

            // UI state
            isSubmitting,
            errors,
            titleInput,

            // Options
            statusOptions,
            priorityOptions,

            // Computed
            canSubmit,

            // Methods
            submitForm,
            handleKeydown
        };
    },

    template: `
        <form @submit.prevent="submitForm" @keydown="handleKeydown" class="space-y-6">
            <!-- Form Error -->
            <div v-if="errors.form" class="rounded-md bg-red-50 p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800">{{ errors.form }}</p>
                    </div>
                </div>
            </div>

            <!-- Milestone (Required) -->
            <div>
                <label for="task-milestone" class="block text-sm font-medium text-gray-900">
                    Milestone <span class="text-red-500">*</span>
                </label>
                <div class="mt-2">
                    <select
                        id="task-milestone"
                        v-model="milestoneId"
                        required
                        class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm"
                        :class="{ 'ring-red-500': errors.milestone }"
                        :disabled="isSubmitting"
                    >
                        <option value="" disabled>Select a milestone...</option>
                        <option v-for="m in milestones" :key="m.id" :value="m.id">
                            {{ m.name }}
                        </option>
                    </select>
                </div>
                <p v-if="errors.milestone" class="mt-1 text-sm text-red-600">{{ errors.milestone }}</p>
            </div>

            <!-- Title (Required) -->
            <div>
                <label for="task-title" class="block text-sm font-medium text-gray-900">
                    Title <span class="text-red-500">*</span>
                </label>
                <div class="mt-2">
                    <input
                        ref="titleInput"
                        id="task-title"
                        v-model="title"
                        type="text"
                        required
                        class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm"
                        :class="{ 'ring-red-500': errors.title }"
                        placeholder="Enter task title..."
                        :disabled="isSubmitting"
                    >
                </div>
                <p v-if="errors.title" class="mt-1 text-sm text-red-600">{{ errors.title }}</p>
            </div>

            <!-- Description -->
            <div>
                <label for="task-description" class="block text-sm font-medium text-gray-900">Description</label>
                <div class="mt-2">
                    <textarea
                        id="task-description"
                        v-model="description"
                        rows="3"
                        class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm"
                        placeholder="Add a description..."
                        :disabled="isSubmitting"
                    ></textarea>
                </div>
            </div>

            <!-- Status & Priority (Row) -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="task-status" class="block text-sm font-medium text-gray-900">Status</label>
                    <div class="mt-2">
                        <select
                            id="task-status"
                            v-model="status"
                            class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm"
                            :disabled="isSubmitting"
                        >
                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="task-priority" class="block text-sm font-medium text-gray-900">Priority</label>
                    <div class="mt-2">
                        <select
                            id="task-priority"
                            v-model="priority"
                            class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm"
                            :disabled="isSubmitting"
                        >
                            <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Due Date -->
            <div>
                <label for="task-due-date" class="block text-sm font-medium text-gray-900">Due Date</label>
                <div class="mt-2">
                    <input
                        id="task-due-date"
                        v-model="dueDate"
                        type="date"
                        class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm"
                        :disabled="isSubmitting"
                    >
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500">
                    <kbd class="px-1.5 py-0.5 text-xs font-semibold text-gray-800 bg-gray-100 border border-gray-200 rounded">Cmd</kbd>
                    +
                    <kbd class="px-1.5 py-0.5 text-xs font-semibold text-gray-800 bg-gray-100 border border-gray-200 rounded">Enter</kbd>
                    to submit
                </p>
                <button
                    type="submit"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!canSubmit"
                >
                    <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="-ml-0.5 mr-1.5 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                    </svg>
                    {{ isSubmitting ? 'Creating...' : 'Create Task' }}
                </button>
            </div>
        </form>
    `
};
