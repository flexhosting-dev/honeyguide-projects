<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.21',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => '@symfony/stimulus-bundle/loader.js',
    ],
    'vue/index' => [
        'path' => './assets/vue/index.js',
    ],
    'vue/components/TagsEditor' => [
        'path' => './assets/vue/components/TagsEditor.js',
    ],
    'vue/components/ChecklistEditor' => [
        'path' => './assets/vue/components/ChecklistEditor.js',
    ],
    'vue/components/ActivityLog' => [
        'path' => './assets/vue/components/ActivityLog.js',
    ],
    'vue/components/CommentsEditor' => [
        'path' => './assets/vue/components/CommentsEditor.js',
    ],
    'vue/components/TaskCard' => [
        'path' => './assets/vue/components/TaskCard.js',
    ],
    'vue/components/KanbanBoard' => [
        'path' => './assets/vue/components/KanbanBoard.js',
    ],
    'vue/components/TaskCreateForm' => [
        'path' => './assets/vue/components/TaskCreateForm.js',
    ],
    'vue/components/RichTextEditor' => [
        'path' => './assets/vue/components/RichTextEditor.js',
    ],
    'vue/components/SubtasksEditor' => [
        'path' => './assets/vue/components/SubtasksEditor.js',
    ],
    'vue/components/QuickAddCard' => [
        'path' => './assets/vue/components/QuickAddCard.js',
    ],
    'vue/components/TaskTable' => [
        'path' => './assets/vue/components/TaskTable.js',
    ],
    'vue/components/TaskTable/TableHeader' => [
        'path' => './assets/vue/components/TaskTable/TableHeader.js',
    ],
    'vue/components/TaskTable/TaskRow' => [
        'path' => './assets/vue/components/TaskTable/TaskRow.js',
    ],
    'vue/components/TaskTable/ColumnConfig' => [
        'path' => './assets/vue/components/TaskTable/ColumnConfig.js',
    ],
    'vue/components/TaskTable/GroupRow' => [
        'path' => './assets/vue/components/TaskTable/GroupRow.js',
    ],
    'vue/components/TaskTable/EditableCell' => [
        'path' => './assets/vue/components/TaskTable/EditableCell.js',
    ],
    'vue/components/TaskTable/BulkActionBar' => [
        'path' => './assets/vue/components/TaskTable/BulkActionBar.js',
    ],
    'vue/components/TaskTable/TreeToggle' => [
        'path' => './assets/vue/components/TaskTable/TreeToggle.js',
    ],
    'vue/components/GanttView' => [
        'path' => './assets/vue/components/GanttView.js',
    ],
    'vue/components/ConfirmDialog' => [
        'path' => './assets/vue/components/ConfirmDialog.js',
    ],
    'vue' => [
        'path' => './assets/vendor/vue/vue.index.js',
    ],
];
