import { ref, computed, onMounted, nextTick, watch } from 'vue';

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
        },
        canEdit: {
            type: Boolean,
            default: true
        }
    },

    setup(props) {
        const items = ref([...props.initialItems]);
        const newItemTitle = ref('');
        const isLoading = ref(false);
        const editingItemId = ref(null);
        const editingTitle = ref('');
        const newItemInput = ref(null);

        // Drag and drop state
        const draggedItem = ref(null);
        const dragOverIndex = ref(null);

        const basePath = props.basePath || window.BASE_PATH || '';

        // Computed properties
        const totalCount = computed(() => items.value.length);
        const completedCount = computed(() => items.value.filter(item => item.isCompleted).length);
        const progressPercent = computed(() => {
            if (totalCount.value === 0) return 0;
            return (completedCount.value / totalCount.value) * 100;
        });

        // Update tab count in the DOM
        const updateTabCount = () => {
            const countEl = document.querySelector('.checklist-count');
            if (countEl) {
                countEl.textContent = `(${totalCount.value})`;
            }
        };

        // Watch for changes and update tab count
        watch(totalCount, updateTabCount);

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
                    // Refocus input for adding more items
                    nextTick(() => {
                        if (newItemInput.value) {
                            newItemInput.value.focus();
                        }
                    });
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
                    // Scroll into view after mobile keyboard appears
                    setTimeout(() => {
                        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 350);
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

        // Scroll input into view after mobile keyboard appears
        const scrollInputIntoView = () => {
            setTimeout(() => {
                newItemInput.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 350);
        };

        // Drag and drop handlers
        const handleDragStart = (event, item) => {
            draggedItem.value = item;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.id);
            // Add a slight delay to allow the drag image to be set
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
            // Only clear if leaving the checklist area entirely
            if (!event.currentTarget.contains(event.relatedTarget)) {
                dragOverIndex.value = null;
            }
        };

        const handleDrop = async (event, targetIndex) => {
            event.preventDefault();
            if (!draggedItem.value || isLoading.value) return;

            const draggedId = draggedItem.value.id;
            const currentIndex = items.value.findIndex(i => i.id === draggedId);

            if (currentIndex === -1 || currentIndex === targetIndex) {
                draggedItem.value = null;
                dragOverIndex.value = null;
                return;
            }

            // Reorder items locally
            const itemsCopy = [...items.value];
            const [removed] = itemsCopy.splice(currentIndex, 1);

            // Adjust target index if dragging from before to after
            let insertIndex = targetIndex;
            if (currentIndex < targetIndex) {
                insertIndex = targetIndex - 1;
            }
            itemsCopy.splice(insertIndex, 0, removed);
            items.value = itemsCopy;

            draggedItem.value = null;
            dragOverIndex.value = null;

            // Persist to server
            await saveOrder();
        };

        const saveOrder = async () => {
            const itemIds = items.value.map(item => item.id);

            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/checklists/reorder`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ itemIds })
                });

                if (response.ok) {
                    if (typeof Toastr !== 'undefined') {
                        Toastr.success('Checklist Reordered', 'Item order updated');
                    }
                } else {
                    console.error('Failed to save checklist order');
                    if (typeof Toastr !== 'undefined') {
                        Toastr.error('Reorder Failed', 'Could not save new order');
                    }
                }
            } catch (error) {
                console.error('Error saving checklist order:', error);
                if (typeof Toastr !== 'undefined') {
                    Toastr.error('Reorder Failed', 'Could not save new order');
                }
            }
        };

        return {
            items,
            newItemTitle,
            isLoading,
            editingItemId,
            editingTitle,
            newItemInput,
            totalCount,
            completedCount,
            progressPercent,
            canEdit: props.canEdit,
            addItem,
            toggleItem,
            startEditing,
            saveEdit,
            cancelEdit,
            onEditKeydown,
            deleteItem,
            onNewItemKeydown,
            scrollInputIntoView,
            // Drag and drop
            draggedItem,
            dragOverIndex,
            handleDragStart,
            handleDragEnd,
            handleDragOver,
            handleDragLeave,
            handleDrop
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

            <!-- Add Item Form (only if canEdit) -->
            <div v-if="canEdit" class="add-checklist-form mb-4">
                <div class="flex gap-2">
                    <input
                        ref="newItemInput"
                        type="text"
                        v-model="newItemTitle"
                        @keydown="onNewItemKeydown"
                        @focus="scrollInputIntoView"
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
                    v-for="(item, index) in items"
                    :key="item.id"
                    class="checklist-item group flex items-center gap-2 p-2 rounded-md hover:bg-gray-50 transition-all"
                    :class="{
                        'opacity-50': draggedItem && draggedItem.id === item.id,
                        'border-t-2 border-primary-500': dragOverIndex === index && draggedItem && draggedItem.id !== item.id
                    }"
                    :draggable="canEdit && editingItemId !== item.id"
                    @dragstart="canEdit && handleDragStart($event, item)"
                    @dragend="handleDragEnd"
                    @dragover="canEdit && handleDragOver($event, index)"
                    @dragleave="handleDragLeave"
                    @drop="canEdit && handleDrop($event, index)"
                >
                    <!-- Drag Handle (only if canEdit) -->
                    <div v-if="canEdit" class="cursor-grab text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity select-none">
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
                        :disabled="isLoading || !canEdit"
                    >

                    <!-- Title (View/Edit Mode) -->
                    <template v-if="canEdit && editingItemId === item.id">
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
                            @click="canEdit && startEditing(item)"
                            class="flex-1 text-sm"
                            :class="[item.isCompleted ? 'line-through text-gray-400' : 'text-gray-900', canEdit ? 'cursor-pointer' : '']"
                        >
                            {{ item.title }}
                        </span>
                    </template>

                    <!-- Delete Button (only if canEdit) -->
                    <button
                        v-if="canEdit"
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

                <!-- Drop zone at end of list -->
                <div
                    v-if="canEdit && items.length > 0 && draggedItem"
                    class="h-8 rounded-md transition-all"
                    :class="{ 'border-t-2 border-primary-500 bg-primary-50': dragOverIndex === items.length }"
                    @dragover="handleDragOver($event, items.length)"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop($event, items.length)"
                ></div>

                <!-- Empty State -->
                <p v-if="items.length === 0" class="text-sm text-gray-400 italic py-4">
                    No checklist items yet
                </p>
            </div>
        </div>
    `
};
