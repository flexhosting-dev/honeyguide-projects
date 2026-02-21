/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu'];

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        const isHidden = this.menuTarget.classList.contains('hidden');

        // Close any other open dropdowns first
        this.closeOtherDropdowns();

        // Toggle this dropdown
        if (isHidden) {
            this.open();
        } else {
            this.close();
        }
    }

    open() {
        this.menuTarget.classList.remove('hidden');
    }

    close() {
        this.menuTarget.classList.add('hidden');
    }

    closeOtherDropdowns() {
        // Close all other dropdown menus
        document.querySelectorAll('[data-dropdown-target="menu"]').forEach(menu => {
            if (menu !== this.menuTarget) {
                menu.classList.add('hidden');
            }
        });
    }

    handleClickOutside(event) {
        // Don't close if clicking inside the dropdown
        if (this.element.contains(event.target)) {
            return;
        }

        // Close the dropdown
        this.close();
    }

    connect() {
        // Bind the click outside handler
        this.boundHandleClickOutside = this.handleClickOutside.bind(this);

        // Use capture phase to ensure we catch all clicks
        document.addEventListener('click', this.boundHandleClickOutside, true);
    }

    disconnect() {
        // Clean up the event listener
        document.removeEventListener('click', this.boundHandleClickOutside, true);
    }
}
