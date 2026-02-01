import { ref, computed, nextTick, watch, onMounted } from 'vue';

export default {
    name: 'CommentsEditor',

    props: {
        taskId: {
            type: String,
            required: true
        },
        initialComments: {
            type: Array,
            default: () => []
        },
        currentUserId: {
            type: String,
            required: true
        },
        currentUserInitials: {
            type: String,
            default: ''
        },
        currentUserAvatar: {
            type: String,
            default: ''
        },
        basePath: {
            type: String,
            default: ''
        },
        canEdit: {
            type: Boolean,
            default: true
        },
        projectId: {
            type: String,
            default: ''
        }
    },

    setup(props) {
        const comments = ref([...props.initialComments]);
        const newComment = ref('');
        const isLoading = ref(false);
        const pendingFiles = ref([]);
        const isUploading = ref(false);

        // Mention state
        const showMentionDropdown = ref(false);
        const mentionQuery = ref('');
        const mentionStartIndex = ref(-1);
        const mentionMembers = ref([]);
        const mentionFilteredMembers = ref([]);
        const mentionSelectedIndex = ref(0);
        const membersLoaded = ref(false);

        const basePath = props.basePath || window.BASE_PATH || '';

        const sortedComments = computed(() => {
            return [...comments.value].reverse();
        });

        const canDelete = (comment) => {
            return comment.authorId === props.currentUserId;
        };

        const canAddComment = computed(() => {
            return (newComment.value.trim().length > 0 || pendingFiles.value.length > 0) && !isLoading.value;
        });

        const commentCount = computed(() => comments.value.length);

        const updateTabCount = () => {
            const countEl = document.querySelector('.comments-count');
            if (countEl) {
                countEl.textContent = commentCount.value > 0 ? `(${commentCount.value})` : '';
            }
        };

        watch(commentCount, updateTabCount);

        // Load project members for @mentions
        const loadMembers = async () => {
            if (membersLoaded.value || !props.projectId) return;
            try {
                const response = await fetch(`${basePath}/projects/${props.projectId}/members`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.ok) {
                    const data = await response.json();
                    mentionMembers.value = data.members || [];
                    membersLoaded.value = true;
                }
            } catch (error) {
                console.error('Error loading members:', error);
            }
        };

        // Handle textarea input for #mentions
        const onTextareaInput = (event) => {
            const textarea = event.target;
            const value = textarea.value;
            const cursorPos = textarea.selectionStart;

            // Find # before cursor
            const textBeforeCursor = value.substring(0, cursorPos);
            const lastHashIndex = textBeforeCursor.lastIndexOf('#');

            if (lastHashIndex >= 0) {
                const charBeforeHash = lastHashIndex > 0 ? textBeforeCursor[lastHashIndex - 1] : ' ';
                const textAfterHash = textBeforeCursor.substring(lastHashIndex + 1);

                // Only trigger if # is at start or after a space, and no space in query
                if ((charBeforeHash === ' ' || charBeforeHash === '\n' || lastHashIndex === 0) && !textAfterHash.includes(' ')) {
                    mentionStartIndex.value = lastHashIndex;
                    mentionQuery.value = textAfterHash.toLowerCase();
                    showMentionDropdown.value = true;
                    mentionSelectedIndex.value = 0;

                    // Filter members
                    mentionFilteredMembers.value = mentionMembers.value.filter(m =>
                        m.fullName.toLowerCase().includes(mentionQuery.value)
                    ).slice(0, 5);

                    if (mentionFilteredMembers.value.length === 0) {
                        showMentionDropdown.value = false;
                    }
                    return;
                }
            }

            showMentionDropdown.value = false;
        };

        const selectMention = (member) => {
            const textarea = document.querySelector('.comment-textarea');
            if (!textarea) return;

            const value = newComment.value;
            const before = value.substring(0, mentionStartIndex.value);
            const after = value.substring(textarea.selectionStart);

            newComment.value = before + '#' + member.fullName + ' ' + after;
            showMentionDropdown.value = false;

            nextTick(() => {
                const newPos = mentionStartIndex.value + member.fullName.length + 2;
                textarea.selectionStart = textarea.selectionEnd = newPos;
                textarea.focus();
            });
        };

        // Handle keyboard shortcuts
        const onKeydown = (event) => {
            if (showMentionDropdown.value) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    mentionSelectedIndex.value = Math.min(mentionSelectedIndex.value + 1, mentionFilteredMembers.value.length - 1);
                    return;
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    mentionSelectedIndex.value = Math.max(mentionSelectedIndex.value - 1, 0);
                    return;
                }
                if (event.key === 'Enter' || event.key === 'Tab') {
                    if (mentionFilteredMembers.value.length > 0) {
                        event.preventDefault();
                        selectMention(mentionFilteredMembers.value[mentionSelectedIndex.value]);
                        return;
                    }
                }
                if (event.key === 'Escape') {
                    showMentionDropdown.value = false;
                    return;
                }
            }

            // Cmd/Ctrl + Enter to submit
            if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
                event.preventDefault();
                addComment();
            }
        };

        // Extract mentioned user IDs from comment text
        const extractMentionedUserIds = (text) => {
            const ids = [];
            for (const member of mentionMembers.value) {
                if (text.includes('#' + member.fullName)) {
                    ids.push(member.id);
                }
            }
            return ids.length > 0 ? ids : null;
        };

        // Render comment content with styled mentions
        const renderContent = (text) => {
            if (!text) return '';
            let html = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            // Replace #Name with styled spans
            for (const member of mentionMembers.value) {
                const mention = '#' + member.fullName;
                const escaped = mention.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                html = html.replace(new RegExp(escaped, 'g'),
                    `<span class="text-primary-600 font-medium">${mention}</span>`
                );
            }

            return html;
        };

        // File attachment handling
        const onFileSelect = (event) => {
            const files = Array.from(event.target.files);
            pendingFiles.value.push(...files);
            event.target.value = '';
        };

        const removePendingFile = (index) => {
            pendingFiles.value.splice(index, 1);
        };

        const uploadCommentAttachments = async (commentId) => {
            if (pendingFiles.value.length === 0) return [];

            const formData = new FormData();
            for (const file of pendingFiles.value) {
                formData.append('files[]', file);
            }

            try {
                const response = await fetch(`${basePath}/comments/${commentId}/attachments`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });

                if (response.ok) {
                    const data = await response.json();
                    return data.attachments || [];
                }
            } catch (error) {
                console.error('Error uploading attachments:', error);
            }

            return [];
        };

        // Add new comment
        const addComment = async () => {
            const content = newComment.value.trim();
            if ((!content && pendingFiles.value.length === 0) || isLoading.value) return;

            isLoading.value = true;
            try {
                const mentionedUserIds = extractMentionedUserIds(content);

                const response = await fetch(`${basePath}/tasks/${props.taskId}/comments`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        content,
                        mentionedUserIds
                    })
                });

                if (response.ok) {
                    const data = await response.json();

                    // Upload attachments if any
                    let attachments = [];
                    if (pendingFiles.value.length > 0) {
                        attachments = await uploadCommentAttachments(data.comment.id);
                    }

                    comments.value.push({
                        id: data.comment.id,
                        content: data.comment.content,
                        authorName: data.comment.authorName,
                        authorInitials: data.comment.authorInitials,
                        authorAvatar: data.comment.authorAvatar || props.currentUserAvatar,
                        authorId: props.currentUserId,
                        createdAt: data.comment.createdAt,
                        attachments: attachments,
                    });
                    newComment.value = '';
                    pendingFiles.value = [];

                    nextTick(() => {
                        const list = document.querySelector('.comments-list');
                        if (list) {
                            list.scrollTop = 0;
                        }
                    });
                }
            } catch (error) {
                console.error('Error adding comment:', error);
            } finally {
                isLoading.value = false;
            }
        };

        // Delete comment
        const deleteComment = async (comment) => {
            if (!canDelete(comment) || isLoading.value) return;

            if (!confirm('Delete this comment?')) return;

            isLoading.value = true;
            try {
                const response = await fetch(`${basePath}/comments/${comment.id}/delete`, {
                    method: 'DELETE',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.ok) {
                    comments.value = comments.value.filter(c => c.id !== comment.id);
                }
            } catch (error) {
                console.error('Error deleting comment:', error);
            } finally {
                isLoading.value = false;
            }
        };

        // Load comment attachments
        const loadCommentAttachments = async () => {
            if (!props.taskId) return;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/comment-attachments`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.ok) {
                    const data = await response.json();
                    const grouped = data.commentAttachments || {};
                    for (const comment of comments.value) {
                        if (grouped[comment.id]) {
                            comment.attachments = grouped[comment.id];
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading comment attachments:', error);
            }
        };

        onMounted(() => {
            loadCommentAttachments();
        });

        // Load members on focus
        const onFocus = () => {
            loadMembers();
        };

        return {
            comments,
            sortedComments,
            newComment,
            isLoading,
            canDelete,
            canAddComment,
            canEdit: props.canEdit,
            currentUserAvatar: props.currentUserAvatar,
            currentUserInitials: props.currentUserInitials,
            addComment,
            deleteComment,
            onTextareaInput,
            onKeydown,
            onFocus,
            showMentionDropdown,
            mentionFilteredMembers,
            mentionSelectedIndex,
            selectMention,
            renderContent,
            pendingFiles,
            onFileSelect,
            removePendingFile,
        };
    },

    template: `
        <div class="comments-editor">
            <!-- Add Comment Form (only if canEdit) -->
            <div v-if="canEdit" class="add-comment-form mb-4">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full overflow-hidden">
                            <img v-if="currentUserAvatar" :src="currentUserAvatar" alt="Your avatar" class="w-full h-full object-cover">
                            <span v-else class="w-full h-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center">
                                <span class="text-sm font-medium text-white">{{ currentUserInitials }}</span>
                            </span>
                        </span>
                    </div>
                    <div class="flex-1 relative">
                        <textarea
                            v-model="newComment"
                            @input="onTextareaInput"
                            @keydown="onKeydown"
                            @focus="onFocus"
                            class="comment-textarea w-full text-sm border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none"
                            rows="2"
                            placeholder="Add a comment... (use # to mention)"
                            :disabled="isLoading"
                        ></textarea>

                        <!-- @Mention Dropdown -->
                        <div v-if="showMentionDropdown && mentionFilteredMembers.length > 0"
                             class="absolute left-0 bottom-full mb-1 w-56 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-20 py-1 max-h-48 overflow-y-auto">
                            <button v-for="(member, index) in mentionFilteredMembers" :key="member.id"
                                    type="button"
                                    @mousedown.prevent="selectMention(member)"
                                    class="w-full text-left px-3 py-2 text-sm flex items-center gap-2 hover:bg-gray-100"
                                    :class="{ 'bg-primary-50': index === mentionSelectedIndex }">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex-shrink-0">
                                    <span class="text-xs font-medium text-white">{{ member.initials || member.fullName.charAt(0).toUpperCase() }}</span>
                                </span>
                                <span class="truncate">{{ member.fullName }}</span>
                            </button>
                        </div>

                        <!-- Pending Files -->
                        <div v-if="pendingFiles.length > 0" class="mt-1 space-y-1">
                            <div v-for="(file, index) in pendingFiles" :key="index"
                                 class="flex items-center gap-2 text-xs text-gray-500 bg-gray-50 rounded px-2 py-1">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                                <span class="truncate flex-1">{{ file.name }}</span>
                                <button type="button" @click="removePendingFile(index)" class="text-gray-400 hover:text-red-500">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-between items-center mt-2">
                            <div class="flex items-center gap-2">
                                <label class="cursor-pointer text-gray-400 hover:text-gray-600" title="Attach file">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                                    <input type="file" multiple class="hidden" @change="onFileSelect">
                                </label>
                                <span class="text-xs text-gray-400">Ctrl+Enter to submit</span>
                            </div>
                            <button
                                type="button"
                                @click="addComment"
                                class="px-3 py-1.5 text-sm bg-primary-600 text-white rounded-md hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!canAddComment"
                            >
                                Post Comment
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comments List -->
            <div class="comments-list space-y-4">
                <div
                    v-for="comment in sortedComments"
                    :key="comment.id"
                    class="comment-item bg-gray-50 rounded-lg p-3"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full overflow-hidden mr-2">
                                <img v-if="comment.authorAvatar" :src="comment.authorAvatar" :alt="comment.authorName" class="w-full h-full object-cover">
                                <span v-else class="w-full h-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center">
                                    <span class="text-xs font-medium text-white">{{ comment.authorInitials }}</span>
                                </span>
                            </span>
                            <span class="text-sm font-medium text-gray-900">{{ comment.authorName }}</span>
                            <span class="ml-2 text-xs text-gray-500">{{ comment.createdAt }}</span>
                        </div>
                        <button
                            v-if="canEdit && canDelete(comment)"
                            @click="deleteComment(comment)"
                            class="text-gray-400 hover:text-red-600 p-1"
                            title="Delete comment"
                            :disabled="isLoading"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                    <div class="text-sm text-gray-600 whitespace-pre-wrap" v-html="renderContent(comment.content)"></div>

                    <!-- Comment Attachments -->
                    <div v-if="comment.attachments && comment.attachments.length > 0" class="mt-2 space-y-1">
                        <div v-for="attachment in comment.attachments" :key="attachment.id"
                             class="flex items-center gap-2 text-xs">
                            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                            <a :href="attachment.downloadUrl" class="text-primary-600 hover:text-primary-500 truncate">{{ attachment.originalName }}</a>
                            <span class="text-gray-400">{{ attachment.humanFileSize }}</span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <p v-if="comments.length === 0" class="text-sm text-gray-400 italic">
                    No comments yet
                </p>
            </div>
        </div>
    `
};
