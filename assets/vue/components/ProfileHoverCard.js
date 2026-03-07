import { ref } from 'vue';

export default {
    name: 'ProfileHoverCard',

    props: {
        userId: { type: String, required: true }
    },

    setup(props) {
        const userData = ref(null);
        const isLoading = ref(true); // Start with loading state
        const error = ref(null);

        // Cache for user data (5 minute TTL)
        const cache = new Map();
        const CACHE_TTL = 5 * 60 * 1000;

        // Fetch user data
        const fetchUserData = async () => {
            const cached = cache.get(props.userId);
            if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
                userData.value = cached.data;
                return;
            }

            isLoading.value = true;
            error.value = null;

            try {
                const basePath = window.BASE_PATH || '';
                const response = await fetch(`${basePath}/users/${props.userId}/hover-card`);

                if (!response.ok) {
                    throw new Error('Failed to load profile');
                }

                const data = await response.json();
                userData.value = data;
                cache.set(props.userId, { data, timestamp: Date.now() });
            } catch (e) {
                error.value = e.message;
            } finally {
                isLoading.value = false;
            }
        };

        const viewProfile = () => {
            const basePath = window.BASE_PATH || '';
            window.location.href = `${basePath}/users/${props.userId}/profile`;
        };

        // Fetch immediately when component is created
        fetchUserData();

        return {
            userData,
            isLoading,
            error,
            viewProfile
        };
    },

    template: `
        <div class="profile-hover-card bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden" style="width: 280px;">
            <!-- Loading State -->
            <div v-if="isLoading" class="p-8 text-center">
                <div class="inline-block">
                    <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="p-6 text-center">
                <svg class="h-8 w-8 text-red-500 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-red-600">{{ error }}</p>
            </div>

            <!-- Content -->
            <div v-else-if="userData">
                <!-- Header with gradient background -->
                <div class="bg-gradient-to-r from-emerald-50 via-cyan-50 to-sky-50 px-4 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <!-- Avatar -->
                        <div class="flex-shrink-0">
                            <div v-if="userData.avatar"
                                 class="h-14 w-14 rounded-full overflow-hidden ring-2 ring-white shadow-md">
                                <img :src="(window.BASE_PATH || '') + '/uploads/avatars/' + userData.avatar"
                                     :alt="userData.fullName"
                                     class="w-full h-full object-cover">
                            </div>
                            <div v-else
                                 class="h-14 w-14 rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center ring-2 ring-white shadow-md">
                                <span class="text-white font-semibold text-lg">{{ userData.initials }}</span>
                            </div>
                        </div>

                        <!-- User Info -->
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate text-base leading-tight">
                                {{ userData.fullName }}
                            </h3>
                            <p v-if="userData.jobTitle" class="text-xs text-gray-600 truncate mt-0.5">
                                {{ userData.jobTitle }}
                            </p>
                            <p v-if="userData.department" class="text-xs text-gray-500 truncate mt-0.5">
                                {{ userData.department }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="px-4 py-3 space-y-2">
                    <!-- Email -->
                    <div v-if="userData.email" class="flex items-center gap-2 text-xs">
                        <svg class="h-3.5 w-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <a :href="'mailto:' + userData.email"
                           class="text-primary-600 hover:text-primary-700 truncate flex-1">
                            {{ userData.email }}
                        </a>
                    </div>

                    <!-- Active Projects -->
                    <div class="flex items-center gap-2 text-xs">
                        <svg class="h-3.5 w-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                        <span class="text-gray-600">
                            <span class="font-semibold text-gray-900">{{ userData.activeProjectsCount }}</span>
                            {{ userData.activeProjectsCount === 1 ? 'Active Project' : 'Active Projects' }}
                        </span>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="px-4 pb-4">
                    <button @click="viewProfile"
                            class="w-full px-3 py-2 bg-gradient-to-r from-primary-600 to-primary-500 text-white rounded-lg hover:from-primary-700 hover:to-primary-600 transition-all text-xs font-semibold shadow-sm hover:shadow flex items-center justify-center gap-1.5">
                        <span>View Full Profile</span>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `
};
