/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'panel',
        'overlay',
        'searchInput',
        'userList',
        'selectedBadge',
        'emailInput',
        'emailRoleSelect',
        'bulkRoleSelect',
        'emailResult',
        'bulkAddButton'
    ];

    static values = {
        projectId: String,
        basePath: String
    };

    connect() {
        this.selectedUsers = new Set();
        this.debounceTimer = null;
        this.boundHandleOutsideClick = this.handleOutsideClick.bind(this);
    }

    disconnect() {
        document.removeEventListener('click', this.boundHandleOutsideClick);
    }

    open(event) {
        event.stopPropagation();
        this.panelTarget.classList.remove('translate-x-full');
        this.panelTarget.classList.add('translate-x-0');
        this.overlayTarget.classList.remove('hidden');

        // Add outside click listener
        setTimeout(() => {
            document.addEventListener('click', this.boundHandleOutsideClick);
        }, 100);

        // Load initial user list
        this.loadEligibleUsers('');
    }

    close() {
        this.panelTarget.classList.add('translate-x-full');
        this.panelTarget.classList.remove('translate-x-0');
        this.overlayTarget.classList.add('hidden');

        // Remove outside click listener
        document.removeEventListener('click', this.boundHandleOutsideClick);

        // Clear selections
        this.selectedUsers.clear();
        this.updateSelectedBadge();
        this.emailResultTarget.innerHTML = '';
        this.emailInputTarget.value = '';
    }

    handleOutsideClick(event) {
        if (!this.panelTarget.contains(event.target) &&
            !event.target.closest('[data-action*="add-members#open"]')) {
            this.close();
        }
    }

    search(event) {
        clearTimeout(this.debounceTimer);
        const searchTerm = event.target.value;

        this.debounceTimer = setTimeout(() => {
            this.loadEligibleUsers(searchTerm);
        }, 300);
    }

    async loadEligibleUsers(searchTerm) {
        const url = `${this.basePathValue}/projects/${this.projectIdValue}/members/eligible?search=${encodeURIComponent(searchTerm)}`;

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load users');
            }

            const data = await response.json();
            this.renderUserList(data.users);
        } catch (error) {
            console.error('Error loading users:', error);
            this.userListTarget.innerHTML = '<p class="text-red-600 p-4">Error loading users</p>';
        }
    }

    renderUserList(users) {
        if (users.length === 0) {
            this.userListTarget.innerHTML = '<p class="text-gray-500 p-4">No users found</p>';
            return;
        }

        this.userListTarget.innerHTML = users.map(user => `
            <div class="flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer"
                 data-user-id="${user.id}"
                 data-action="click->add-members#toggleUser">
                <input type="checkbox"
                       id="user-${user.id}"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       ${this.selectedUsers.has(user.id) ? 'checked' : ''}>
                <div class="flex items-center gap-2 flex-1">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-medium">
                        ${user.initials}
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">${this.escapeHtml(user.name)}</div>
                        <div class="text-sm text-gray-500">${this.escapeHtml(user.email)}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    toggleUser(event) {
        const userDiv = event.target.closest('[data-user-id]');
        if (!userDiv) return;

        const userId = userDiv.dataset.userId;
        const checkbox = userDiv.querySelector('input[type="checkbox"]');

        if (this.selectedUsers.has(userId)) {
            this.selectedUsers.delete(userId);
            checkbox.checked = false;
        } else {
            this.selectedUsers.add(userId);
            checkbox.checked = true;
        }

        this.updateSelectedBadge();
    }

    updateSelectedBadge() {
        const count = this.selectedUsers.size;
        if (count > 0) {
            this.selectedBadgeTarget.textContent = count;
            this.selectedBadgeTarget.classList.remove('hidden');
            this.bulkAddButtonTarget.disabled = false;
        } else {
            this.selectedBadgeTarget.classList.add('hidden');
            this.bulkAddButtonTarget.disabled = true;
        }
    }

    async bulkAdd(event) {
        event.preventDefault();

        if (this.selectedUsers.size === 0) {
            return;
        }

        const role = this.bulkRoleSelectTarget.value;
        const userIds = Array.from(this.selectedUsers);

        this.bulkAddButtonTarget.disabled = true;
        this.bulkAddButtonTarget.textContent = 'Adding...';

        try {
            const response = await fetch(`${this.basePathValue}/projects/${this.projectIdValue}/members/bulk-add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ userIds, role })
            });

            const data = await response.json();

            if (data.success) {
                this.showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                this.showToast(data.error || 'Failed to add users', 'error');
                this.bulkAddButtonTarget.disabled = false;
                this.bulkAddButtonTarget.textContent = 'Add Selected';
            }
        } catch (error) {
            console.error('Error adding users:', error);
            this.showToast('Error adding users', 'error');
            this.bulkAddButtonTarget.disabled = false;
            this.bulkAddButtonTarget.textContent = 'Add Selected';
        }
    }

    async inviteByEmail(event) {
        event.preventDefault();

        const email = this.emailInputTarget.value.trim();
        const role = this.emailRoleSelectTarget.value;

        if (!email) {
            this.emailResultTarget.innerHTML = '<p class="text-red-600 text-sm mt-2">Email is required</p>';
            return;
        }

        // Basic email validation
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            this.emailResultTarget.innerHTML = '<p class="text-red-600 text-sm mt-2">Invalid email format</p>';
            return;
        }

        this.emailResultTarget.innerHTML = '<p class="text-blue-600 text-sm mt-2">Sending invitation...</p>';

        try {
            const response = await fetch(`${this.basePathValue}/projects/${this.projectIdValue}/members/invite-email`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email, role })
            });

            const data = await response.json();

            if (data.success) {
                this.emailResultTarget.innerHTML = `<p class="text-green-600 text-sm mt-2">${this.escapeHtml(data.message)}</p>`;
                this.emailInputTarget.value = '';

                if (!data.requiresApproval) {
                    setTimeout(() => window.location.reload(), 1500);
                }
            } else {
                this.emailResultTarget.innerHTML = `<p class="text-red-600 text-sm mt-2">${this.escapeHtml(data.error)}</p>`;
            }
        } catch (error) {
            console.error('Error inviting user:', error);
            this.emailResultTarget.innerHTML = '<p class="text-red-600 text-sm mt-2">Error sending invitation</p>';
        }
    }

    showToast(message, type) {
        // Use Toastr if available, otherwise fallback to alert
        if (typeof toastr !== 'undefined') {
            toastr[type](message);
        } else {
            alert(message);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
