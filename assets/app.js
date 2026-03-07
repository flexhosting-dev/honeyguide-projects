import './bootstrap.js';
import '@hotwired/turbo';
import './styles/app.css';
import './js/notifications.js';

// Vue components
import { autoMountVueComponents, createApp } from 'vue/index';
import TagsEditor from 'vue/components/TagsEditor';
import ChecklistEditor from 'vue/components/ChecklistEditor';
import ActivityLog from 'vue/components/ActivityLog';
import CommentsEditor from 'vue/components/CommentsEditor';
import TaskCreateForm from 'vue/components/TaskCreateForm';
import KanbanBoard from 'vue/components/KanbanBoard';
import RichTextEditor from 'vue/components/RichTextEditor';
import SubtasksEditor from 'vue/components/SubtasksEditor';
import QuickAddCard from 'vue/components/QuickAddCard';
import TaskTable from 'vue/components/TaskTable';
import GanttView from 'vue/components/GanttView';
import ConfirmDialog from 'vue/components/ConfirmDialog';
import ProfileHoverCard from 'vue/components/ProfileHoverCard';

// Tippy.js for profile hover cards
import tippy from 'tippy.js';

// Register Vue components
const vueComponents = {
    TagsEditor,
    ChecklistEditor,
    ActivityLog,
    CommentsEditor,
    TaskCreateForm,
    KanbanBoard,
    RichTextEditor,
    SubtasksEditor,
    QuickAddCard,
    TaskTable,
    GanttView
};

// Mount Vue components on page load and after Turbo navigations
function mountVueComponents(forceRemount = false) {
    autoMountVueComponents(vueComponents, forceRemount);
}

// Make globally accessible for AJAX-loaded content (e.g., task panel)
window.mountVueComponents = mountVueComponents;

// Force remount all Vue components (useful for view switching)
window.remountVueComponents = function() {
    mountVueComponents(true);
};

// Initialize global confirm dialog
function initGlobalConfirmDialog() {
    const dialogContainer = document.getElementById('global-confirm-dialog');
    if (dialogContainer && !window._globalConfirmDialogInstance) {
        const app = createApp(ConfirmDialog);
        const instance = app.mount(dialogContainer);
        window._globalConfirmDialogInstance = instance;
    }
}

// Global function to show confirm dialog
window.showConfirmDialog = async function(options = {}) {
    if (window._globalConfirmDialogInstance && window._globalConfirmDialogInstance.show) {
        return await window._globalConfirmDialogInstance.show(options);
    } else {
        // Fallback to native confirm if Vue component isn't loaded yet
        console.warn('ConfirmDialog not ready, using native confirm');
        return window.confirm(options.message || 'Are you sure?');
    }
};

// Initial mount
document.addEventListener('DOMContentLoaded', () => {
    mountVueComponents();
    initGlobalConfirmDialog();
});

// Re-mount after Turbo page loads
document.addEventListener('turbo:load', () => {
    mountVueComponents();

    // Clear deleted tasks from sessionStorage after components have processed them
    // This prevents the list from growing indefinitely
    setTimeout(() => {
        try {
            sessionStorage.removeItem('deleted_tasks');
        } catch (e) {
            console.error('Error clearing deleted tasks:', e);
        }
    }, 1000);
});

// Re-mount after Turbo frame loads (for AJAX-loaded content like task panel)
document.addEventListener('turbo:frame-load', mountVueComponents);

// Profile hover cards with Tippy.js
function initProfileHoverCards() {
    // Create singleton container for Vue-rendered content
    let container = document.getElementById('profile-hover-card-content');
    if (!container) {
        container = document.createElement('div');
        container.id = 'profile-hover-card-content';
        document.body.appendChild(container);
    }

    let currentVueApp = null;

    // Get all elements with data-user-id
    const elements = document.querySelectorAll('[data-user-id]');

    // Destroy existing Tippy instances and initialize new ones
    elements.forEach((el) => {
        // Destroy existing instance if present
        if (el._tippy) {
            el._tippy.destroy();
        }

        // Create new Tippy instance for this element
        tippy(el, {
            content: '', // Will be set dynamically in onShow
            allowHTML: true,
            interactive: true,
            arrow: true,
            placement: 'top',
            theme: 'profile-card',
            animation: 'shift-away',
            delay: [500, 0],
            maxWidth: 'none',
            appendTo: () => document.body,

            onShow(instance) {
                const userId = instance.reference.dataset.userId;

                if (!userId) {
                    return false;
                }

                // Set the shared container as content for this instance
                instance.setContent(container);

                // Unmount previous Vue app if exists
                if (currentVueApp) {
                    currentVueApp.unmount();
                }

                // Create new Vue app with ProfileHoverCard
                currentVueApp = createApp(ProfileHoverCard, {
                    userId: userId
                });

                currentVueApp.mount(container);
            },

            onHide() {
                // Keep Vue app mounted for smooth transition
                // Will be replaced on next show
            }
        });
    });
}

// Make globally accessible for Vue components
window.initProfileHoverCards = initProfileHoverCards;

// Initialize profile hover cards on page load
document.addEventListener('DOMContentLoaded', initProfileHoverCards);

// Re-initialize after Turbo navigations
document.addEventListener('turbo:load', initProfileHoverCards);
document.addEventListener('turbo:frame-load', initProfileHoverCards);
