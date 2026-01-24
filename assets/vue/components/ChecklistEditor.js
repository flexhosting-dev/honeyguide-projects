import { ref, computed, onMounted, nextTick } from 'vue';

export default {
    name: 'ChecklistEditor',

    props: {
        taskId: {
            type: String,
            required: true
        },
        initialItems: {
            type: Array,
            default: () => []
        },
        basePath: {
            type: String,
            default: ''
        }
    },

    setup(props) {
        const items = ref([...props.initialItems]);
        const newItemTitle = ref('');
        const isLoading = ref(false);
        const editingItemId = ref(null);
        const editingTitle = ref('');

        const basePath = props.basePath || window.BASE_PATH || '';

        // Computed properties
        const totalCount = computed(() => items.value.length);
        const completedCount = computed(() => items.value.filter(item => item.isCompleted).length);
        const progressPercent = computed(() => {
            if (totalCount.value === 0) return 0;
            return (completedCount.value / totalCount.value) * 100;
        });

        // Add new item
        const addItem = async () => {
            const title = newItemTitle.value.trim();
            if (!title || isLoading.value) return;

            isLoading.value = true;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/checklists`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ title })
                });

                if (response.ok) {
                    const data = await response.json();
                    items.value.push(data.item);
                    newItemTitle.value = '';
                }
            } catch (error) {
                console.error('Error adding checklist item:', error);
            } finally {
                isLoading.value = false;
            }
        };

        // Toggle item completion
        const toggleItem = async (item) => {
            if (isLoading.value) return;

            // Optimistic update
            item.isCompleted = !item.isCompleted;

            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/checklists/${item.id}/toggle`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    // Revert on failure
                    item.isCompleted = !item.isCompleted;
                }
            } catch (error) {
                // Revert on error
                item.isCompleted = !item.isCompleted;
                console.error('Error toggling checklist item:', error);
            }
        };

        // Start editing item
        const startEditing = (item) => {
            editingItemId.value = item.id;
            editingTitle.value = item.title;
            nextTick(() => {
                const input = document.querySelector(`#edit-input-${item.id}`);
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        };

        // Save edited item
        const saveEdit = async (item) => {
            const title = editingTitle.value.trim();
            if (!title) {
                cancelEdit();
                return;
            }

            if (title === item.title) {
                cancelEdit();
                return;
            }

            isLoading.value = true;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/checklists/${item.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ title })
                });

                if (response.ok) {
                    item.title = title;
                }
            } catch (error) {
                console.error('Error updating checklist item:', error);
            } finally {
                isLoading.value = false;
                cancelEdit();
            }
        };

        // Cancel editing
        const cancelEdit = () => {
            editingItemId.value = null;
            editingTitle.value = '';
        };

        // Handle edit keydown
        const onEditKeydown = (event, item) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveEdit(item);
            } else if (event.key === 'Escape') {
                cancelEdit();
            }
        };

        // Delete item
        const deleteItem = async (item) => {
            if (isLoading.value) return;

            isLoading.value = true;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/checklists/${item.id}`, {
                    method: 'DELETE',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.ok) {
                    items.value = items.value.filter(i => i.id !== item.id);
                }
            } catch (error) {
                console.error('Error deleting checklist item:', error);
            } finally {
                isLoading.value = false;
            }
        };

        // Handle new item keydown
        const onNewItemKeydown = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addItem();
            }
        };

        return {
            items,
            newItemTitle,
            isLoading,
            editingItemId,
            editingTitle,
            totalCount,
            completedCount,
            progressPercent,
            addItem,
            toggleItem,
            startEditing,
            saveEdit,
            cancelEdit,
            onEditKeydown,
            deleteItem,
            onNewItemKeydown
        };
    },

    template: `
        <div class="checklist-container">
            <!-- Progress Bar -->
            <div v-if="totalCount > 0" class="checklist-progress mb-4">
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-500">Progress</span>
                    <span class="font-medium">{{ completedCount }}/{{ totalCount }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div
                        class="bg-primary-600 h-2 rounded-full transition-all"
                        :style="{ width: progressPercent + '%' }"
                    ></div>
                </div>
            </div>

            <!-- Add Item Form -->
            <div class="add-checklist-form mb-4">
                <div class="flex gap-2">
                    <input
                        type="text"
                        v-model="newItemTitle"
                        @keydown="onNewItemKeydown"
                        class="flex-1 text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="Add an item..."
                        :disabled="isLoading"
                    >
                    <button
                        type="button"
                        @click="addItem"
                        class="px-3 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-500 disabled:opacity-50"
                        :disabled="isLoading || !newItemTitle.trim()"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Checklist Items -->
            <div class="checklist-items space-y-1">
                <div
                    v-for="item in items"
                    :key="item.id"
                    class="checklist-item group flex items-center gap-2 p-2 rounded-md hover:bg-gray-50"
                >
                    <!-- Drag Handle -->
                    <div class="cursor-grab text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </div>

                    <!-- Checkbox -->
                    <input
                        type="checkbox"
                        :checked="item.isCompleted"
                        @change="toggleItem(item)"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        :disabled="isLoading"
                    >

                    <!-- Title (View/Edit Mode) -->
                    <template v-if="editingItemId === item.id">
                        <input
                            :id="'edit-input-' + item.id"
                            type="text"
                            v-model="editingTitle"
                            @keydown="onEditKeydown($event, item)"
                            @blur="saveEdit(item)"
                            class="flex-1 text-sm border border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        >
                    </template>
                    <template v-else>
                        <span
                            @click="startEditing(item)"
                            class="flex-1 text-sm cursor-pointer"
                            :class="item.isCompleted ? 'line-through text-gray-400' : 'text-gray-900'"
                        >
                            {{ item.title }}
                        </span>
                    </template>

                    <!-- Delete Button -->
                    <button
                        type="button"
                        @click="deleteItem(item)"
                        class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-red-600"
                        :disabled="isLoading"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </div>

                <!-- Empty State -->
                <p v-if="items.length === 0" class="text-sm text-gray-400 italic py-4">
                    No checklist items yet
                </p>
            </div>
        </div>
    `
};
