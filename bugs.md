# Known Bugs

## Task Panel Shows Editable Widgets Without Edit Permission

**Status:** Open
**Severity:** Medium
**Reported:** 2026-02-03

### Description
The task detail panel (loaded via AJAX at `/tasks/{id}/panel`) displays editable widgets (status dropdown, priority dropdown, date pickers, etc.) even for users who don't have edit permissions on the task/project.

### Expected Behavior
Users without `task.edit` permission should see read-only versions of all task fields (status badge without dropdown, plain text dates, no "Add" buttons for assignees/tags/checklists, etc.).

### Current Behavior
Edit controls are visible and interactive regardless of user permissions. The backend correctly rejects unauthorized edit attempts with 403, but the UI should not show edit controls in the first place.

### Technical Details
- The controller passes `canEdit` based on `TASK_EDIT` permission check
- The Twig template `_panel.html.twig` has conditional rendering based on `canEdit`
- Vue components receive `canEdit` via `data-vue-can-edit` attribute
- The Vue prop parsing in `assets/vue/index.js` converts string "true"/"false" to boolean

### Files Involved
- `src/Controller/TaskController.php` - `panel()` method sets `canEdit`
- `templates/task/_panel.html.twig` - Conditional rendering logic
- `templates/task/_checklist_vue.html.twig` - Passes `canEdit` to Vue
- `templates/task/_tags_vue.html.twig` - Passes `canEdit` to Vue
- `templates/task/_description_vue.html.twig` - Passes `canEdit` to Vue
- `assets/vue/index.js` - Parses data attributes for Vue components
- `assets/vue/components/*.js` - Components should respect `canEdit` prop

### Possible Causes
1. Symfony template cache not cleared after code changes
2. Browser caching AJAX responses
3. Asset compilation not run after JS changes
4. Vue component not properly receiving/using `canEdit` prop

### Workaround
Clear all caches:
```bash
php bin/console cache:clear
npm run build  # or npm run dev
```
Hard refresh browser (Ctrl+Shift+R / Cmd+Shift+R)

### Investigation Steps
1. Check browser DevTools Network tab - verify the AJAX response includes `data-vue-can-edit="false"`
2. Check Vue DevTools - verify the component props show `canEdit: false`
3. Add console.log in Vue components to debug prop values

---

## Checklist Reordering Not Functional

**Status:** Fixed
**Severity:** Medium
**Reported:** 2026-02-03
**Fixed:** 2026-02-03

### Description
Checklist items cannot be reordered via drag-and-drop. The drag handle icon appears but dragging does nothing.

### Root Cause
The drag handle icon was purely cosmetic - no actual drag-and-drop functionality was implemented.

### Fix Applied
Implemented HTML5 drag-and-drop in `ChecklistEditor.js`:
- Added drag state tracking (`draggedItem`, `dragOverIndex`)
- Added drag event handlers (`handleDragStart`, `handleDragEnd`, `handleDragOver`, `handleDragLeave`, `handleDrop`)
- Items are now draggable with visual feedback (opacity change, drop indicator)
- On drop, local state is updated and `POST /tasks/{taskId}/checklists/reorder` is called to persist order
