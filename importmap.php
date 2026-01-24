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
    'vue' => [
        'version' => '3.5.27',
    ],
    '@vue/runtime-dom' => [
        'version' => '3.5.27',
    ],
    '@vue/runtime-core' => [
        'version' => '3.5.27',
    ],
    '@vue/shared' => [
        'version' => '3.5.27',
    ],
    '@vue/reactivity' => [
        'version' => '3.5.27',
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
];
