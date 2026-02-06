# Future Features

This document outlines planned enhancements and architectural improvements for WorkFlow.

---

## 1. Centralized Task Store Architecture

**Priority:** High
**Complexity:** Medium
**Impact:** Improves maintainability and enables real-time sync across views

### Problem
Currently, each task view (kanban, list, panel) manages its own state and syncs via DOM events. This leads to:
- Duplicated update logic across views
- Manual DOM manipulation for Twig views
- Fragile event-based synchronization
- Hard to extend with new views

### Solution
Implement a centralized task store using Vue 3's native `reactive()` API as a single source of truth.

### Store Design
```javascript
const taskStore = {
    state: reactive({
        tasks: new Map(),      // O(1) lookups by ID
        isHydrated: false,
        basePath: ''
    }),

    getters: {
        getById(id),
        getByProject(projectId),
        groupedByStatus,       // For kanban status mode
        groupedByPriority,     // For kanban priority mode
        groupedByMilestone     // For kanban milestone mode
    },

    actions: {
        hydrate(tasks),
        createTask(projectId, data),
        updateStatus(taskId, status),
        updatePriority(taskId, priority),
        // ... other CRUD operations
    }
}
```

### Key Features
- **Optimistic Updates**: UI updates immediately, rollback on API error
- **Hydration**: Initialize from server-rendered JSON on page load
- **Bridge Pattern**: Emit DOM events for backward compatibility with Twig views
- **Grouped Getters**: Computed properties for kanban columns

### Implementation Phases
1. Create store foundation (`assets/vue/stores/taskStore.js`)
2. Add hydration from Twig templates
3. Migrate KanbanBoard component to use store
4. Migrate TaskCreateForm to use store
5. Connect task panel via event bridge
6. Migrate remaining Vue components
7. Clean up legacy event listeners

### Files Affected
- New: `assets/vue/stores/taskStore.js`
- Modified: `importmap.php`, `assets/app.js`, `assets/vue/index.js`
- Modified: `KanbanBoard.js`, `TaskCreateForm.js`
- Modified: `templates/project/show.html.twig`, `templates/task/index.html.twig`

---

## 2. Real-Time Collaboration with WebSockets

**Priority:** Medium
**Complexity:** High
**Impact:** Enables true multi-user real-time collaboration

### Problem
Currently, changes made by one user are not visible to other users until they refresh the page. In a team environment, this leads to:
- Stale data when multiple users work on the same project
- Potential conflicts when two users edit the same task
- No awareness of who else is viewing/editing

### Solution
Implement WebSocket-based real-time updates using Symfony Mercure or a custom WebSocket server.

### Ideal Use Cases

1. **Live Kanban Board Updates**
   - When User A drags a task to "In Progress", User B sees it move instantly
   - Team standup meetings with shared kanban view
   - Project managers monitoring task progress in real-time

2. **Collaborative Task Editing**
   - See when another user is viewing/editing the same task
   - Real-time comment notifications
   - Live checklist updates during pair work

3. **Presence Indicators**
   - Show who's currently viewing a project
   - Display "User X is typing..." in comments
   - Avatar indicators on tasks being edited

4. **Instant Notifications**
   - Task assignment notifications appear immediately
   - @mention alerts in real-time
   - Due date reminders pushed to active users

5. **Activity Feed Updates**
   - Project activity feed updates live
   - Dashboard shows real-time team activity
   - No need to refresh to see latest changes

### Technical Approach

**Option A: Symfony Mercure (Recommended)**
```php
// Server-side: Publish update
$update = new Update(
    'project/'.$projectId.'/tasks',
    json_encode(['type' => 'task-updated', 'task' => $taskData])
);
$hub->publish($update);
```

```javascript
// Client-side: Subscribe to updates
const eventSource = new EventSource(mercureUrl);
eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    taskStore.mutations.updateTask(data.task.id, data.task);
};
```

**Option B: Native WebSockets with Ratchet**
- More control but requires separate WebSocket server
- Better for high-frequency updates (typing indicators)

### Implementation Phases
1. Set up Mercure hub (or WebSocket server)
2. Create subscription manager for projects/tasks
3. Publish events on task CRUD operations
4. Subscribe to channels on page load
5. Update store when events received
6. Add presence tracking
7. Implement typing indicators for comments

### Prerequisites
- Centralized Task Store (Feature #1) should be implemented first
- Store becomes the single point for receiving WebSocket updates

### Files Affected
- New: `src/Service/RealtimePublisher.php`
- New: `assets/js/websocket.js` or Mercure client
- Modified: `TaskController.php` - publish on changes
- Modified: `taskStore.js` - subscribe to updates
- Config: `config/packages/mercure.yaml`

---

## 3. Subtasks — Remaining Enhancements

**Priority:** Medium
**Complexity:** Medium

Core subtask functionality is implemented (creation, stacking panel navigation, parent chain display, depth enforcement). The following features remain.

### Completion Enhancements
- Warning when completing a parent that has open subtasks
- "Complete all subtasks" bulk action on parent task

### Deletion Confirmation
- Show subtask count in delete confirmation dialog (cascade delete already works at DB level)

### Promotion & Demotion
- Promote a subtask to a root-level task (remove parent)
- Demote a root task to become a subtask of another task
- Move a subtask to a different parent
- API endpoint: `PATCH /tasks/{id}/parent`
- Circular reference prevention when re-parenting

---

## 4. Task Filters (Side Panel)

**Priority:** High
**Complexity:** Low-Medium
**Impact:** Significantly improves task discovery and management in large projects

### Overview
Add a collapsible filter panel on the side of task views (list, kanban) allowing users to filter tasks by multiple criteria. Filters persist in URL for shareability and bookmarking.

### Filter Criteria

| Filter | Type | Options |
|--------|------|---------|
| **Status** | Multi-checkbox | To Do, In Progress, In Review, Completed |
| **Priority** | Multi-checkbox | High, Medium, Low, None |
| **Assignee** | Multi-select dropdown | Project members + "Unassigned" |
| **Milestone** | Multi-select dropdown | Project milestones + "No milestone" |
| **Due Date** | Radio + date picker | Presets + custom range |
| **Tags** | Multi-select dropdown | Project tags |
| **Created By** | Single-select dropdown | Project members |
| **Date Created** | Date range picker | From/To dates |
| **Has Subtasks** | Checkbox | Yes/No |
| **Search** | Text input | Title and description search |

### Features

1. **Collapsible Panel**
   - Toggle button in header: `[Filter v]` / `[Filter ^]`
   - Remembers open/closed state (localStorage)
   - Shows active filter count: `[Filter (3) v]`

2. **URL Persistence**
   - Filters encoded in URL query params
   - Shareable filtered views
   - Browser back/forward works
   ```
   /projects/123/tasks?status=todo,in_progress&priority=high&assignee=user-456
   ```

3. **Saved Filters (Presets)**
   - Save current filter combination with a name
   - Quick access dropdown: "My Overdue", "High Priority", "Unassigned"
   - Personal vs shared (project-level) presets

4. **Active Filters Display**
   - Chips above task list showing active filters
   - Click chip to remove that filter
   ```
   Active: [Status: To Do x] [Priority: High x] [Assignee: John x] [Clear All]
   ```

5. **Filter Counts**
   - Show count next to each option
   - Updates dynamically as other filters change
   ```
   Status
   [x] To Do (12)
   [x] In Progress (5)
   [ ] Completed (23)
   ```

6. **Quick Filters (Header Shortcuts)**
   - Common filters as buttons above task view
   ```
   [My Tasks] [Overdue] [Due This Week] [Unassigned] [More Filters...]
   ```

### Technical Implementation

**URL Query Parameters:**
```
?status=todo,in_progress
&priority=high,medium
&assignee=uuid1,uuid2
&milestone=uuid1
&due=overdue|today|week|month|none|2026-01-01,2026-01-31
&tags=uuid1,uuid2
&search=keyword
&created_after=2026-01-01
&created_before=2026-01-31
```

**Controller:**
```php
#[Route('/projects/{id}/tasks', name: 'app_project_tasks')]
public function tasks(Project $project, Request $request): Response
{
    $filters = TaskFilterDTO::fromRequest($request);
    $tasks = $this->taskRepository->findByFilters($project, $filters);

    return $this->render('project/_tasks.html.twig', [
        'tasks' => $tasks,
        'filters' => $filters,
        'filter_options' => $this->getFilterOptions($project),
    ]);
}
```

**Repository:**
```php
public function findByFilters(Project $project, TaskFilterDTO $filters): array
{
    $qb = $this->createQueryBuilder('t')
        ->where('t.milestone IN (SELECT m FROM Milestone m WHERE m.project = :project)')
        ->setParameter('project', $project);

    if ($filters->statuses) {
        $qb->andWhere('t.status IN (:statuses)')
           ->setParameter('statuses', $filters->statuses);
    }

    if ($filters->priorities) {
        $qb->andWhere('t.priority IN (:priorities)')
           ->setParameter('priorities', $filters->priorities);
    }

    if ($filters->assignees) {
        $qb->join('t.assignees', 'a')
           ->andWhere('a.user IN (:assignees)')
           ->setParameter('assignees', $filters->assignees);
    }

    // ... more filter conditions

    return $qb->getQuery()->getResult();
}
```

### Implementation Phases

1. **Filter DTO & Repository**
   - Create `TaskFilterDTO` class
   - Implement `findByFilters()` repository method
   - Add filter options endpoint

2. **Filter Panel UI**
   - Create collapsible side panel component
   - Checkbox groups for status/priority
   - Multi-select dropdowns for assignee/milestone/tags

3. **URL Sync**
   - Parse filters from URL on load
   - Update URL when filters change
   - Handle browser back/forward

4. **Active Filters Display**
   - Chips component above task list
   - Remove individual filter on chip click

5. **Saved Filters**
   - FilterPreset entity (user, project, name, filters JSON)
   - CRUD for presets
   - Dropdown to apply saved filters

6. **Client-Side Filtering (Optional)**
   - For instant feedback without API calls
   - Combine with server-side for pagination

### Files Affected

**Backend:**
- New: `src/DTO/TaskFilterDTO.php`
- New: `src/Entity/FilterPreset.php` (for saved filters)
- Modified: `src/Repository/TaskRepository.php` - Filter query builder
- Modified: `src/Controller/TaskController.php` - Accept filter params
- New: `src/Controller/FilterPresetController.php` - Saved filters CRUD

**Frontend:**
- New: `templates/task/_filters.html.twig` - Filter panel template
- New: `assets/vue/components/TaskFilters.js` - Vue filter component (optional)
- Modified: `templates/project/show.html.twig` - Include filter panel
- Modified: `templates/task/index.html.twig` - Include filter panel (My Tasks)
- New: `assets/js/filters.js` - URL sync, client-side filtering

### Mobile Considerations

- Filter panel becomes full-screen modal on mobile
- "Filter" button in header opens modal
- Sticky "Apply Filters" button at bottom
- Collapse sections by default on mobile

---

## 5. Advanced Task Table View

**Priority:** High
**Complexity:** Medium-High
**Impact:** Power-user productivity, spreadsheet-like task management

### Overview
Add a full-featured datatable view for tasks with spreadsheet-like capabilities: sortable/resizable columns, column visibility toggle, instant search, grouping, inline editing, and quick task creation anywhere in the table.

### Core Features

#### 1. Column Management

**Available Columns:**
| Column | Default | Sortable | Editable | Width |
|--------|---------|----------|----------|-------|
| Checkbox (select) | Yes | -- | -- | 40px |
| Task Title | Yes | Yes | Yes | flex |
| Status | Yes | Yes | Yes | 120px |
| Priority | Yes | Yes | Yes | 100px |
| Assignee(s) | Yes | Yes | Yes | 150px |
| Due Date | Yes | Yes | Yes | 100px |
| Start Date | No | Yes | Yes | 100px |
| Tags | Yes | -- | Yes | 150px |
| Milestone | No | Yes | Yes | 150px |
| Created | No | Yes | -- | 100px |
| Updated | No | Yes | -- | 100px |
| Created By | No | Yes | -- | 120px |
| Progress | No | Yes | -- | 100px |
| Subtasks | No | Yes | -- | 80px |
| Comments | No | -- | -- | 80px |
| Description | No | -- | Yes | 200px |

**Column Features:**
- Drag to reorder columns
- Drag column edge to resize
- Click header to sort (asc/desc/none)
- Double-click edge to auto-fit width
- Column config saved to localStorage

#### 2. Instant Search

- Real-time filtering (client-side for loaded data)
- Highlights search matches
- Combines with active filters
- Clear button to reset

#### 3. Row Grouping

**Group By Options:**
- None
- Milestone
- Status
- Priority
- Assignee
- Due Date (This Week, Next Week, Later, None)

**Group Header Features:**
- Collapsible (click to expand/collapse)
- Task count and completion stats
- Bulk actions on group
- Quick "Add task" button per group
- Drag tasks between groups

#### 4. Inline Editing

**Edit Modes by Column:**
| Column | Edit Control |
|--------|--------------|
| Title | Text input |
| Description | Textarea (expandable) |
| Status | Dropdown |
| Priority | Dropdown |
| Assignee | Multi-select with search |
| Due/Start Date | Date picker |
| Tags | Tag selector with create |
| Milestone | Dropdown |

**Keyboard Navigation:**
- Tab: Move to next editable cell
- Shift+Tab: Move to previous cell
- Enter: Save and move down
- Esc: Cancel edit
- Arrow keys: Navigate cells (when not editing)

#### 5. Quick Add Row

- Empty row at end of each group
- Click or Tab into it to start typing
- Enter creates task with:
  - Title from input
  - Milestone from current group (if grouped by milestone)
  - Status from current group (if grouped by status)
  - Default priority: None
- Shift+Enter: Create and add another
- Tab through cells to set more fields before saving

#### 6. Bulk Actions

**Row Selection:**
- Checkbox column for multi-select
- Click row (outside cells) to select
- Shift+Click for range select
- Ctrl/Cmd+Click for toggle select
- Header checkbox: Select all visible

**Bulk Action Bar (appears when rows selected):**
- Set status for all selected
- Set priority for all selected
- Assign/unassign users
- Set milestone
- Add tags
- Delete (with confirmation)

#### 7. Subtask Tree View

**Tree Structure:**
- Tasks with subtasks display a `[+]` toggle in the Title column
- Clicking `[+]` expands to show child tasks as indented rows
- Toggle changes to `[-]` when expanded (click to collapse)
- Tree lines (│, ├, └) show hierarchical relationships
- Unlimited nesting depth supported

**Visual Example:**
```
[+] Parent Task 1
[-] Parent Task 2
 │  ├── Subtask 2.1
 │  │   └── Sub-subtask 2.1.1
 │  └── Subtask 2.2
[+] Parent Task 3
    Task 4 (no children)
```

**Tree Behavior:**
- Expand/collapse state persisted to localStorage per user
- "Expand All" / "Collapse All" buttons in table toolbar
- Keyboard: Right arrow expands, Left arrow collapses
- Indent level: 24px per depth level
- Tree lines use CSS borders (`:before` pseudo-elements)

**Interaction with Other Features:**
- **Grouping**: Tree hierarchy shown within each group
- **Sorting**: Subtasks stay under their parent, sorted among siblings
- **Search**: When a subtask matches, auto-expand parents to show it
- **Quick Add**: Option to add subtask via row context menu or `Tab` from parent
- **Bulk Select**: Option to "Select with children" for parent tasks
- **Drag & Drop**: Drag tasks to reparent (drop on task to make it child)

**Performance:**
- Lazy load children on first expand (if not already loaded)
- Virtual scrolling accounts for expanded/collapsed state
- Cache expanded subtasks to avoid re-fetching

### Implementation Phases

1. **Basic Table Structure**
   - Vue component with static columns
   - Render tasks in rows
   - Basic sorting

2. **Column Management**
   - Visibility toggle dropdown
   - Column reordering (drag)
   - Column resizing
   - Persist to localStorage

3. **Row Grouping**
   - Group by milestone/status/priority
   - Collapsible group headers
   - Group stats (count, completion)

4. **Inline Editing**
   - Click-to-edit cells
   - Different editors per column type
   - Keyboard navigation
   - Optimistic updates

5. **Quick Add**
   - Inline add task row
   - Add milestone row
   - Auto-inherit group properties

6. **Bulk Actions**
   - Row selection (single, multi, range)
   - Bulk action bar
   - Bulk update API

7. **Search & Filter Integration**
   - Instant search
   - Connect to filter panel
   - Highlight matches

8. **Subtask Tree View**
   - Expand/collapse toggles (+/-)
   - Tree lines and indentation CSS
   - Lazy load children on expand
   - Persist expand state

9. **Performance**
   - Virtual scrolling for large lists
   - Lazy load groups and subtrees
   - Debounced updates

### Files Affected

**Backend:**
- New: `src/Controller/TaskTableController.php` - Table-specific endpoints
- Modified: `src/Controller/TaskController.php` - Quick update, bulk update
- New: `src/DTO/BulkUpdateDTO.php`
- Modified: `src/Entity/User.php` - Table preferences (JSON column)

**Frontend:**
- New: `assets/vue/components/TaskTable.js` - Main table component
- New: `assets/vue/components/TaskTable/TableHeader.js`
- New: `assets/vue/components/TaskTable/TaskRow.js`
- New: `assets/vue/components/TaskTable/EditableCell.js`
- New: `assets/vue/components/TaskTable/GroupRow.js`
- New: `assets/vue/components/TaskTable/TreeToggle.js` - Expand/collapse +/- control
- New: `assets/vue/components/TaskTable/BulkActionBar.js`
- New: `assets/vue/components/TaskTable/ColumnConfig.js`
- New: `templates/task/_table_vue.html.twig` - Mount point
- Modified: `templates/project/show.html.twig` - Add Table view option
- New: `assets/css/task-table.css` - Table-specific styles (includes tree lines)

### Accessibility

- Full keyboard navigation
- ARIA roles (grid, row, gridcell, columnheader, treegrid for subtask view)
- `aria-expanded` on tree toggles
- Screen reader announcements for edits and expand/collapse
- Focus management when editing
- High contrast support for tree lines

### Mobile Considerations

- Simplified view on mobile (fewer columns)
- Horizontal scroll with sticky first column
- Touch-friendly edit controls
- Consider card view alternative on very small screens

### Context Menu (Right-Click Actions)

Add a right-click context menu for quick task actions without opening the task panel.

#### Pros

| Pro | Reason |
|-----|--------|
| **Faster workflow** | No need to open task panel for quick actions |
| **Power user friendly** | Expected behavior in desktop apps (Excel, Jira, Asana) |
| **Discoverability** | Shows available actions at a glance |
| **Bulk efficiency** | Right-click on selection to act on multiple tasks |
| **Reduces clicks** | Status change: 1 click vs open panel → find dropdown → select → close |

#### Cons

| Con | Mitigation |
|-----|------------|
| **Mobile unfriendly** | Long-press (500ms) triggers same menu |
| **Discoverability paradox** | Add subtle hint on first use |
| **Maintenance** | Share action handlers with inline edit and bulk actions |
| **Accessibility** | Support keyboard trigger (Shift+F10 or context menu key) |

#### Single Task Menu

```
┌─────────────────────────┐
│ ✏️  Edit Task            │
│ 🔗  Copy Link            │
├─────────────────────────┤
│ Status            ▶     │  → submenu with status options
│ Priority          ▶     │  → submenu with priority options
│ Assign to         ▶     │  → submenu with team members
│ Set Due Date            │  → opens date picker
│ Move to Milestone ▶     │  → submenu with milestones
├─────────────────────────┤
│ ➕  Add Subtask          │
│ 📋  Duplicate            │
├─────────────────────────┤
│ 🗑️  Delete               │
└─────────────────────────┘
```

#### Multi-Select Menu

When multiple tasks are selected:

```
┌─────────────────────────┐
│ 3 tasks selected        │  ← header showing count
├─────────────────────────┤
│ Set Status        ▶     │
│ Set Priority      ▶     │
│ Assign to         ▶     │
│ Move to Milestone ▶     │
├─────────────────────────┤
│ 🗑️  Delete All          │
└─────────────────────────┘
```

#### Implementation Details

**Component:** `assets/vue/components/TaskTable/ContextMenu.js`

**Triggers:**
- Right-click (`@contextmenu.prevent`) on task row
- Keyboard: Context menu key or Shift+F10 on focused row
- Mobile: Long-press (500ms hold)

**Positioning:**
- Appears at cursor position
- Flips up/left if near viewport edge
- Z-index above all table elements

**Closing:**
- Click outside menu
- Escape key
- Action selected
- Scroll

**Submenus:**
- Open on hover (desktop) or tap (mobile)
- Show current value with checkmark
- Optimistic update on selection

#### Files Affected

- New: `assets/vue/components/TaskTable/ContextMenu.js`
- Modified: `assets/vue/components/TaskTable/TaskRow.js` - Add context menu trigger
- Modified: `assets/vue/components/TaskTable.js` - Context menu state and handlers
- Modified: `assets/css/task-table.css` - Context menu styling

---

## 6. User Notifications System

**Priority:** High
**Complexity:** Medium
**Impact:** Keeps users informed and engaged with project activity

### Overview
Comprehensive notification system with in-app notifications, optional email notifications, and user-customizable preferences for notification types and delivery methods.

### Notification Types

| Event | Description | Default In-App | Default Email |
|-------|-------------|----------------|---------------|
| **Task Assigned** | You were assigned to a task | Yes | Yes |
| **Task Unassigned** | You were removed from a task | Yes | No |
| **Task Completed** | A task you're assigned to was completed | Yes | No |
| **Task Due Soon** | Task due in 24/48 hours | Yes | Yes |
| **Task Overdue** | Task is past due date | Yes | Yes |
| **Comment Added** | New comment on your task | Yes | Yes |
| **@Mentioned** | Someone mentioned you | Yes | Yes |
| **Comment Reply** | Reply to your comment | Yes | No |
| **Project Invited** | Added to a project | Yes | Yes |
| **Project Removed** | Removed from a project | Yes | Yes |
| **Milestone Due** | Milestone due soon | Yes | No |
| **Task Status Changed** | Status change on your task | Yes | No |
| **Attachment Added** | File added to your task | No | No |
| **Subtask Completed** | Subtask of your task completed | No | No |

### In-App Notifications UI

**Notification Bell (Header):**
- Badge shows unread count
- Dropdown shows recent notifications
- Click notification to navigate
- Mark as read on view
- "Mark all read" button

**Full Notifications Page (`/notifications`):**
- Filter by type (All, Unread, Mentions, Assignments, Comments, Due Dates)
- Pagination for history
- Settings link

### User Notification Preferences

**Settings Page (`/settings/notifications`):**
- Email Frequency: Instant, Daily Digest, Weekly Digest, Never
- Per-type toggles for In-App and Email
- Reset to defaults option

### Email Notifications

**Email Frequency Options:**
1. **Instant** - Email sent immediately (via queue)
2. **Daily Digest** - All notifications compiled into one daily email
3. **Weekly Digest** - Weekly summary email
4. **Never** - No emails, in-app only

### Implementation Phases

1. **Core Notification System**
   - Notification entity and migration
   - NotificationService
   - Create notifications on events (assigned, commented, etc.)
   - Repository queries

2. **In-App UI**
   - Notification bell component
   - Dropdown list
   - Unread badge counter
   - Mark as read functionality
   - Full notifications page

3. **User Preferences**
   - Preferences storage (JSON on User or separate entity)
   - Settings page UI
   - Apply preferences in NotificationService

4. **Email - Instant**
   - Email templates (Twig)
   - Queue system (Symfony Messenger)
   - Send emails for enabled notification types

5. **Email - Digests**
   - Scheduled command for daily/weekly digests
   - Compile notifications into digest
   - Track last digest sent

6. **Real-Time (with WebSocket feature)**
   - Push notifications to connected clients
   - Update badge count instantly
   - Toast popup for new notifications

### Cleanup & Retention

- Auto-delete read notifications after 30 days
- Auto-delete all notifications after 90 days
- Scheduled cleanup command
- User can manually clear all notifications

### Files Affected

**Backend:**
- New: `src/Entity/Notification.php`
- New: `src/Repository/NotificationRepository.php`
- New: `src/Service/NotificationService.php`
- New: `src/Controller/NotificationController.php`
- New: `src/Controller/NotificationSettingsController.php`
- New: `src/Message/SendNotificationEmail.php`
- New: `src/MessageHandler/SendNotificationEmailHandler.php`
- New: `src/Command/SendDigestEmailsCommand.php`
- New: `migrations/VersionXXX.php`
- Modified: `src/Entity/User.php` - Add preferences
- Modified: `src/Controller/TaskController.php` - Trigger notifications
- Modified: `src/Controller/CommentController.php` - Trigger notifications
- New: `templates/email/notification/*.html.twig` - Email templates

**Frontend:**
- New: `assets/vue/components/NotificationBell.js`
- New: `assets/vue/components/NotificationDropdown.js`
- New: `assets/vue/components/NotificationItem.js`
- New: `templates/notification/index.html.twig` - Full page
- New: `templates/settings/notifications.html.twig` - Preferences
- Modified: `templates/layout.html.twig` - Add bell to header

---

## 7. CSV Import & Export

**Priority:** Medium
**Complexity:** Medium
**Impact:** Enables bulk task creation, migration from other tools, and data portability

### Overview
Allow users to import tasks from CSV files and export existing tasks to CSV. This is essential for migrating from spreadsheets, other project management tools, or bulk task creation workflows.

### Import Features

#### 1. Import Wizard (Multi-Step)

**Step 1: File Upload**
- Drag-and-drop or file picker
- Accept `.csv` and `.xlsx` files
- File size limit: 5MB
- Preview first 5 rows immediately

**Step 2: Column Mapping**
- Auto-detect common column names (Title, Status, Due Date, etc.)
- Manual mapping dropdown for each detected column
- Required fields: Title (minimum)
- Optional fields: Status, Priority, Due Date, Start Date, Assignee, Tags, Description, Milestone

**Step 3: Data Preview & Validation**
- Show preview table with mapped data
- Highlight validation errors (invalid dates, unknown assignees, etc.)
- Row-by-row error messages
- Option to skip invalid rows or fix inline
- Count: "X tasks ready to import, Y rows with errors"

**Step 4: Import Options**
- Target project selection
- Target milestone (or create new)
- Default status for imported tasks
- Default priority for imported tasks
- Duplicate handling: Skip, Update, Create Duplicate

**Step 5: Import Summary**
- Progress bar during import
- Results: X imported, Y skipped, Z errors
- Download error log CSV
- Link to view imported tasks

#### 2. Supported Fields

| CSV Column | Maps To | Format | Required |
|------------|---------|--------|----------|
| Title | task.title | Text | Yes |
| Description | task.description | Text | No |
| Status | task.status | `todo`, `in_progress`, `in_review`, `completed` | No |
| Priority | task.priority | `none`, `low`, `medium`, `high` | No |
| Due Date | task.dueDate | `YYYY-MM-DD` or `MM/DD/YYYY` | No |
| Start Date | task.startDate | `YYYY-MM-DD` or `MM/DD/YYYY` | No |
| Assignee | task.assignees | Email or full name (comma-separated for multiple) | No |
| Tags | task.tags | Comma-separated tag names | No |
| Milestone | task.milestone | Milestone name (creates if not exists) | No |
| Parent Task | task.parent | Title of parent task (for subtasks) | No |
| Checklist | task.checklist | Semicolon-separated checklist items | No |

#### 3. Downloadable Templates

**CSV Template:**
- Header row with all supported columns
- 3 example rows with valid data
- Comments explaining formats

```csv
Title,Description,Status,Priority,Due Date,Assignee,Tags,Milestone
"Set up dev environment","Install dependencies and configure",todo,high,2026-02-15,john@example.com,"setup,backend",Sprint 1
"Design login page","Create wireframes and mockups",in_progress,medium,2026-02-10,"jane@example.com,bob@example.com","design,frontend",Sprint 1
"Write API docs",,todo,low,2026-02-20,,"documentation",Sprint 2
```

**Excel Template (.xlsx) with Smart Dropdowns:**

When user downloads the Excel template, use Excel's Data Validation feature to provide dropdown lists for fields with predefined options. Dropdowns are populated based on user's permissions and project context.

| Column | Dropdown Options |
|--------|------------------|
| Status | `To Do`, `In Progress`, `In Review`, `Completed` |
| Priority | `None`, `Low`, `Medium`, `High` |
| Project | User's accessible projects (if importing to multiple) |
| Milestone | Milestones from selected project(s) |
| Assignee | Project members the user can assign to |
| Tags | Existing tags from selected project(s) |

**Implementation Details:**
- Use PhpSpreadsheet's `DataValidation` class to create dropdown lists
- Populate options dynamically when template is generated
- Store reference data in a hidden "Options" sheet for longer lists
- Named ranges for easy maintenance

```php
// Example: Adding Status dropdown to column C
$validation = $sheet->getCell('C2')->getDataValidation();
$validation->setType(DataValidation::TYPE_LIST);
$validation->setFormula1('"To Do,In Progress,In Review,Completed"');
$validation->setShowDropDown(true);

// For longer lists (e.g., project members), use named range
$optionsSheet->fromArray($memberNames, null, 'A1');
$spreadsheet->addNamedRange(new NamedRange('Assignees', $optionsSheet, 'A1:A' . count($memberNames)));
$validation->setFormula1('Assignees');
```

**Benefits:**
- Reduces data entry errors
- Users see valid options without leaving Excel
- Faster bulk data entry
- Consistent data formatting

### Export Features

#### 1. Export Options

- Export all tasks in project
- Export filtered tasks (from current filter)
- Export selected tasks (from table view)
- Export tasks from milestone

#### 2. Export Formats

- **CSV**: Standard comma-separated values
- **Excel (.xlsx)**: With formatting and multiple sheets

#### 3. Exportable Fields

All task fields plus:
- Created Date
- Updated Date
- Created By
- Subtask Count
- Comment Count
- Checklist Progress

#### 4. Export Customization

- Select which columns to include
- Choose date format
- Include/exclude completed tasks
- Include/exclude subtasks

### Import API

```php
// POST /projects/{id}/import
// Content-Type: multipart/form-data
// Body: file (CSV), mapping (JSON)

// Response
{
    "success": true,
    "imported": 45,
    "skipped": 3,
    "errors": [
        {"row": 12, "field": "dueDate", "message": "Invalid date format"},
        {"row": 23, "field": "assignee", "message": "User not found: unknown@email.com"}
    ]
}
```

### Export API

```php
// GET /projects/{id}/export?format=csv&fields=title,status,priority,dueDate

// Response: File download with appropriate Content-Type
```

### Implementation Phases

1. **Backend CSV Parser**
   - CSV parsing service with encoding detection
   - Field validation and transformation
   - Batch insert for performance

2. **Import Wizard UI**
   - Step 1: File upload with drag-drop
   - Step 2: Column mapper component
   - Step 3: Preview table with validation
   - Step 4: Options form
   - Step 5: Progress and results

3. **Export Functionality**
   - Export service with field selection
   - CSV writer
   - Download endpoint

4. **Excel Support**
   - Add PhpSpreadsheet dependency
   - Excel parsing for import
   - Excel generation for export

5. **Template Download**
   - Generate sample CSV dynamically
   - Include project-specific milestones/tags

### Error Handling

- **File Errors**: Too large, wrong format, encoding issues
- **Mapping Errors**: Required field not mapped
- **Validation Errors**: Invalid values, unknown users
- **Import Errors**: Database failures, constraint violations

All errors should be user-friendly and actionable.

### Files Affected

**Backend:**
- New: `src/Service/CsvImportService.php`
- New: `src/Service/CsvExportService.php`
- New: `src/DTO/ImportMappingDTO.php`
- New: `src/DTO/ImportResultDTO.php`
- New: `src/Controller/ImportExportController.php`
- Modified: `composer.json` - Add `phpoffice/phpspreadsheet`

**Frontend:**
- New: `templates/import/wizard.html.twig`
- New: `assets/vue/components/ImportWizard.js`
- New: `assets/vue/components/ImportWizard/FileUpload.js`
- New: `assets/vue/components/ImportWizard/ColumnMapper.js`
- New: `assets/vue/components/ImportWizard/PreviewTable.js`
- New: `assets/vue/components/ImportWizard/ImportOptions.js`
- New: `assets/vue/components/ImportWizard/ImportResults.js`
- Modified: `templates/project/show.html.twig` - Add Import/Export buttons
- New: `templates/export/options.html.twig` - Export options modal

### Security Considerations

- Validate file type and content (not just extension)
- Sanitize all imported text fields
- Limit import size to prevent DoS
- Rate limit import endpoint
- Audit log for imports (who imported what, when)
- Permission check: Only project managers can import

### UX Considerations

- Show progress for large imports
- Allow cancellation during import
- Preserve partially imported data on error
- Email notification when large import completes
- Import history page showing past imports

---

## 8. Additional Future Considerations

### Offline Support (Service Workers)
- Cache tasks locally for offline viewing
- Queue changes when offline, sync when back online
- Useful for mobile users with spotty connections

### Task Dependencies
- Block tasks until dependent tasks complete
- Gantt chart visualization
- Critical path highlighting

### Time Tracking
- Start/stop timer on tasks
- Time estimates vs actual tracking
- Timesheet reports

### Recurring Tasks
See Feature #10 below for full specification.

### Advanced Search
- Full-text search across all tasks
- Filter by multiple criteria
- Saved searches/filters

### Dark Mode
- System preference detection (`prefers-color-scheme`)
- Manual toggle in user settings (Light / Dark / System)
- Persist preference to user profile
- CSS custom properties for theme colors
- Smooth transition between themes
- Ensure sufficient contrast ratios for accessibility

---

## 9. Customizable Task Statuses

**Priority:** Medium
**Complexity:** Medium
**Impact:** Enables organizations to tailor workflow stages to their specific processes

### Overview
Allow portal administrators to customize the task status types available throughout the system. Each custom status must be mapped to a parent type of either "Open" or "Closed", enabling proper workflow tracking while supporting diverse business processes.

### Core Concepts

#### Parent Status Types
All custom statuses inherit from one of two fundamental types:

| Parent Type | Meaning | Examples |
|-------------|---------|----------|
| **Open** | Task is not yet complete, still requires action | To Do, In Progress, In Review, On Hold, Blocked, Waiting for Input |
| **Closed** | Task is finished, no further action needed | Completed, Done, Cancelled, Dropped, Won't Fix, Duplicate |

#### Why Parent Types Matter
- **Progress Calculation**: Only tasks with "Closed" parent status count toward completion percentage
- **Filters**: "Show open tasks" filter uses parent type, not individual status
- **Reports**: Burndown charts, velocity metrics based on Open → Closed transitions
- **Kanban**: Can visually separate Open vs Closed columns

### Default Statuses

System ships with these default statuses (can be modified/deleted by admin):

| Status | Parent Type | Color | Icon |
|--------|-------------|-------|------|
| To Do | Open | Gray | circle-outline |
| In Progress | Open | Blue | play-circle |
| In Review | Open | Purple | eye |
| Completed | Closed | Green | check-circle |

### Admin Configuration UI

**Location:** Portal Settings → Task Statuses (`/settings/statuses`)

#### Status List View
- Table showing all statuses with: Name, Parent Type, Color, Icon, Task Count, Actions
- Drag handle to reorder (affects dropdown order)
- Add Status button
- Cannot delete status if tasks are using it (must reassign first)

#### Add/Edit Status Modal

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Name | Text input | Yes | Max 50 chars, unique |
| Parent Type | Radio buttons | Yes | Open / Closed |
| Color | Color picker | Yes | Preset colors + custom hex |
| Icon | Icon picker | No | Optional status icon |
| Description | Textarea | No | Help text shown in tooltips |

#### Reordering
- Drag-and-drop to reorder statuses
- Order determines display in dropdowns and kanban columns
- Separate ordering for Open and Closed groups

#### Deleting a Status
1. Check if any tasks use this status
2. If yes: Show modal "X tasks use this status. Reassign to:" with dropdown
3. Bulk update tasks to new status
4. Delete original status

### Database Schema

**New Entity: `TaskStatusType`**
```php
#[ORM\Entity(repositoryClass: TaskStatusTypeRepository::class)]
#[ORM\Table(name: 'task_status_type')]
class TaskStatusType
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\Column(length: 50, unique: true)]
    private string $name;

    #[ORM\Column(length: 20)]
    private string $slug;  // URL-safe identifier

    #[ORM\Column(length: 10)]
    private string $parentType;  // 'open' or 'closed'

    #[ORM\Column(length: 7)]
    private string $color;  // Hex color code

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;  // Cannot be deleted

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
}
```

**Migration for Task Entity:**
```php
// Change task.status from enum to relation
#[ORM\ManyToOne(targetEntity: TaskStatusType::class)]
#[ORM\JoinColumn(nullable: false)]
private TaskStatusType $status;
```

### API Endpoints

```
GET    /api/statuses              # List all statuses (for dropdowns)
GET    /settings/statuses         # Admin status management page
POST   /settings/statuses         # Create new status
PUT    /settings/statuses/{id}    # Update status
DELETE /settings/statuses/{id}    # Delete status (with reassignment)
POST   /settings/statuses/reorder # Reorder statuses
```

### Migration Strategy

**Data Migration Steps:**
1. Create `task_status_type` table
2. Insert default statuses matching current enum values
3. Add `status_type_id` column to `task` table
4. Populate `status_type_id` based on current `status` enum value
5. Drop old `status` enum column
6. Rename `status_type_id` to `status_id`

**Backward Compatibility:**
- Existing code using `TaskStatus` enum will need refactoring
- Create compatibility layer during transition if needed
- Update all status comparisons to use entity or slug

### UI Integration

#### Task Forms
- Status dropdown populated from `TaskStatusType` entities
- Grouped by parent type: "Open" section, "Closed" section
- Show color indicator next to each option

#### Kanban Board
- Each individual status becomes its own column (not grouped by Open/Closed)
- Column headers show status name and color
- Columns ordered by admin-defined sort order
- Option to collapse/hide specific columns

#### Filters
- Status filter dropdown groups statuses by parent type:
  ```
  ▼ Open
    ☐ To Do
    ☐ In Progress
    ☐ On Hold
  ▼ Closed
    ☐ Completed
    ☐ Dropped
  ```
- Clicking parent type header (Open/Closed) toggles all child statuses
- Filter chips show status color

#### Task Cards/List
- Status badge uses custom color
- Optional icon display

### Permissions

| Action | Permission | Default Role |
|--------|------------|--------------|
| View statuses | (all users) | Everyone |
| Create status | `settings.statuses.create` | Portal Admin |
| Edit status | `settings.statuses.edit` | Portal Admin |
| Delete status | `settings.statuses.delete` | Portal Admin |
| Reorder statuses | `settings.statuses.edit` | Portal Admin |

### Implementation Phases

1. **Entity & Migration**
   - Create `TaskStatusType` entity
   - Create migration (with data migration for existing tasks)
   - Create repository with ordering

2. **Admin UI**
   - Status list page
   - Add/Edit status modal
   - Drag-to-reorder functionality
   - Delete with reassignment

3. **Refactor Task Entity**
   - Change status from enum to relation
   - Update all status references throughout codebase
   - Update TaskStatus enum usages to entity lookups

4. **Update Task UI**
   - Status dropdowns use dynamic list
   - Update kanban to use custom statuses
   - Update filters
   - Update task cards/badges

5. **Reports & Metrics**
   - Update progress calculations to use parent type
   - Update any status-based reports

### Files Affected

**Backend:**
- New: `src/Entity/TaskStatusType.php`
- New: `src/Repository/TaskStatusTypeRepository.php`
- New: `src/Controller/Settings/StatusController.php`
- New: `src/Form/TaskStatusTypeFormType.php`
- New: `migrations/VersionXXX.php` - Create table + data migration
- Modified: `src/Entity/Task.php` - Change status field
- Modified: `src/Repository/TaskRepository.php` - Update status queries
- Modified: `src/Controller/TaskController.php` - Status lookups
- Modified: `src/Enum/TaskStatus.php` - Deprecate or remove
- Modified: `src/Service/TaskService.php` - Status handling
- Modified: `src/Twig/AppExtension.php` - Status helpers

**Frontend:**
- New: `templates/settings/statuses/index.html.twig`
- New: `templates/settings/statuses/_form.html.twig`
- New: `assets/js/status-admin.js` - Reorder, delete handling
- Modified: `templates/task/_form.html.twig` - Dynamic status dropdown
- Modified: `assets/vue/components/KanbanBoard.js` - Dynamic columns
- Modified: `templates/task/_card.html.twig` - Dynamic status colors
- Modified: `templates/task/_filters.html.twig` - Dynamic status options

### Edge Cases

- **Empty statuses**: Require at least one Open and one Closed status
- **Default status**: Mark one Open status as default for new tasks
- **Kanban column order**: Allow customizing which statuses appear as columns
- **Status transitions**: Allow free movement between any statuses (including Closed → Open) since accidental closures happen
- **Project-specific statuses**: Future enhancement - allow per-project status customization

### Example Custom Configurations

**Software Development:**
- Open: Backlog, Ready, In Development, Code Review, QA Testing
- Closed: Done, Won't Fix, Duplicate

**Sales Pipeline:**
- Open: Lead, Contacted, Proposal Sent, Negotiation
- Closed: Won, Lost, Disqualified

**Support Tickets:**
- Open: New, Triaged, In Progress, Waiting on Customer
- Closed: Resolved, Closed, Spam

---

## 10. Recurring Tasks

**Priority:** Medium
**Complexity:** Medium-High
**Impact:** Automates routine work, reduces manual task creation for repetitive workflows

### Overview

Recurring tasks allow users to create task templates that automatically generate new task instances on a defined schedule. Each instance is a normal `Task` entity that can be edited, completed, or deleted independently, while maintaining a link back to its recurring template.

### Core Concepts

#### How It Works

1. User creates a **Recurring Task Template** with task details + recurrence pattern
2. A **scheduled command** runs daily and creates task instances ahead of time
3. Each generated task is a normal `Task` with a reference to its template
4. Tasks display a recurring badge (🔄) to indicate they're part of a series

#### Two Creation Strategies

| Strategy | Description | Pros | Cons |
|----------|-------------|------|------|
| **Create on Schedule** (Recommended) | Cron job creates instances X days ahead | Tasks visible in upcoming views, predictable | Requires scheduler |
| **Create on Completion** | Next instance created when current is completed | Simple, no background jobs | If user forgets, next task never appears |

**Recommendation:** Use scheduled creation with configurable "create ahead" days (default: 7 days).

### Recurrence Patterns

| Pattern | Examples |
|---------|----------|
| **Daily** | Every day, every 3 days, every weekday |
| **Weekly** | Every Monday, every Mon/Wed/Fri, every 2 weeks on Tuesday |
| **Monthly** | 15th of each month, last day of month, 2nd Tuesday of month |
| **Yearly** | Every January 1st, last Friday of December |
| **Custom** | Every 10 days, every 6 weeks |

#### Pattern Configuration (Google Calendar Style)

**Repeat every:** `[1-99]` `[day/week/month/year]`

**Weekly options:**
```
Repeat on: [S] [M] [T] [W] [T] [F] [S]
           ☐   ☑   ☐   ☑   ☐   ☑   ☐   ← Mon, Wed, Fri selected
```

**Monthly options:**
```
○ Day 15 of the month
● The third Monday of the month
○ The last day of the month
```

**Ends:**
```
○ Never
○ On [date picker]
○ After [number] occurrences
```

### Database Schema

#### New Entity: RecurringTaskTemplate

```php
#[ORM\Entity(repositoryClass: RecurringTaskTemplateRepository::class)]
#[ORM\Table(name: 'recurring_task_template')]
class RecurringTaskTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    // Task template fields
    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: Milestone::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Milestone $milestone = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: TaskStatusType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private TaskStatusType $defaultStatus;

    #[ORM\Column(type: 'string', enumType: TaskPriority::class)]
    private TaskPriority $priority = TaskPriority::NONE;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'recurring_task_template_assignees')]
    private Collection $defaultAssignees;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;

    // Recurrence pattern
    #[ORM\Column(length: 20)]
    private string $frequency;  // 'daily', 'weekly', 'monthly', 'yearly'

    #[ORM\Column]
    private int $interval = 1;  // Every X days/weeks/months/years

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $daysOfWeek = null;  // [1,3,5] for Mon/Wed/Fri (ISO weekday numbers)

    #[ORM\Column(nullable: true)]
    private ?int $dayOfMonth = null;  // 1-31, or -1 for last day

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $monthlyType = null;  // 'day_of_month' or 'day_of_week'

    #[ORM\Column(nullable: true)]
    private ?int $weekOfMonth = null;  // 1-5, or -1 for last (for "2nd Tuesday" type)

    // Schedule bounds
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;  // null = no end

    #[ORM\Column(nullable: true)]
    private ?int $maxOccurrences = null;  // Stop after X instances

    #[ORM\Column]
    private int $createAheadDays = 7;  // Create instance X days before due

    // Tracking
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastCreatedAt = null;

    #[ORM\Column]
    private int $occurrenceCount = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
}
```

#### Task Entity Additions

```php
// Add to existing Task entity
#[ORM\ManyToOne(targetEntity: RecurringTaskTemplate::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
private ?RecurringTaskTemplate $recurringTemplate = null;

#[ORM\Column(nullable: true)]
private ?int $recurrenceIndex = null;  // Which occurrence (1st, 2nd, 3rd...)

#[ORM\Column(type: 'date_immutable', nullable: true)]
private ?\DateTimeImmutable $scheduledDate = null;  // Original scheduled date from pattern

public function isRecurring(): bool
{
    return $this->recurringTemplate !== null;
}
```

### Scheduled Task Creation

#### Command

```php
// src/Command/CreateRecurringTasksCommand.php
#[AsCommand(
    name: 'app:create-recurring-tasks',
    description: 'Creates upcoming instances of recurring tasks'
)]
class CreateRecurringTasksCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today');
        $templates = $this->templateRepo->findActive();
        $created = 0;

        foreach ($templates as $template) {
            // Check if max occurrences reached
            if ($template->getMaxOccurrences() &&
                $template->getOccurrenceCount() >= $template->getMaxOccurrences()) {
                continue;
            }

            // Check if past end date
            if ($template->getEndDate() && $today > $template->getEndDate()) {
                continue;
            }

            // Calculate next due dates within the create-ahead window
            $windowEnd = $today->modify("+{$template->getCreateAheadDays()} days");
            $nextDates = $this->recurrenceCalculator->getNextDates(
                $template,
                $template->getLastCreatedAt() ?? $template->getStartDate(),
                $windowEnd
            );

            foreach ($nextDates as $dueDate) {
                // Skip if task already exists for this date
                if ($this->taskRepo->existsForTemplateAndDate($template, $dueDate)) {
                    continue;
                }

                $task = $this->createTaskFromTemplate($template, $dueDate);
                $this->entityManager->persist($task);

                $template->setLastCreatedAt(new \DateTimeImmutable());
                $template->incrementOccurrenceCount();
                $created++;
            }
        }

        $this->entityManager->flush();
        $output->writeln("Created {$created} recurring task instances.");

        return Command::SUCCESS;
    }

    private function createTaskFromTemplate(
        RecurringTaskTemplate $template,
        \DateTimeImmutable $dueDate
    ): Task {
        $task = new Task();
        $task->setTitle($template->getTitle());
        $task->setDescription($template->getDescription());
        $task->setProject($template->getProject());
        $task->setMilestone($template->getMilestone());
        $task->setStatus($template->getDefaultStatus());
        $task->setPriority($template->getPriority());
        $task->setDueDate($dueDate);
        $task->setRecurringTemplate($template);
        $task->setRecurrenceIndex($template->getOccurrenceCount() + 1);
        $task->setScheduledDate($dueDate);

        foreach ($template->getDefaultAssignees() as $user) {
            $task->addAssignee($user);
        }

        foreach ($template->getTags() as $tag) {
            $task->addTag($tag);
        }

        return $task;
    }
}
```

#### Cron Configuration

```bash
# Run daily at 6 AM
0 6 * * * cd /path/to/project && php bin/console app:create-recurring-tasks
```

Or using Symfony Scheduler (Symfony 6.3+):

```php
#[AsSchedule]
class RecurringTaskSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::cron('0 6 * * *', new CreateRecurringTasksMessage()));
    }
}
```

### UI Components

#### Visual Differentiation in Task List

Tasks from recurring templates display a recurring icon:

```
┌─────────────────────────────────────────────────────────────────┐
│ ☐  Weekly team standup              🔄   To Do    Due: Mon 10   │
│ ☐  Daily standup                    🔄   To Do    Due: Tue 11   │
│ ☐  Fix login bug                          To Do    Due: Feb 12  │  ← normal
│ ☐  Monthly security review          🔄   To Do    Due: Mar 1    │
└─────────────────────────────────────────────────────────────────┘
```

**Legend:**
- 🔄 = Recurring task instance (or use a repeat icon)
- Hover tooltip: "Recurring: Every Monday"
- Click icon to view series details

#### Task Panel - Recurring Info Section

When viewing a recurring task instance:

```
┌─────────────────────────────────────────────────────────┐
│ Weekly Team Standup                              [...]  │
├─────────────────────────────────────────────────────────┤
│ 🔄 Recurring Task                                       │
│    Every Monday                                         │
│    Instance 12 of series                                │
│                                                         │
│    [View Series]  [Edit Series]  [Detach from Series]   │
├─────────────────────────────────────────────────────────┤
│ Status: To Do                                           │
│ Due Date: Monday, February 10, 2026                     │
│ ...                                                     │
└─────────────────────────────────────────────────────────┘
```

#### Create Recurring Task Modal

**Step 1: Task Details** (same as normal task creation)
- Title, Description, Project, Milestone, Priority, Assignees, Tags

**Step 2: Recurrence Pattern**

```
┌─────────────────────────────────────────────────────────┐
│ Recurrence Pattern                                      │
├─────────────────────────────────────────────────────────┤
│ Repeat every: [1 ▼] [week ▼]                            │
│                                                         │
│ Repeat on:                                              │
│   [S] [M] [T] [W] [T] [F] [S]                           │
│    ☐   ☑   ☐   ☐   ☐   ☐   ☐                           │
│                                                         │
│ Starts: [Feb 10, 2026    📅]                            │
│                                                         │
│ Ends:                                                   │
│   ○ Never                                               │
│   ○ On date: [____________📅]                           │
│   ○ After [__] occurrences                              │
│                                                         │
│ Create tasks: [7 ▼] days ahead                          │
├─────────────────────────────────────────────────────────┤
│               [Cancel]  [Create Recurring Task]         │
└─────────────────────────────────────────────────────────┘
```

#### Recurring Templates List

**Location:** Project → Settings → Recurring Tasks, or dedicated tab

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Recurring Tasks                                        [+ New Template] │
├─────────────────────────────────────────────────────────────────────────┤
│ Title                    │ Pattern           │ Next Due  │ Status │ ⚙️  │
├─────────────────────────────────────────────────────────────────────────┤
│ Weekly team standup      │ Every Monday      │ Feb 17    │ Active │ ⚙️  │
│ Daily standup            │ Every weekday     │ Feb 11    │ Active │ ⚙️  │
│ Monthly security review  │ 1st of month      │ Mar 1     │ Active │ ⚙️  │
│ Quarterly report         │ Every 3 months    │ Apr 1     │ Paused │ ⚙️  │
└─────────────────────────────────────────────────────────────────────────┘

⚙️ Menu: Edit, Pause/Resume, View Instances, Delete
```

### Behavior Specifications

#### When User Completes an Instance

1. Task marked as completed (normal behavior)
2. No immediate action needed - next instance already created by scheduler
3. If next instance doesn't exist yet, scheduler will create it on next run

#### When User Edits an Instance

| Field Changed | Behavior |
|---------------|----------|
| Title | Only this instance changes |
| Description | Only this instance changes |
| Due Date | Only this instance changes (task remains part of series) |
| Status | Only this instance changes |
| Assignees | Only this instance changes |

**Key Behavior:** Editing any field (including due date) only affects that instance. The task remains linked to the recurring template and continues to show the recurring badge. The `scheduledDate` field preserves the original scheduled date from the pattern, while `dueDate` can be changed independently.

This matches Google Calendar behavior: dragging an event to a different day reschedules that instance but keeps it part of the series.

#### When User Edits the Template

- Changes apply to **future instances only**
- Already-created instances remain unchanged
- Option to "Apply to all open instances" (bulk update)

#### When User Deletes an Instance

- Only that instance is deleted
- Series continues, future instances unaffected
- Instance marked as "skipped" in recurrence tracking (optional)

#### When User Deletes the Template

- Stop creating new instances
- Existing instances remain as normal tasks
- Existing instances lose recurring badge (template reference becomes null)

#### When User Pauses the Template

- Stop creating new instances temporarily
- Resume later to continue from where it left off
- Existing instances unaffected

#### Detaching an Instance

User can "detach" an instance from its series:
- Task becomes a normal task
- No longer shows recurring badge
- Template occurrence count unchanged

### Filtering & Grouping

#### Filter Options

```
Recurring Tasks:
  ○ All tasks
  ○ Recurring only
  ○ Non-recurring only
  ○ From template: [Select template ▼]
```

#### Grouping Option

In table view, add grouping option:
- **Group by: Recurring Series** - Groups instances by their template

### API Endpoints

```
# Templates
GET    /api/projects/{id}/recurring-templates       # List templates
POST   /api/projects/{id}/recurring-templates       # Create template
GET    /api/recurring-templates/{id}                # Get template details
PUT    /api/recurring-templates/{id}                # Update template
DELETE /api/recurring-templates/{id}                # Delete template
POST   /api/recurring-templates/{id}/pause          # Pause template
POST   /api/recurring-templates/{id}/resume         # Resume template

# Instance management
GET    /api/recurring-templates/{id}/instances      # List instances
POST   /api/tasks/{id}/detach-from-series           # Detach instance
```

### Implementation Phases

1. **Entity & Migration**
   - Create `RecurringTaskTemplate` entity
   - Add recurring fields to `Task` entity
   - Create migrations

2. **Recurrence Calculator Service**
   - Calculate next occurrence dates
   - Handle all pattern types (daily, weekly, monthly, yearly)
   - Edge cases: month end, leap years, timezone handling

3. **Scheduled Command**
   - Create recurring tasks command
   - Cron/scheduler configuration
   - Logging and error handling

4. **Template CRUD UI**
   - Create template form with recurrence pattern UI
   - Template list page
   - Edit/pause/delete functionality

5. **Task List Integration**
   - Recurring badge display
   - Filter by recurring
   - Group by series

6. **Task Panel Integration**
   - Show recurring info section
   - View/edit series links
   - Detach functionality

7. **Notifications**
   - Notify assignees when recurring instance is created
   - Optional: Summary email of upcoming recurring tasks

### Files Affected

**Backend:**
- New: `src/Entity/RecurringTaskTemplate.php`
- New: `src/Repository/RecurringTaskTemplateRepository.php`
- New: `src/Service/RecurrenceCalculatorService.php`
- New: `src/Command/CreateRecurringTasksCommand.php`
- New: `src/Controller/RecurringTaskController.php`
- New: `src/Form/RecurringTaskTemplateType.php`
- New: `migrations/VersionXXX.php`
- Modified: `src/Entity/Task.php` - Add recurring fields
- Modified: `src/Repository/TaskRepository.php` - Filter by recurring

**Frontend:**
- New: `templates/recurring/index.html.twig` - Template list
- New: `templates/recurring/_form.html.twig` - Create/edit form
- New: `assets/vue/components/RecurrencePatternEditor.js` - Pattern UI
- Modified: `templates/task/_card.html.twig` - Recurring badge
- Modified: `templates/task/_panel.html.twig` - Recurring info section
- Modified: `assets/vue/components/TaskTable.js` - Recurring filter/group
- Modified: `assets/vue/components/TaskRow.js` - Recurring badge

### Edge Cases

| Case | Handling |
|------|----------|
| Monthly on 31st | Falls back to last day of shorter months |
| Leap year Feb 29 | Skip in non-leap years, or fall back to Feb 28 |
| DST transitions | Use date-only scheduling, not time-specific |
| Milestone deleted | Instances remain, new instances have no milestone |
| Assignee deactivated | Remove from template, existing instances unchanged |
| Project archived | Pause all templates in project |
| Template end date passed | Mark as completed, stop checking |
| Max occurrences reached | Mark as completed, stop checking |

### Example Use Cases

**Daily Standup:**
- Frequency: Daily (weekdays only)
- Days: Mon, Tue, Wed, Thu, Fri
- Assignees: Entire team
- Create ahead: 1 day

**Weekly Report:**
- Frequency: Weekly
- Day: Friday
- Assignees: Project manager
- Create ahead: 3 days

**Monthly Invoice Review:**
- Frequency: Monthly
- Type: Last business day of month
- Assignees: Finance team
- Create ahead: 7 days

**Quarterly Security Audit:**
- Frequency: Every 3 months
- Day: 1st of month
- Months: Jan, Apr, Jul, Oct
- Assignees: Security team
- Create ahead: 14 days

---

## 11. Trash Bin (Soft Delete)

**Priority:** High
**Complexity:** Medium
**Impact:** Prevents accidental data loss, enables recovery of deleted items

### Overview

Implement a soft delete system across all major entities. Instead of permanently removing records, items are moved to a trash bin where they can be restored or permanently deleted. A dedicated Trash page provides a unified view of all deleted items across modules.

### Affected Modules

| Module | Parent-Child Relationship |
|--------|---------------------------|
| **Projects** | Contains Milestones |
| **Milestones** | Contains Tasks |
| **Tasks** | Contains Subtasks, Checklists, Comments, Attachments |
| **Subtasks** | Contains Checklists (if applicable) |
| **Checklists** | Contains Checklist Items |
| **Users** | Assigned to Tasks, owns Projects |

### Core Mechanism

#### Soft Delete Field

Add a `deletedAt` nullable datetime column to each entity:

```php
#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $deletedAt = null;

public function isDeleted(): bool
{
    return $this->deletedAt !== null;
}

public function delete(): void
{
    $this->deletedAt = new \DateTimeImmutable();
}

public function restore(): void
{
    $this->deletedAt = null;
}
```

#### Default Query Filtering

All repositories exclude soft-deleted items by default:

```php
// TaskRepository.php
public function findActive(Project $project): array
{
    return $this->createQueryBuilder('t')
        ->where('t.deletedAt IS NULL')
        ->andWhere('t.project = :project')
        ->setParameter('project', $project)
        ->getQuery()
        ->getResult();
}

public function findTrashed(): array
{
    return $this->createQueryBuilder('t')
        ->where('t.deletedAt IS NOT NULL')
        ->orderBy('t.deletedAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

### Parent-Child Deletion Behavior

When deleting an item that has children, prompt the user with options:

#### Delete Confirmation Dialog

```
┌─────────────────────────────────────────────────────────────────┐
│ Delete Milestone: "Sprint 1"                                    │
├─────────────────────────────────────────────────────────────────┤
│ This milestone contains 12 tasks. What would you like to do     │
│ with them?                                                      │
│                                                                 │
│   ○ Delete all tasks with the milestone                         │
│     Tasks will be moved to trash and can be restored later.     │
│                                                                 │
│   ○ Move tasks to project root (no milestone)                   │
│     Tasks will remain active but unassigned to any milestone.   │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [Cancel]  [Delete Milestone]       │
└─────────────────────────────────────────────────────────────────┘
```

#### Hierarchy Rules

| Deleting | Children | Options |
|----------|----------|---------|
| **Project** | Milestones, Tasks | Cascade delete all OR transfer to another project |
| **Milestone** | Tasks | Cascade delete OR promote to project root (no milestone) |
| **Task** | Subtasks, Checklists, Comments | Cascade delete OR promote subtasks to root tasks |
| **Subtask** | Checklists | Always cascade (checklists go with subtask) |
| **User** | Owned projects, assignments | Transfer ownership OR reassign, cannot delete if sole owner |

### Trash Page UI

**Location:** `/trash` (accessible from sidebar or user menu)

#### Tab-Based Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Trash                                                    [Empty Trash]  │
├─────────────────────────────────────────────────────────────────────────┤
│ [Projects (2)] [Milestones (5)] [Tasks (23)] [Subtasks (8)]             │
│ [Checklists (3)] [Users (0)]                                            │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ ☐  Task Title                    │ Deleted      │ Deleted By │ ⟲   │ │
│ ├─────────────────────────────────────────────────────────────────────┤ │
│ │ ☐  Fix login validation bug      │ 2 hours ago  │ John Doe   │ [⟲] │ │
│ │ ☐  Update dashboard layout       │ 1 day ago    │ Jane Smith │ [⟲] │ │
│ │ ☐  API documentation             │ 3 days ago   │ John Doe   │ [⟲] │ │
│ │ ☐  Old feature prototype         │ 7 days ago   │ Jane Smith │ [⟲] │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ Selected: 0  │  [Restore Selected]  [Delete Permanently]                │
└─────────────────────────────────────────────────────────────────────────┘

Legend:
[⟲] = Restore button
```

#### Features

1. **Tabs for Each Module**
   - Badge showing count of trashed items per module
   - Only show tabs with items (or show all with 0 counts)

2. **Item List**
   - Checkbox for multi-select
   - Item name/title with link to preview (read-only)
   - Deletion timestamp (relative: "2 hours ago", "3 days ago")
   - Deleted by (user who performed the deletion)
   - Restore button per item

3. **Bulk Actions**
   - Restore Selected
   - Delete Permanently (with confirmation)

4. **Sorting**
   - Default: Deletion date, newest first
   - Options: Name, Deleted by, Deletion date

5. **Search/Filter**
   - Search by name within current tab
   - Filter by date range
   - Filter by "Deleted by" user

### Restore Behavior

#### Simple Restore

When restoring an item with no parent dependencies:
- Set `deletedAt = null`
- Item reappears in its original location

#### Restore with Missing Parent

When the parent was also deleted or permanently removed:

```
┌─────────────────────────────────────────────────────────────────┐
│ Cannot Restore Task                                             │
├─────────────────────────────────────────────────────────────────┤
│ The milestone "Sprint 1" for this task has been deleted.        │
│                                                                 │
│ Choose a new location:                                          │
│                                                                 │
│   Project: [My Project        ▼]                                │
│   Milestone: [No Milestone    ▼]  (or restore "Sprint 1" first) │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [Cancel]  [Restore Task]           │
└─────────────────────────────────────────────────────────────────┘
```

#### Cascade Restore

Option to restore parent and all children together:
- "Restore with children" button on parent items
- Restores entire hierarchy at once

### Permanent Deletion

#### Single Item

```
┌─────────────────────────────────────────────────────────────────┐
│ Permanently Delete Task?                                        │
├─────────────────────────────────────────────────────────────────┤
│ "Fix login validation bug" will be permanently deleted.         │
│ This action cannot be undone.                                   │
│                                                                 │
│ This will also permanently delete:                              │
│   • 3 subtasks                                                  │
│   • 2 checklists                                                │
│   • 8 comments                                                  │
│   • 1 attachment                                                │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [Cancel]  [Delete Permanently]     │
└─────────────────────────────────────────────────────────────────┘
```

#### Empty Trash

```
┌─────────────────────────────────────────────────────────────────┐
│ Empty Trash?                                                    │
├─────────────────────────────────────────────────────────────────┤
│ All items in the trash will be permanently deleted.             │
│ This action cannot be undone.                                   │
│                                                                 │
│ Current trash contents:                                         │
│   • 2 projects                                                  │
│   • 5 milestones                                                │
│   • 23 tasks                                                    │
│   • 8 subtasks                                                  │
│   • 3 checklists                                                │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [Cancel]  [Empty Trash]            │
└─────────────────────────────────────────────────────────────────┘
```

### Auto-Cleanup (Optional)

Configurable automatic permanent deletion of old trash items:

```php
// Portal Settings
'trash_retention_days' => 30,  // 0 = never auto-delete
```

#### Cleanup Command

```php
#[AsCommand(name: 'app:cleanup-trash')]
class CleanupTrashCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retentionDays = $this->settings->get('trash_retention_days');
        if ($retentionDays <= 0) return Command::SUCCESS;

        $cutoffDate = new \DateTimeImmutable("-{$retentionDays} days");

        // Permanently delete items older than cutoff
        $this->projectRepo->permanentlyDeleteOlderThan($cutoffDate);
        $this->milestoneRepo->permanentlyDeleteOlderThan($cutoffDate);
        $this->taskRepo->permanentlyDeleteOlderThan($cutoffDate);
        // ... other entities

        return Command::SUCCESS;
    }
}
```

### API Endpoints

```
# Trash listing
GET    /trash                           # Trash page (HTML)
GET    /api/trash/projects              # List trashed projects
GET    /api/trash/milestones            # List trashed milestones
GET    /api/trash/tasks                 # List trashed tasks
GET    /api/trash/subtasks              # List trashed subtasks
GET    /api/trash/checklists            # List trashed checklists
GET    /api/trash/users                 # List deactivated users

# Soft delete (existing endpoints, behavior changes)
DELETE /api/projects/{id}               # Soft delete (moves to trash)
DELETE /api/milestones/{id}             # Soft delete
DELETE /api/tasks/{id}                  # Soft delete
DELETE /api/tasks/{id}/subtasks/{sid}   # Soft delete subtask

# Restore
POST   /api/trash/projects/{id}/restore
POST   /api/trash/milestones/{id}/restore
POST   /api/trash/tasks/{id}/restore
POST   /api/trash/subtasks/{id}/restore
POST   /api/trash/checklists/{id}/restore

# Permanent delete
DELETE /api/trash/projects/{id}/permanent
DELETE /api/trash/milestones/{id}/permanent
DELETE /api/trash/tasks/{id}/permanent
DELETE /api/trash/subtasks/{id}/permanent
DELETE /api/trash/checklists/{id}/permanent

# Bulk operations
POST   /api/trash/restore               # Body: { type: 'tasks', ids: [...] }
DELETE /api/trash/permanent             # Body: { type: 'tasks', ids: [...] }
DELETE /api/trash/empty                 # Empty entire trash (admin only)
```

### Database Migration

```php
// For each entity (example: Task)
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE task ADD deleted_at DATETIME DEFAULT NULL');
    $this->addSql('ALTER TABLE task ADD deleted_by_id BINARY(16) DEFAULT NULL');
    $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_TASK_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES user (id) ON DELETE SET NULL');
    $this->addSql('CREATE INDEX IDX_TASK_DELETED_AT ON task (deleted_at)');
}
```

### Permissions

| Action | Permission | Default Role |
|--------|------------|--------------|
| View own trash | (all users) | Everyone |
| View all trash | `trash.view_all` | Admin |
| Restore own items | (all users) | Everyone |
| Restore any item | `trash.restore_any` | Admin |
| Permanent delete own | `trash.permanent_delete` | Project Manager |
| Permanent delete any | `trash.permanent_delete_any` | Admin |
| Empty trash | `trash.empty` | Admin |
| Configure retention | `settings.trash` | Portal Admin |

### Implementation Phases

1. **Entity Updates**
   - Add `deletedAt`, `deletedBy` fields to all entities
   - Create migration for all tables
   - Add soft delete methods to entities

2. **Repository Updates**
   - Update all queries to filter `deletedAt IS NULL` by default
   - Add `findTrashed()` methods
   - Add permanent delete methods

3. **Delete Behavior**
   - Update delete endpoints to soft delete
   - Add parent-child deletion dialog
   - Implement cascade vs promote logic

4. **Trash Page**
   - Create trash controller and routes
   - Build tabbed trash UI
   - Implement restore functionality

5. **Permanent Delete**
   - Add permanent delete endpoints
   - Build confirmation dialogs
   - Implement empty trash feature

6. **Auto-Cleanup**
   - Create cleanup command
   - Add portal setting for retention days
   - Configure cron schedule

### Files Affected

**Backend:**
- Modified: `src/Entity/Project.php` - Add soft delete fields
- Modified: `src/Entity/Milestone.php` - Add soft delete fields
- Modified: `src/Entity/Task.php` - Add soft delete fields
- Modified: `src/Entity/Checklist.php` - Add soft delete fields
- Modified: `src/Entity/User.php` - Add soft delete fields
- Modified: `src/Repository/*.php` - Filter deleted, add trash queries
- New: `src/Controller/TrashController.php`
- New: `src/Service/TrashService.php` - Deletion logic, cascade handling
- New: `src/Command/CleanupTrashCommand.php`
- New: `migrations/VersionXXX.php`

**Frontend:**
- New: `templates/trash/index.html.twig` - Trash page
- New: `assets/vue/components/TrashList.js` - Vue component for trash
- New: `assets/vue/components/TrashTabs.js` - Tab navigation
- Modified: `templates/components/confirm_dialog.html.twig` - Parent-child options
- Modified: Various delete buttons - Use soft delete

### Edge Cases

| Case | Handling |
|------|----------|
| Restore task with deleted milestone | Prompt for new milestone or restore milestone first |
| Restore subtask with deleted parent | Prompt to restore as root task or restore parent first |
| Delete user who owns projects | Must transfer ownership first |
| Circular restore dependency | Detect and prompt to restore entire chain |
| Trash storage limits | Optional: Limit trash size per user/project |
| Viewing deleted item | Read-only preview with "Restore" CTA |

---

## Notes

- Features are listed roughly in order of suggested implementation priority
- Each feature should be fully specified before implementation
- Consider dependencies between features when planning sprints
- Centralized Task Store (Feature 1) is foundational for many other features

---

*Last updated: 6 February 2026*
