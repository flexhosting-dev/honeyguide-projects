import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { message: String };

    async confirm(event) {
        event.preventDefault();

        const confirmed = await window.showConfirmDialog({
            title: 'Confirm',
            message: this.messageValue || 'Are you sure?',
            confirmText: 'Confirm',
            type: 'warning'
        });

        if (confirmed) {
            // Re-trigger the form submission without the confirm handler
            event.target.removeEventListener('submit', this.confirm);
            event.target.submit();
        }
    }
}
