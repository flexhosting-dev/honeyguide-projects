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
