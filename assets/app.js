import './bootstrap.js';
import '@hotwired/turbo';
import './styles/app.css';

// Vue components
import { autoMountVueComponents } from 'vue/index';
import TagsEditor from 'vue/components/TagsEditor';
import ChecklistEditor from 'vue/components/ChecklistEditor';
import ActivityLog from 'vue/components/ActivityLog';
import CommentsEditor from 'vue/components/CommentsEditor';
import TaskCard from 'vue/components/TaskCard';
import KanbanBoard from 'vue/components/KanbanBoard';

// Register Vue components
const vueComponents = {
    TagsEditor,
    ChecklistEditor,
    ActivityLog,
    CommentsEditor,
    TaskCard,
    KanbanBoard
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
