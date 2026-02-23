import { createApp, ref, reactive, computed, onMounted, watch } from 'vue';

// Export Vue utilities for use in components
export { ref, reactive, computed, onMounted, watch };

// Store for shared state across components
export const store = reactive({
    basePath: window.BASE_PATH || '',
});

// Global mount function for Vue components
export function mountVueComponent(elementId, component, props = {}) {
    const element = document.getElementById(elementId);
    if (!element) return null;

    const app = createApp(component, props);
    app.mount(element);
    return app;
}

// Auto-mount Vue components on elements with data-vue-component attribute
export function autoMountVueComponents(components, forceRemount = false) {
    document.querySelectorAll('[data-vue-component]').forEach(element => {
        // Unmount if already mounted and forceRemount is true
        if (element.__vue_app__) {
            if (forceRemount) {
                element.__vue_app__.unmount();
                element.__vue_app__ = null;
            } else {
                return;
            }
        }

        const componentName = element.dataset.vueComponent;
        const component = components[componentName];

        if (!component) {
            console.warn(`Vue component "${componentName}" not found`);
            return;
        }

        // Parse props from data attributes
        const props = {};
        Object.keys(element.dataset).forEach(key => {
            if (key !== 'vueComponent' && key.startsWith('vue')) {
                // Convert vueTaskId to taskId
                const propName = key.replace('vue', '').replace(/^[A-Z]/, c => c.toLowerCase());
                let value = element.dataset[key];

                // Handle boolean strings explicitly
                if (value === 'true') {
                    value = true;
                } else if (value === 'false') {
                    value = false;
                } else {
                    // Try to parse JSON values
                    try {
                        value = JSON.parse(value);
                        // Convert object with numeric keys to array (Twig |map sometimes produces this)
                        if (value && typeof value === 'object' && !Array.isArray(value)) {
                            const keys = Object.keys(value);
                            if (keys.length > 0 && keys.every(k => /^\d+$/.test(k))) {
                                value = Object.values(value);
                            }
                        }
                    } catch (e) {
                        // Keep as string if not valid JSON
                    }
                }

                props[propName] = value;
            }
        });

        const app = createApp(component, props);
        app.mount(element);
        // Store app instance for potential unmounting
        element.__vue_app__ = app;
    });
}

export { createApp };
