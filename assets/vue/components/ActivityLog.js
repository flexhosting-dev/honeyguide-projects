import { ref, onMounted, onUnmounted } from 'vue';

export default {
    name: 'ActivityLog',

    props: {
        taskId: {
            type: String,
            required: true
        },
        basePath: {
            type: String,
            default: ''
        },
        autoLoad: {
            type: Boolean,
            default: false
        }
    },

    setup(props) {
        const activities = ref([]);
        const isLoading = ref(false);
        const isLoaded = ref(false);
        const error = ref(null);

        const basePath = props.basePath || window.BASE_PATH || '';

        // Icon SVGs for different action types
        const icons = {
            created: `<svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>`,
            status_changed: `<svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>`,
            priority_changed: `<svg class="h-4 w-4 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0l-3.75-3.75M17.25 21l3.75-3.75" /></svg>`,
            milestone_changed: `<svg class="h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" /></svg>`,
            assigned: `<svg class="h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>`,
            unassigned: `<svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>`,
            commented: `<svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>`,
            updated: `<svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>`
        };

        // Get icon for action type
        const getIcon = (action) => {
            return icons[action] || icons.updated;
        };

        // Format activity description
        const formatDescription = (activity) => {
            const action = activity.action;
            const metadata = activity.metadata || {};

            switch (action) {
                case 'created':
                    return 'created this task';
                case 'status_changed':
                    return `changed status from <span class="font-medium">${escapeHtml(metadata.from || '')}</span> to <span class="font-medium">${escapeHtml(metadata.to || '')}</span>`;
                case 'priority_changed':
                    return `changed priority from <span class="font-medium">${escapeHtml(metadata.from || '')}</span> to <span class="font-medium">${escapeHtml(metadata.to || '')}</span>`;
                case 'milestone_changed':
                    return `moved to milestone <span class="font-medium">${escapeHtml(metadata.to || '')}</span>`;
                case 'assigned':
                    return `assigned <span class="font-medium">${escapeHtml(metadata.assignee || '')}</span>`;
                case 'unassigned':
                    return `unassigned <span class="font-medium">${escapeHtml(metadata.assignee || '')}</span>`;
                case 'commented':
                    return 'added a comment';
                case 'updated':
                    return formatUpdatedDescription(metadata);
                default:
                    return activity.actionLabel || 'updated this task';
            }
        };

        // Format updated action description
        const formatUpdatedDescription = (metadata) => {
            const changes = metadata.changes || {};

            if (changes.title) {
                return `changed title from <span class="font-medium">${escapeHtml(changes.title.from || '')}</span> to <span class="font-medium">${escapeHtml(changes.title.to || '')}</span>`;
            }
            if (changes.dueDate) {
                const from = changes.dueDate.from || 'none';
                const to = changes.dueDate.to || 'none';
                return `changed due date from <span class="font-medium">${escapeHtml(from)}</span> to <span class="font-medium">${escapeHtml(to)}</span>`;
            }
            if (changes.startDate) {
                const from = changes.startDate.from || 'none';
                const to = changes.startDate.to || 'none';
                return `changed start date from <span class="font-medium">${escapeHtml(from)}</span> to <span class="font-medium">${escapeHtml(to)}</span>`;
            }
            return 'updated this task';
        };

        // Escape HTML to prevent XSS
        const escapeHtml = (text) => {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };

        // Load activities from API
        const loadActivities = async () => {
            if (isLoaded.value || isLoading.value) return;

            isLoading.value = true;
            error.value = null;

            try {
                const response = await fetch(`${basePath}/tasks/${props.taskId}/activity`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    throw new Error('Failed to load activity');
                }

                const data = await response.json();
                activities.value = data.activities || [];
                isLoaded.value = true;
            } catch (err) {
                console.error('Error loading activity:', err);
                error.value = 'Failed to load activity';
            } finally {
                isLoading.value = false;
            }
        };

        // Handle custom event to trigger loading
        const handleLoadEvent = () => {
            loadActivities();
        };

        // Auto-load if prop is set, and listen for custom event
        onMounted(() => {
            if (props.autoLoad) {
                loadActivities();
            }
            // Listen for custom event to trigger loading (for tab switching)
            document.addEventListener('load-activity', handleLoadEvent);
        });

        // Cleanup event listener
        onUnmounted(() => {
            document.removeEventListener('load-activity', handleLoadEvent);
        });

        return {
            activities,
            isLoading,
            isLoaded,
            error,
            getIcon,
            formatDescription,
            loadActivities
        };
    },

    template: `
        <div class="activity-container">
            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center py-8">
                <svg class="animate-spin h-6 w-6 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-2 text-sm text-gray-500">Loading activity...</span>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="py-4">
                <p class="text-sm text-red-500">{{ error }}</p>
                <button
                    @click="loadActivities"
                    class="mt-2 text-sm text-primary-600 hover:text-primary-700"
                >
                    Try again
                </button>
            </div>

            <!-- Not Loaded State (for lazy loading) -->
            <div v-else-if="!isLoaded" class="py-4">
                <button
                    @click="loadActivities"
                    class="text-sm text-primary-600 hover:text-primary-700"
                >
                    Load activity
                </button>
            </div>

            <!-- Activity List -->
            <div v-else-if="activities.length > 0" class="activity-list space-y-3">
                <div
                    v-for="activity in activities"
                    :key="activity.id"
                    class="activity-item flex gap-3 py-2"
                >
                    <div class="flex-shrink-0">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100" v-html="getIcon(activity.action)">
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900">
                            <span class="font-medium">{{ activity.user.fullName }}</span>
                            <span class="text-gray-600" v-html="' ' + formatDescription(activity)"></span>
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ activity.createdAt }}</p>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <p v-else class="text-sm text-gray-400 italic py-4">No activity yet</p>
        </div>
    `
};
