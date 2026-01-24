import './bootstrap.js';
import '@hotwired/turbo';
import './styles/app.css';

// Vue components
import { autoMountVueComponents } from 'vue/index';
import TagsEditor from 'vue/components/TagsEditor';
import ChecklistEditor from 'vue/components/ChecklistEditor';
import ActivityLog from 'vue/components/ActivityLog';
import CommentsEditor from 'vue/components/CommentsEditor';

// Register Vue components
const vueComponents = {
    TagsEditor,
    ChecklistEditor,
    ActivityLog,
    CommentsEditor
};

// Mount Vue components on page load and after Turbo navigations
function mountVueComponents() {
    autoMountVueComponents(vueComponents);
}

// Initial mount
document.addEventListener('DOMContentLoaded', mountVueComponents);

// Re-mount after Turbo page loads
document.addEventListener('turbo:load', mountVueComponents);

// Re-mount after Turbo frame loads (for AJAX-loaded content like task panel)
document.addEventListener('turbo:frame-load', mountVueComponents);
