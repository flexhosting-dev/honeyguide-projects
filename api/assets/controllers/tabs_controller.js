import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];
    static values = { index: Number, style: String };

    connect() {
        this.showTab(this.indexValue || 0);
    }

    select(event) {
        const index = this.tabTargets.indexOf(event.currentTarget);
        this.showTab(index);
    }

    showTab(index) {
        const isPillStyle = this.styleValue === 'pill';

        this.tabTargets.forEach((tab, i) => {
            if (i === index) {
                if (isPillStyle) {
                    tab.classList.add('border-primary-500', 'text-primary-600', 'bg-primary-50', 'border');
                    tab.classList.remove('text-gray-600', 'hover:bg-gray-50', 'border-transparent');
                } else {
                    tab.classList.add('border-primary-500', 'text-primary-600');
                    tab.classList.remove('border-transparent', 'text-gray-500');
                }
            } else {
                if (isPillStyle) {
                    tab.classList.remove('border-primary-500', 'text-primary-600', 'bg-primary-50', 'border');
                    tab.classList.add('text-gray-600', 'hover:bg-gray-50', 'border-transparent');
                } else {
                    tab.classList.remove('border-primary-500', 'text-primary-600');
                    tab.classList.add('border-transparent', 'text-gray-500');
                }
            }
        });

        this.panelTargets.forEach((panel, i) => {
            panel.classList.toggle('hidden', i !== index);
        });
    }
}
