import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['column', 'card'];

    connect() {
        this.cardTargets.forEach(card => {
            card.setAttribute('draggable', 'true');
            card.addEventListener('dragstart', this.dragStart.bind(this));
            card.addEventListener('dragend', this.dragEnd.bind(this));
        });

        this.columnTargets.forEach(column => {
            column.addEventListener('dragover', this.dragOver.bind(this));
            column.addEventListener('drop', this.drop.bind(this));
        });
    }

    dragStart(event) {
        event.target.classList.add('opacity-50');
        event.dataTransfer.setData('text/plain', event.target.dataset.taskId);
    }

    dragEnd(event) {
        event.target.classList.remove('opacity-50');
    }

    dragOver(event) {
        event.preventDefault();
    }

    async drop(event) {
        event.preventDefault();
        const taskId = event.dataTransfer.getData('text/plain');
        const newStatus = event.currentTarget.dataset.status;
        const card = document.querySelector(`[data-task-id="${taskId}"]`);

        // Move card to new column
        const dropZone = event.currentTarget.querySelector('[data-kanban-target="dropzone"]');
        dropZone.appendChild(card);

        // Update status via AJAX
        try {
            const response = await fetch(`/tasks/${taskId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ status: newStatus })
            });

            if (!response.ok) {
                throw new Error('Failed to update status');
            }
        } catch (error) {
            console.error('Error updating task status:', error);
        }
    }
}
