import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';

export default {
    name: 'RichTextEditor',

    props: {
        taskId: { type: String, default: '' },
        entityType: { type: String, default: 'task' },
        entityId: { type: String, default: '' },
        saveEndpoint: { type: String, default: '' },
        initialContent: { type: String, default: '' },
        basePath: { type: String, default: '' },
        canEdit: { type: Boolean, default: true },
        initialAttachments: { type: Array, default: () => [] },
        showAttachments: { type: Boolean, default: true },
    },

    setup(props) {
        const content = ref(props.initialContent || '');
        const isEditing = ref(false);
        const isSaving = ref(false);
        const editorRef = ref(null);
        const attachments = ref([...props.initialAttachments]);
        const isUploading = ref(false);
        const isDragOver = ref(false);

        const basePath = props.basePath || window.BASE_PATH || '';

        // Load attachments from API on mount if taskId is present
        const loadAttachments = async () => {
            if (!props.taskId || !props.showAttachments) return;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/attachments`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (response.ok) {
                    const data = await response.json();
                    attachments.value = data.attachments || [];
                }
            } catch (error) {
                console.error('Error loading attachments:', error);
            }
        };

        onMounted(() => {
            if (props.showAttachments && props.taskId) {
                loadAttachments();
            }
        });

        const hasContent = computed(() => {
            const text = content.value.replace(/<[^>]*>/g, '').trim();
            return text.length > 0;
        });

        const startEditing = () => {
            if (!props.canEdit) return;
            isEditing.value = true;
            nextTick(() => {
                if (editorRef.value) {
                    editorRef.value.innerHTML = content.value;
                    editorRef.value.focus();
                }
            });
        };

        const cancelEditing = () => {
            isEditing.value = false;
            if (editorRef.value) {
                editorRef.value.innerHTML = content.value;
            }
        };

        const saveDescription = async () => {
            if (isSaving.value) return;
            isSaving.value = true;

            const newContent = editorRef.value ? editorRef.value.innerHTML : '';
            const endpoint = props.saveEndpoint || `${basePath}/tasks/${props.taskId}/description`;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ description: newContent }),
                });

                if (response.ok) {
                    content.value = newContent;
                    isEditing.value = false;
                }
            } catch (error) {
                console.error('Error saving description:', error);
            } finally {
                isSaving.value = false;
            }
        };

        const execCommand = (command, value = null) => {
            document.execCommand(command, false, value);
            if (editorRef.value) {
                editorRef.value.focus();
            }
        };

        const formatBlock = (tag) => {
            document.execCommand('formatBlock', false, tag);
            if (editorRef.value) {
                editorRef.value.focus();
            }
        };

        const insertLink = () => {
            const url = prompt('Enter URL:');
            if (url) {
                document.execCommand('createLink', false, url);
            }
        };

        // Attachment handling
        const uploadFiles = async (files) => {
            if (!files || files.length === 0) return;

            isUploading.value = true;
            const formData = new FormData();
            for (const file of files) {
                formData.append('files[]', file);
            }

            const uploadUrl = props.taskId
                ? `${basePath}/tasks/${props.taskId}/attachments`
                : `${basePath}/${props.entityType}s/${props.entityId}/attachments`;

            try {
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });

                if (response.ok) {
                    const data = await response.json();
                    attachments.value.push(...data.attachments);
                    updateAttachmentCount();
                } else {
                    const data = await response.json();
                    alert(data.error || 'Upload failed');
                }
            } catch (error) {
                console.error('Error uploading files:', error);
            } finally {
                isUploading.value = false;
            }
        };

        const deleteAttachment = async (attachment) => {
            if (!confirm('Delete this attachment?')) return;

            try {
                const response = await fetch(`${basePath}/attachments/${attachment.id}`, {
                    method: 'DELETE',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (response.ok) {
                    attachments.value = attachments.value.filter(a => a.id !== attachment.id);
                    updateAttachmentCount();
                }
            } catch (error) {
                console.error('Error deleting attachment:', error);
            }
        };

        const onFileSelect = (event) => {
            uploadFiles(event.target.files);
            event.target.value = '';
        };

        const onDragOver = (event) => {
            event.preventDefault();
            isDragOver.value = true;
        };

        const onDragLeave = () => {
            isDragOver.value = false;
        };

        const onDrop = (event) => {
            event.preventDefault();
            isDragOver.value = false;
            uploadFiles(event.dataTransfer.files);
        };

        const updateAttachmentCount = () => {
            const countEl = document.querySelector('.attachments-count');
            if (countEl) {
                countEl.textContent = attachments.value.length > 0 ? `(${attachments.value.length})` : '';
            }
        };

        const getFileIcon = (mimeType) => {
            if (mimeType.startsWith('image/')) return 'photo';
            if (mimeType === 'application/pdf') return 'pdf';
            if (mimeType.includes('spreadsheet') || mimeType.includes('excel') || mimeType === 'text/csv') return 'spreadsheet';
            if (mimeType.includes('document') || mimeType.includes('word')) return 'document';
            if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('7z')) return 'archive';
            return 'file';
        };

        return {
            content,
            isEditing,
            isSaving,
            editorRef,
            attachments,
            isUploading,
            isDragOver,
            hasContent,
            canEdit: props.canEdit,
            showAttachments: props.showAttachments,
            startEditing,
            cancelEditing,
            saveDescription,
            execCommand,
            formatBlock,
            insertLink,
            uploadFiles,
            deleteAttachment,
            onFileSelect,
            onDragOver,
            onDragLeave,
            onDrop,
            getFileIcon,
        };
    },

    template: `
        <div class="rich-text-editor">
            <!-- Display Mode -->
            <div v-if="!isEditing">
                <div
                    v-if="canEdit"
                    @click="startEditing"
                    class="prose prose-sm max-w-none text-sm text-gray-600 rounded p-2 -m-2 hover:bg-gray-50 cursor-pointer min-h-[60px]"
                    :class="{ 'text-gray-400 italic': !hasContent }"
                >
                    <div v-if="hasContent" v-html="content"></div>
                    <span v-else>Click to add description...</span>
                </div>
                <div v-else class="prose prose-sm max-w-none text-sm text-gray-600">
                    <div v-if="hasContent" v-html="content"></div>
                    <span v-else class="text-gray-400 italic">No description</span>
                </div>
            </div>

            <!-- Edit Mode -->
            <div v-if="isEditing" class="border border-gray-300 rounded-lg overflow-hidden">
                <!-- Toolbar -->
                <div class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 bg-gray-50 border-b border-gray-200">
                    <button type="button" @click="execCommand('bold')" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Bold (Ctrl+B)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4h8a4 4 0 0 1 0 8H6zM6 12h9a4 4 0 0 1 0 8H6z"/></svg>
                    </button>
                    <button type="button" @click="execCommand('italic')" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Italic (Ctrl+I)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10 4h4m-2 0v16m-4 0h8"/></svg>
                    </button>
                    <button type="button" @click="execCommand('underline')" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Underline (Ctrl+U)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4v7a5 5 0 0 0 10 0V4M5 20h14"/></svg>
                    </button>
                    <button type="button" @click="execCommand('strikeThrough')" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Strikethrough">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16 4H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H8M4 12h16"/></svg>
                    </button>

                    <span class="w-px h-5 bg-gray-300 mx-1"></span>

                    <select @change="formatBlock($event.target.value); $event.target.value = ''" class="text-xs border-0 bg-transparent text-gray-600 py-1 pr-6 pl-1 focus:ring-0 cursor-pointer">
                        <option value="">Heading</option>
                        <option value="h1">Heading 1</option>
                        <option value="h2">Heading 2</option>
                        <option value="h3">Heading 3</option>
                        <option value="p">Paragraph</option>
                    </select>

                    <span class="w-px h-5 bg-gray-300 mx-1"></span>

                    <button type="button" @click="execCommand('insertUnorderedList')" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Bullet List">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                    </button>
                    <button type="button" @click="execCommand('insertOrderedList')" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Numbered List">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75V4.5l-1.5.75M3 10.5h2.25M3 14.25h1.5l.75-1.5.75 1.5M3 20.25h2.25"/></svg>
                    </button>

                    <span class="w-px h-5 bg-gray-300 mx-1"></span>

                    <button type="button" @click="formatBlock('blockquote')" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Blockquote">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311C9.591 11.71 11 13.26 11 15.175 11 17.068 9.44 18.4 7.5 18.4c-1.065 0-2.08-.407-2.917-1.079zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.986.199 3.395 1.749 3.395 3.664 0 1.893-1.56 3.225-3.5 3.225-1.065 0-2.08-.407-2.917-1.079z"/></svg>
                    </button>
                    <button type="button" @click="insertLink" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Insert Link (Ctrl+K)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                    </button>
                    <button type="button" @click="execCommand('insertHorizontalRule')" class="p-1.5 rounded hover:bg-gray-200 text-gray-600" title="Horizontal Rule">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5"/></svg>
                    </button>
                </div>

                <!-- Editor -->
                <div
                    ref="editorRef"
                    contenteditable="true"
                    class="prose prose-sm max-w-none p-3 min-h-[120px] text-sm text-gray-700 focus:outline-none"
                    @keydown.ctrl.enter="saveDescription"
                    @keydown.meta.enter="saveDescription"
                ></div>

                <!-- Actions -->
                <div class="flex justify-end gap-2 px-3 py-2 bg-gray-50 border-t border-gray-200">
                    <span class="text-xs text-gray-400 mr-auto mt-1.5">Ctrl+Enter to save</span>
                    <button type="button" @click="cancelEditing" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                    <button type="button" @click="saveDescription" class="px-3 py-1.5 text-sm bg-primary-600 text-white rounded-md hover:bg-primary-500 disabled:opacity-50" :disabled="isSaving">
                        {{ isSaving ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>

            <!-- Attachments Section -->
            <div v-if="showAttachments" class="mt-4">
                <!-- Upload Zone (only when editing) -->
                <div v-if="canEdit && isEditing"
                     @dragover="onDragOver"
                     @dragleave="onDragLeave"
                     @drop="onDrop"
                     :class="['border-2 border-dashed rounded-lg p-4 text-center transition-colors', isDragOver ? 'border-primary-400 bg-primary-50' : 'border-gray-200 hover:border-gray-300']"
                >
                    <div v-if="isUploading" class="text-sm text-gray-500">
                        <svg class="animate-spin h-5 w-5 mx-auto mb-1 text-primary-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Uploading...
                    </div>
                    <div v-else>
                        <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                        </svg>
                        <p class="mt-1 text-sm text-gray-500">Drag & drop files here or
                            <label class="text-primary-600 hover:text-primary-500 cursor-pointer">
                                browse
                                <input type="file" multiple class="hidden" @change="onFileSelect">
                            </label>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">Max 10MB per file</p>
                    </div>
                </div>

                <!-- Attachment List -->
                <div v-if="attachments.length > 0" class="mt-3 space-y-2">
                    <div v-for="attachment in attachments" :key="attachment.id"
                         class="flex items-center gap-3 p-2 rounded-lg bg-gray-50 group">
                        <!-- Image preview or file icon -->
                        <div class="flex-shrink-0 w-10 h-10 rounded overflow-hidden bg-gray-200 flex items-center justify-center">
                            <img v-if="attachment.isImage && attachment.previewUrl" :src="attachment.previewUrl" class="w-full h-full object-cover" :alt="attachment.originalName">
                            <svg v-else class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <a :href="attachment.downloadUrl" class="text-sm font-medium text-gray-700 hover:text-primary-600 truncate block">{{ attachment.originalName }}</a>
                            <p class="text-xs text-gray-400">{{ attachment.humanFileSize }}</p>
                        </div>
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a :href="attachment.downloadUrl" class="p-1 text-gray-400 hover:text-gray-600" title="Download">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            </a>
                            <button v-if="canEdit" type="button" @click="deleteAttachment(attachment)" class="p-1 text-gray-400 hover:text-red-600" title="Delete">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `
};
