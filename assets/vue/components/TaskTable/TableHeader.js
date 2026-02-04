import { computed } from 'vue';

export default {
    name: 'TableHeader',

    props: {
        columns: {
            type: Array,
            required: true
        },
        sortColumn: {
            type: String,
            default: 'position'
        },
        sortDirection: {
            type: String,
            default: 'asc'
        },
        allSelected: {
            type: Boolean,
            default: false
        },
        someSelected: {
            type: Boolean,
            default: false
        },
        canEdit: {
            type: Boolean,
            default: false
        }
    },

    emits: ['sort', 'select-all'],

    setup(props, { emit }) {
        const visibleColumns = computed(() => {
            return props.columns.filter(col => col.visible);
        });

        const handleSort = (column) => {
            if (!column.sortable) return;
            emit('sort', column.key);
        };

        const getSortIcon = (column) => {
            if (!column.sortable) return '';
            if (props.sortColumn !== column.key) return 'neutral';
            return props.sortDirection;
        };

        const handleSelectAll = (event) => {
            emit('select-all', event.target.checked);
        };

        const getColumnWidth = (column) => {
            if (column.width === 'flex') return '';
            return typeof column.width === 'number' ? `${column.width}px` : column.width;
        };

        return {
            visibleColumns,
            handleSort,
            getSortIcon,
            handleSelectAll,
            getColumnWidth
        };
    },

    template: `
        <thead class="bg-gray-50 sticky top-0 z-10">
            <tr role="row">
                <th v-for="column in visibleColumns"
                    :key="column.key"
                    scope="col"
                    :style="{ width: getColumnWidth(column), minWidth: getColumnWidth(column) }"
                    :class="[
                        'px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500',
                        column.sortable ? 'cursor-pointer hover:bg-gray-100 select-none' : '',
                        column.key === 'checkbox' ? 'w-10' : '',
                        column.width === 'flex' ? 'flex-1' : ''
                    ]"
                    :aria-sort="column.sortable ? (sortColumn === column.key ? (sortDirection === 'asc' ? 'ascending' : sortDirection === 'desc' ? 'descending' : 'none') : 'none') : undefined"
                    :tabindex="column.sortable ? 0 : undefined"
                    @click="handleSort(column)"
                    @keydown.enter="handleSort(column)"
                    @keydown.space.prevent="handleSort(column)">

                    <!-- Checkbox column -->
                    <template v-if="column.key === 'checkbox' && canEdit">
                        <input
                            type="checkbox"
                            :checked="allSelected"
                            :indeterminate="someSelected && !allSelected"
                            @click.stop
                            @change="handleSelectAll"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            aria-label="Select all tasks"
                        />
                    </template>

                    <!-- Regular column header -->
                    <template v-else-if="column.key !== 'checkbox'">
                        <div class="flex items-center gap-1">
                            <span>{{ column.label }}</span>
                            <template v-if="column.sortable">
                                <!-- Neutral sort icon -->
                                <svg v-if="getSortIcon(column) === 'neutral'"
                                     class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                </svg>
                                <!-- Ascending -->
                                <svg v-else-if="getSortIcon(column) === 'asc'"
                                     class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                </svg>
                                <!-- Descending -->
                                <svg v-else-if="getSortIcon(column) === 'desc'"
                                     class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </template>
                        </div>
                    </template>
                </th>
            </tr>
        </thead>
    `
};
