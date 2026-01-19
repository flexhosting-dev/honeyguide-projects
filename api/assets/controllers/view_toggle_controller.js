import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['btn', 'panel'];

    connect() {
        this.showPanel(0);
    }

    switch(event) {
        const index = this.btnTargets.indexOf(event.currentTarget);
        this.showPanel(index);
    }

    showPanel(index) {
        this.btnTargets.forEach((btn, i) => {
            if (i === index) {
                btn.classList.add('bg-white', 'text-primary-600', 'shadow-sm');
                btn.classList.remove('text-gray-600', 'hover:text-gray-900');
            } else {
                btn.classList.remove('bg-white', 'text-primary-600', 'shadow-sm');
                btn.classList.add('text-gray-600', 'hover:text-gray-900');
            }
        });

        this.panelTargets.forEach((panel, i) => {
            panel.classList.toggle('hidden', i !== index);
        });
    }
}
