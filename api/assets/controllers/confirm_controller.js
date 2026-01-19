import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { message: String };

    confirm(event) {
        if (!confirm(this.messageValue || 'Are you sure?')) {
            event.preventDefault();
        }
    }
}
