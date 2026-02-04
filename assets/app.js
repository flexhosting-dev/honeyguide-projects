import './bootstrap.js';
import '@hotwired/turbo';
import './styles/app.css';
import './js/notifications.js';

// Vue components
import { autoMountVueComponents } from 'vue/index';
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
    TaskTable
};

// Mount Vue components on page load and after Turbo navigations
function mountVueComponents() {
    autoMountVueComponents(vueComponents);
}

// Make globally accessible for AJAX-loaded content (e.g., task panel)
window.mountVueComponents = mountVueComponents;

// Initial mount
document.addEventListener('DOMContentLoaded', mountVueComponents);

// Re-mount after Turbo page loads
document.addEventListener('turbo:load', mountVueComponents);

// Re-mount after Turbo frame loads (for AJAX-loaded content like task panel)
document.addEventListener('turbo:frame-load', mountVueComponents);
