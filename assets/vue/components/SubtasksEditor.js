import { ref, computed } from 'vue';

export default {
    name: 'SubtasksEditor',

    props: {
        taskId: { type: String, required: true },
        initialSubtasks: { type: Array, default: () => [] },
        basePath: { type: String, default: '' },
        canEdit: { type: Boolean, default: true },
        maxDepthReached: { type: Boolean, default: false }
    },

    setup(props) {
        const subtasks = ref([...props.initialSubtasks]);
        const newTitle = ref('');
        const saving = ref(false);
        const error = ref('');
        const basePath = props.basePath || window.BASE_PATH || '';

        const completedCount = computed(() =>
            subtasks.value.filter(s => s.status?.value === 'completed').length
        );

        const addSubtask = async () => {
            const title = newTitle.value.trim();
            if (!title || saving.value) return;

            saving.value = true;
            error.value = '';

            try {
                const res = await fetch(`${basePath}/tasks/${props.taskId}/subtasks`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title })
                });
                const data = await res.json();
                if (!res.ok) {
                    error.value = data.error || 'Failed to create subtask';
                    return;
                }
                subtasks.value.push(data.subtask);
                newTitle.value = '';
            } catch (e) {
                error.value = 'Network error';
            } finally {
                saving.value = false;
            }
        };

        const handleKeydown = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addSubtask();
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

        return { subtasks, newTitle, saving, error, completedCount, addSubtask, handleKeydown, openSubtask, statusClass, basePath };
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

            <div v-if="canEdit && !maxDepthReached" class="mt-3">
                <div class="flex items-center gap-2">
                    <input type="text"
                           v-model="newTitle"
                           @keydown="handleKeydown"
                           placeholder="Add a subtask..."
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
                <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
            </div>
            <div v-if="maxDepthReached && canEdit" class="mt-3 text-xs text-gray-400 italic">Maximum nesting depth reached</div>
        </div>
    `
};
