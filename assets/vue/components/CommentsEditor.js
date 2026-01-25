import { ref, computed, nextTick, watch } from 'vue';

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
        basePath: {
            type: String,
            default: ''
        }
    },

    setup(props) {
        const comments = ref([...props.initialComments]);
        const newComment = ref('');
        const isLoading = ref(false);

        const basePath = props.basePath || window.BASE_PATH || '';

        // Sort comments newest first for display
        const sortedComments = computed(() => {
            return [...comments.value].reverse();
        });

        // Check if user can delete a comment
        const canDelete = (comment) => {
            return comment.authorId === props.currentUserId;
        };

        // Check if add button should be enabled
        const canAddComment = computed(() => {
            return newComment.value.trim().length > 0 && !isLoading.value;
        });

        // Computed for comment count
        const commentCount = computed(() => comments.value.length);

        // Update tab count in the DOM
        const updateTabCount = () => {
            const countEl = document.querySelector('.comments-count');
            if (countEl) {
                countEl.textContent = commentCount.value > 0 ? `(${commentCount.value})` : '';
            }
        };

        // Watch for changes and update tab count
        watch(commentCount, updateTabCount);

        // Add new comment
        const addComment = async () => {
            const content = newComment.value.trim();
            if (!content || isLoading.value) return;

            isLoading.value = true;
            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/comments`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ content })
                });

                if (response.ok) {
                    const data = await response.json();
                    comments.value.push({
                        id: data.comment.id,
                        content: data.comment.content,
                        authorName: data.comment.authorName,
                        authorInitials: data.comment.authorInitials,
                        authorId: props.currentUserId,
                        createdAt: data.comment.createdAt
                    });
                    newComment.value = '';

                    // Scroll to new comment
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

        // Handle textarea input for enabling/disabling button
        const onTextareaInput = () => {
            // Reactivity handles this automatically
        };

        // Handle keyboard shortcuts
        const onKeydown = (event) => {
            // Cmd/Ctrl + Enter to submit
            if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
                event.preventDefault();
                addComment();
            }
        };

        return {
            comments,
            sortedComments,
            newComment,
            isLoading,
            canDelete,
            canAddComment,
            addComment,
            deleteComment,
            onTextareaInput,
            onKeydown
        };
    },

    template: `
        <div class="comments-editor">
            <!-- Add Comment Form -->
            <div class="add-comment-form mb-4">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary-100">
                            <span class="text-sm font-medium text-primary-700">
                                {{ currentUserInitials }}
                            </span>
                        </span>
                    </div>
                    <div class="flex-1">
                        <textarea
                            v-model="newComment"
                            @keydown="onKeydown"
                            class="w-full text-sm border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none"
                            rows="2"
                            placeholder="Add a comment..."
                            :disabled="isLoading"
                        ></textarea>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs text-gray-400">Ctrl+Enter to submit</span>
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
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 mr-2">
                                <span class="text-xs font-medium text-primary-700">
                                    {{ comment.authorInitials }}
                                </span>
                            </span>
                            <span class="text-sm font-medium text-gray-900">{{ comment.authorName }}</span>
                            <span class="ml-2 text-xs text-gray-500">{{ comment.createdAt }}</span>
                        </div>
                        <button
                            v-if="canDelete(comment)"
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
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ comment.content }}</p>
                </div>

                <!-- Empty State -->
                <p v-if="comments.length === 0" class="text-sm text-gray-400 italic">
                    No comments yet
                </p>
            </div>
        </div>
    `
};
