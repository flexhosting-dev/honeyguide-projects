import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';

export default {
    name: 'TagsEditor',

    props: {
        taskId: {
            type: String,
            required: true
        },
        initialTags: {
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
        const tags = ref([...props.initialTags]);
        const searchQuery = ref('');
        const showInput = ref(false);
        const showDropdown = ref(false);
        const showColorPicker = ref(false);
        const selectedColor = ref('#3b82f6');
        const projectTags = ref([]);
        const isLoading = ref(false);
        const searchInputRef = ref(null);

        const basePath = props.basePath || window.BASE_PATH || '';

        // Preset colors
        const presetColors = [
            '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#14b8a6', '#06b6d4',
            '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e'
        ];

        const standardColors = [
            '#991b1b', '#9a3412', '#92400e', '#854d0e', '#3f6212', '#166534', '#115e59', '#155e75',
            '#075985', '#1e40af', '#3730a3', '#5b21b6', '#6b21a8', '#86198f', '#9d174d', '#6b7280'
        ];

        const neutralColors = [
            '#000000', '#374151', '#4b5563', '#6b7280', '#9ca3af', '#d1d5db', '#e5e7eb', '#f3f4f6'
        ];

        // Filtered tags for dropdown
        const filteredTags = computed(() => {
            if (!searchQuery.value) return projectTags.value;
            const query = searchQuery.value.toLowerCase();
            return projectTags.value.filter(tag =>
                tag.name.toLowerCase().includes(query)
            );
        });

        // Check if current search matches an existing tag
        const canCreateTag = computed(() => {
            if (!searchQuery.value.trim()) return false;
            const query = searchQuery.value.toLowerCase().trim();
            return !projectTags.value.some(tag => tag.name.toLowerCase() === query);
        });

        // Check if tag is already added to task
        const isTagAdded = (tagId) => {
            return tags.value.some(t => t.id === tagId);
        };

        // Fetch project tags
        const fetchProjectTags = async () => {
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/tags/available`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.ok) {
                    const data = await response.json();
                    projectTags.value = data.tags || [];
                }
            } catch (error) {
                console.error('Error fetching project tags:', error);
            }
        };

        // Add existing tag to task
        const addTag = async (tag) => {
            if (isTagAdded(tag.id)) return;

            isLoading.value = true;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/tags`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ tagId: tag.id })
                });

                if (response.ok) {
                    tags.value.push({ ...tag });
                    searchQuery.value = '';
                    showDropdown.value = false;
                    showInput.value = false;
                }
            } catch (error) {
                console.error('Error adding tag:', error);
            } finally {
                isLoading.value = false;
            }
        };

        // Create and add new tag
        const createTag = async () => {
            if (!canCreateTag.value) return;

            isLoading.value = true;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/tags`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        name: searchQuery.value.trim(),
                        color: selectedColor.value
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    tags.value.push(data.tag);
                    projectTags.value.push(data.tag);
                    searchQuery.value = '';
                    showDropdown.value = false;
                    showColorPicker.value = false;
                    showInput.value = false;
                }
            } catch (error) {
                console.error('Error creating tag:', error);
            } finally {
                isLoading.value = false;
            }
        };

        // Remove tag from task
        const removeTag = async (tagId) => {
            isLoading.value = true;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/tags/${tagId}`, {
                    method: 'DELETE',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.ok) {
                    tags.value = tags.value.filter(t => t.id !== tagId);
                }
            } catch (error) {
                console.error('Error removing tag:', error);
            } finally {
                isLoading.value = false;
            }
        };

        // Show the tag input
        const openTagInput = () => {
            showInput.value = true;
            nextTick(() => {
                if (searchInputRef.value) {
                    searchInputRef.value.focus();
                }
            });
        };

        // Handle input focus
        const onInputFocus = () => {
            showDropdown.value = true;
            fetchProjectTags();
        };

        // Handle input changes
        const onInputChange = () => {
            showDropdown.value = true;
            if (!projectTags.value.length) {
                fetchProjectTags();
            }
        };

        // Handle keyboard navigation
        const onKeydown = (event) => {
            if (event.key === 'Enter' && canCreateTag.value) {
                event.preventDefault();
                createTag();
            } else if (event.key === 'Escape') {
                showDropdown.value = false;
                showColorPicker.value = false;
                showInput.value = false;
                searchQuery.value = '';
            }
        };

        // Select color
        const selectColor = (color) => {
            selectedColor.value = color;
            showColorPicker.value = false;
        };

        // Close dropdown when clicking outside
        const handleClickOutside = (event) => {
            const container = document.getElementById(`tags-editor-${props.taskId}`);
            if (container && !container.contains(event.target)) {
                showDropdown.value = false;
                showColorPicker.value = false;
                showInput.value = false;
                searchQuery.value = '';
            }
        };

        onMounted(() => {
            document.addEventListener('click', handleClickOutside);
        });

        onUnmounted(() => {
            document.removeEventListener('click', handleClickOutside);
        });

        return {
            tags,
            searchQuery,
            showInput,
            showDropdown,
            showColorPicker,
            selectedColor,
            projectTags,
            filteredTags,
            canCreateTag,
            isLoading,
            presetColors,
            standardColors,
            neutralColors,
            canEdit: props.canEdit,
            searchInputRef,
            isTagAdded,
            addTag,
            createTag,
            removeTag,
            openTagInput,
            onInputFocus,
            onInputChange,
            onKeydown,
            selectColor
        };
    },

    template: `
        <div :id="'tags-editor-' + taskId" class="tags-container">
            <!-- Tags List -->
            <div class="tags-list flex flex-wrap gap-1.5">
                <span
                    v-for="tag in tags"
                    :key="tag.id"
                    class="tag-chip relative inline-flex items-center text-xs font-medium text-white pl-2 py-0.5"
                    :class="canEdit ? 'group pr-5' : 'pr-2'"
                    :style="{ backgroundColor: tag.color, '--tag-color': tag.color }"
                >
                    {{ tag.name }}
                    <button
                        v-if="canEdit"
                        type="button"
                        @click="removeTag(tag.id)"
                        class="absolute right-0.5 top-1/2 -translate-y-1/2 h-4 w-4 items-center justify-center rounded-full hover:bg-black/20 hidden group-hover:inline-flex"
                        :disabled="isLoading"
                    >
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>

                <!-- Add Tag Button (inline with tags) -->
                <span
                    v-if="canEdit && !showInput"
                    @click="openTagInput"
                    class="tag-chip-add inline-flex items-center text-xs font-medium text-gray-500 pl-1.5 pr-2 cursor-pointer hover:text-gray-600 hover:border-gray-400"
                >
                    <svg class="h-3 w-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add
                </span>
            </div>

            <!-- Add Tag Input (shown on click) -->
            <div v-if="canEdit && showInput" class="add-tag-container relative mt-2">
                <input
                    ref="searchInputRef"
                    type="text"
                    v-model="searchQuery"
                    @focus="onInputFocus"
                    @input="onInputChange"
                    @keydown="onKeydown"
                    class="tag-search-input w-full text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Add a tag..."
                    :disabled="isLoading"
                >

                <!-- Dropdown -->
                <div
                    v-show="showDropdown"
                    class="absolute left-0 right-0 mt-1 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-20 p-3"
                >
                    <!-- Create New Tag Section -->
                    <div v-if="canCreateTag" class="create-tag-section mb-3 ml-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">Create</span>
                            <span
                                class="tag-chip inline-flex items-center text-xs font-medium text-white pl-2 pr-2 py-0.5"
                                :style="{ backgroundColor: selectedColor, '--tag-color': selectedColor }"
                            >
                                {{ searchQuery }}
                            </span>
                            <button
                                type="button"
                                @click="showColorPicker = !showColorPicker"
                                class="color-picker-btn p-1 rounded hover:bg-gray-100"
                                title="Choose color"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" stroke-dasharray="15.7 15.7" style="stroke: #ef4444;" stroke-dashoffset="0"/>
                                    <circle cx="12" cy="12" r="10" stroke-dasharray="15.7 15.7" style="stroke: #22c55e;" stroke-dashoffset="-15.7"/>
                                    <circle cx="12" cy="12" r="10" stroke-dasharray="15.7 15.7" style="stroke: #3b82f6;" stroke-dashoffset="-31.4"/>
                                    <circle cx="12" cy="12" r="10" stroke-dasharray="15.7 15.7" style="stroke: #eab308;" stroke-dashoffset="-47.1"/>
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="createTag"
                                class="p-1 rounded-full bg-green-500 hover:bg-green-600 text-white"
                                title="Create tag"
                                :disabled="isLoading"
                            >
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </button>
                        </div>

                        <!-- Color Picker Popup -->
                        <div
                            v-if="showColorPicker"
                            class="absolute left-0 mt-2 bg-white rounded-lg shadow-xl ring-1 ring-black ring-opacity-5 z-30 p-3 w-56"
                        >
                            <div class="mb-3">
                                <div class="text-xs font-medium text-gray-700 mb-2">Preset Colors</div>
                                <div class="grid grid-cols-8 gap-1">
                                    <button
                                        v-for="color in presetColors"
                                        :key="color"
                                        type="button"
                                        @click="selectColor(color)"
                                        class="color-swatch w-5 h-5 rounded"
                                        :style="{ backgroundColor: color }"
                                        :class="{ 'ring-2 ring-offset-1 ring-gray-400': selectedColor === color }"
                                    ></button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="text-xs font-medium text-gray-700 mb-2">Standard Colors</div>
                                <div class="grid grid-cols-8 gap-1">
                                    <button
                                        v-for="color in standardColors"
                                        :key="color"
                                        type="button"
                                        @click="selectColor(color)"
                                        class="color-swatch w-5 h-5 rounded"
                                        :style="{ backgroundColor: color }"
                                        :class="{ 'ring-2 ring-offset-1 ring-gray-400': selectedColor === color }"
                                    ></button>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-gray-700 mb-2">Neutrals</div>
                                <div class="grid grid-cols-8 gap-1">
                                    <button
                                        v-for="color in neutralColors"
                                        :key="color"
                                        type="button"
                                        @click="selectColor(color)"
                                        class="color-swatch w-5 h-5 rounded"
                                        :style="{ backgroundColor: color }"
                                        :class="{ 'ring-2 ring-offset-1 ring-gray-400': selectedColor === color, 'border border-gray-300': ['#d1d5db', '#e5e7eb', '#f3f4f6'].includes(color) }"
                                    ></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Tags Grid -->
                    <div class="existing-tags-grid flex flex-wrap gap-1.5 max-h-40 overflow-y-auto ml-2">
                        <button
                            v-for="tag in filteredTags"
                            :key="tag.id"
                            type="button"
                            @click="addTag(tag)"
                            class="tag-chip inline-flex items-center text-xs font-medium text-white pl-2 pr-2 py-0.5 cursor-pointer hover:opacity-80"
                            :style="{ backgroundColor: tag.color, '--tag-color': tag.color }"
                            :class="{ 'opacity-50 cursor-not-allowed': isTagAdded(tag.id) }"
                            :disabled="isTagAdded(tag.id) || isLoading"
                        >
                            {{ tag.name }}
                            <svg v-if="isTagAdded(tag.id)" class="h-3 w-3 ml-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </button>
                        <span v-if="filteredTags.length === 0 && !canCreateTag" class="text-sm text-gray-500 py-2">
                            No tags found
                        </span>
                    </div>
                </div>
            </div>

            <!-- Empty State (only for read-only) -->
            <p v-if="tags.length === 0 && !canEdit" class="no-tags-msg text-sm text-gray-400 italic mt-2">No tags</p>
        </div>
    `
};
