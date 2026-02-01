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

## 3. Subtasks (Nested Tasks)

**Priority:** High
**Complexity:** Medium
**Impact:** Enables breaking down complex tasks into manageable pieces

### Overview
Add full-featured subtasks that mirror the parent task's capabilities. Subtasks are displayed as a new tab in the task detail page/panel, next to Checklist, Comments, and Activity tabs.

### Key Requirements

1. **Full Task Parity**
   - Subtasks have identical layout and fields as parent tasks:
     - Title, description
     - Status (To Do, In Progress, In Review, Completed)
     - Priority (None, Low, Medium, High)
     - Assignees
     - Due date, start date
     - Tags
     - Checklist items
     - Comments
     - Activity log
   - Subtasks can be opened in the same panel/detail view as regular tasks

2. **Nesting Depth**
   - Maximum 3 levels deep:
     ```
     Task (Level 0)
     +-- Subtask (Level 1)
         +-- Sub-subtask (Level 2)
             +-- Sub-sub-subtask (Level 3) <- Maximum depth
     ```
   - UI prevents creating subtasks beyond level 3
   - Clear visual indication of nesting level

3. **UI Location**
   - New "Subtasks" tab in task panel (between Checklist and Comments)
   - Tab shows count: "Subtasks (3)"
   - Progress indicator: "2/5 completed"

### UI Design

**Subtasks Tab Content:**
```
+-------------------------------------------------------------+
| [+ Add Subtask]                          2/5 completed       |
+-------------------------------------------------------------+
| [x] Design database schema                    Completed      |
| [x] Create API endpoints                      Completed      |
| [ ] Build frontend components                 In Progress    |
|   +-- [ ] Create form component                  To Do       |
|   +-- [ ] Create list component                  To Do       |
| [ ] Write tests                                  To Do       |
| [ ] Documentation                                To Do       |
+-------------------------------------------------------------+
```

**Subtask Row Features:**
- Checkbox for quick complete/uncomplete
- Click title to open subtask in panel (replaces current content)
- Status badge
- Assignee avatar(s)
- Due date (if set)
- Nested subtasks shown indented below parent
- Collapse/expand for nested items

**Breadcrumb Navigation:**
When viewing a subtask, show breadcrumb trail:
```
Project Name > Parent Task > Subtask > Current Sub-subtask
```

### Data Model

**Task Entity Changes:**
```php
// src/Entity/Task.php
#[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'subtasks')]
#[ORM\JoinColumn(nullable: true)]
private ?Task $parent = null;

#[ORM\OneToMany(mappedBy: 'parent', targetEntity: Task::class, cascade: ['persist', 'remove'])]
#[ORM\OrderBy(['position' => 'ASC'])]
private Collection $subtasks;

#[ORM\Column(type: 'integer', options: ['default' => 0])]
private int $depth = 0;  // 0 = root task, 1-3 = subtask levels

public function canHaveSubtasks(): bool
{
    return $this->depth < 3;
}

public function getSubtaskCount(): int { }
public function getCompletedSubtaskCount(): int { }
```

### API Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/tasks/{id}/subtasks` | List subtasks for a task |
| POST | `/tasks/{id}/subtasks` | Create subtask under task |
| PATCH | `/tasks/{id}/parent` | Move subtask to different parent |
| GET | `/tasks/{id}/breadcrumbs` | Get parent chain for navigation |

### Behavior Rules

1. **Inheritance (Optional)**
   - Subtasks can optionally inherit milestone from parent
   - Subtasks belong to same project as parent (enforced)

2. **Completion Logic**
   - Parent task can be completed independently of subtasks
   - Option: "Complete all subtasks" action
   - Option: Show warning if completing parent with open subtasks

3. **Deletion**
   - Deleting parent task deletes all subtasks (cascade)
   - Confirmation dialog shows subtask count

4. **Moving/Reordering**
   - Subtasks can be reordered within their parent
   - Subtasks can be promoted to root tasks
   - Root tasks can be demoted to subtasks of another task
   - Prevent circular references

5. **Kanban Display**
   - Subtasks do NOT appear on main kanban board
   - Only root-level tasks shown in kanban
   - Subtask count/progress shown on task card

### Implementation Phases

1. **Database & Entity**
   - Add parent/subtasks relations to Task entity
   - Add depth field
   - Create migration

2. **API Endpoints**
   - CRUD for subtasks
   - Breadcrumb endpoint
   - Validation (depth limit, same project)

3. **Subtasks Tab Component**
   - Vue component for subtask list
   - Inline create form
   - Drag-drop reordering

4. **Panel Navigation**
   - Breadcrumb component
   - Back button behavior
   - Panel history stack

5. **Task Card Integration**
   - Show subtask progress on cards
   - "Has subtasks" indicator

### Files Affected

**Backend:**
- Modified: `src/Entity/Task.php` - Add relations and methods
- New: `migrations/VersionXXX.php` - Add parent_id, depth columns
- Modified: `src/Controller/TaskController.php` - Subtask endpoints
- Modified: `src/Repository/TaskRepository.php` - Subtask queries

**Frontend:**
- New: `assets/vue/components/SubtasksEditor.js` - Subtasks tab component
- New: `templates/task/_subtasks_vue.html.twig` - Vue mount point
- Modified: `templates/task/_panel.html.twig` - Add Subtasks tab
- Modified: `templates/task/show.html.twig` - Add Subtasks tab
- Modified: `templates/task/_card.html.twig` - Show subtask count
- Modified: `assets/vue/components/TaskCard.js` - Subtask indicator

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

8. **Performance**
   - Virtual scrolling for large lists
   - Lazy load groups
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
- New: `assets/vue/components/TaskTable/BulkActionBar.js`
- New: `assets/vue/components/TaskTable/ColumnConfig.js`
- New: `templates/task/_table_vue.html.twig` - Mount point
- Modified: `templates/project/show.html.twig` - Add Table view option
- New: `assets/css/task-table.css` - Table-specific styles

### Accessibility

- Full keyboard navigation
- ARIA roles (grid, row, gridcell, columnheader)
- Screen reader announcements for edits
- Focus management when editing
- High contrast support

### Mobile Considerations

- Simplified view on mobile (fewer columns)
- Horizontal scroll with sticky first column
- Touch-friendly edit controls
- Consider card view alternative on very small screens

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

## 7. Additional Future Considerations

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
- Daily/weekly/monthly task templates
- Auto-create tasks on schedule
- Useful for maintenance and routine work

### Advanced Search
- Full-text search across all tasks
- Filter by multiple criteria
- Saved searches/filters

---

## Notes

- Features are listed roughly in order of suggested implementation priority
- Each feature should be fully specified before implementation
- Consider dependencies between features when planning sprints
- Centralized Task Store (Feature 1) is foundational for many other features

---

*Last updated: January 2026*
