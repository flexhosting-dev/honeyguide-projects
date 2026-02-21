# Future Features

This document outlines planned enhancements and architectural improvements for Honeyguide Projects.

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



## 5. CSV Import & Export

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

## 6. Additional Future Considerations

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


## 7. Trash Bin (Soft Delete)

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

## 8. Task Table View Export

**Priority:** Medium
**Complexity:** Low-Medium
**Impact:** Enables users to export current view data for reporting and sharing

### Overview

Add export functionality to the task table view that respects the current view state: visible columns, sort order, grouping, and expand/collapse state for subtasks. Exports the data exactly as displayed in the table.

### Export Formats

| Format | Library | Description |
|--------|---------|-------------|
| **CSV** | Native JS | Universal format, UTF-8 with BOM for Excel compatibility |
| **Excel (XLSX)** | SheetJS (xlsx) | Native Excel format with column widths and formatting |
| **PDF** | jsPDF + jspdf-autotable | Printable report with header, page numbers, landscape layout |

### Data Transformation

#### Column Handling
- Only export columns with `visible: true` (respects ColumnConfig settings)
- Exclude checkbox column from export
- Add "Depth" column to show hierarchy level (0, 1, 2...)

#### Hierarchy Representation
- Indent task titles with dashes for subtasks: `-- Subtask name`, `---- Sub-subtask`
- Include numeric depth column for filtering/sorting in Excel

#### Group Headers
- When grouping is active, include group header rows
- Format: `--- Status: In Progress (3/8 completed) ---`
- Excel/PDF: Style group rows with gray background

#### Field Formatting

| Field | Export Format |
|-------|---------------|
| Title | Dashed indent + text (`-- Subtask name`) |
| Status | Human-readable label ("In Progress") |
| Priority | Human-readable label ("High", "Medium", "Low", "None") |
| Assignees | Comma-separated full names |
| Tags | Comma-separated tag names |
| Dates | "Feb 8, 2026" format |
| Milestone | Milestone name or empty |
| Subtasks | "2/5" format |

### UI Integration

#### Export Dropdown Button

Location: Table toolbar, between search and column config button

```
[Search Input] [Export ▼] [Columns ⚙]
```

Dropdown menu:
- CSV (.csv)
- Excel (.xlsx)
- PDF (.pdf)

Button shows loading spinner during export.

### File Naming

Format: `{ProjectName}_Export_{YYYY-MM-DD}.{ext}`

Example: `Website_Redesign_Export_2026-02-08.xlsx`

### Technical Implementation

#### Libraries (via importmap.php CDN)

```php
'xlsx' => [
    'version' => '0.18.5',
    'url' => 'https://cdn.sheetjs.com/xlsx-0.20.2/package/xlsx.mjs'
],
'jspdf' => [
    'version' => '2.5.1',
],
'jspdf-autotable' => [
    'version' => '3.8.2',
],
```

#### Files to Create

| File | Purpose |
|------|---------|
| `assets/vue/utils/exportUtils.js` | Export logic (prepareData, CSV, Excel, PDF functions) |
| `assets/vue/components/TaskTable/ExportMenu.js` | Dropdown UI component |

#### Files to Modify

| File | Changes |
|------|---------|
| `importmap.php` | Add xlsx, jspdf, jspdf-autotable CDN imports |
| `assets/vue/components/TaskTable.js` | Import ExportMenu, add to toolbar |
| `templates/task/_table_vue.html.twig` | Add project-name prop for filename |

### Format-Specific Details

#### CSV
- UTF-8 encoding with BOM for Excel compatibility
- Escape commas, quotes, and newlines properly
- Client-side generation and download

#### Excel (XLSX)
- Proportional column widths (title wider, depth narrow)
- Header row with column labels
- Text wrapping for long content

#### PDF
- Landscape A4 orientation
- Project name as title, export date
- Striped rows, blue header
- Page numbers in footer
- Proportional column widths capped at 60mm

### Implementation Phases

1. Create export utilities (`exportUtils.js`)
2. Create ExportMenu component (follow ColumnConfig.js pattern)
3. Update `importmap.php` with CDN libraries
4. Integrate ExportMenu into TaskTable.js toolbar
5. Add project-name prop to template
6. Test with various view configurations

### Verification

1. Toggle column visibility, verify export matches
2. Change sort order, verify export respects it
3. Expand/collapse subtasks, verify only visible rows export
4. Enable grouping, verify group headers appear
5. Test CSV in Excel - verify no encoding issues
6. Test XLSX formatting and column widths
7. Test PDF layout and page breaks

---

## 9. Dashboard Enhancements

**Priority:** Medium
**Complexity:** Low-Medium
**Impact:** Improves at-a-glance visibility of work status and team activity

### Overview

Enhance the dashboard with additional widgets, visualizations, and personalization options to provide users with a more comprehensive and actionable view of their work.

### Current Dashboard Features

- [x] Stats cards: Total Projects, Total Tasks, Due Today, Overdue
- [x] Tasks Due Today section (prominent, with task cards)
- [x] Upcoming Tasks (next 7 days)
- [x] Recent Activity feed
- [x] Tasks by Status breakdown

### Quick Wins (Low Effort)

| Feature | Description | Status |
|---------|-------------|--------|
| **Tasks Due Today** | Prominent section showing tasks due today | ✅ Done |
| **Task Completion Rate** | Percentage/progress bar (e.g., "73% completed this week") | Planned |
| **Projects at Risk** | Highlight projects with overdue milestones or many overdue tasks | Planned |
| **Workload Indicator** | Tasks by priority breakdown (high/medium/low) | Planned |

### Charts & Visualizations

| Feature | Description | Library |
|---------|-------------|---------|
| **Task Burndown Chart** | Weekly/monthly view of tasks completed vs created | Chart.js |
| **Activity Sparkline** | Small chart showing team activity trends (7-30 days) | Chart.js |
| **Status Distribution Chart** | Pie/donut chart for visual task status breakdown | Chart.js |

### Team & Collaboration

| Feature | Description |
|---------|-------------|
| **Team Activity Summary** | "Sarah completed 5 tasks, John added 3 comments today" |
| **Mentions & Notifications Panel** | Unread mentions/comments requiring attention |
| **Who's Working on What** | Team members active on which projects |

### Time-Based Insights

| Feature | Description |
|---------|-------------|
| **This Week Summary** | Tasks completed, comments made, milestones hit |
| **Upcoming Milestones** | Next 3-5 milestones across all projects with countdown |
| **Recently Completed** | Tasks completed in last 24-48 hours (celebrate wins) |

### Personalization

| Feature | Description |
|---------|-------------|
| **Pinned/Favorite Tasks** | Quick access to tasks user is actively working on |
| **Custom Widgets** | Let users choose which dashboard cards to display |
| **Project Health Overview** | Traffic light status for each active project |

### Implementation Priority

**Phase 1 - Quick Wins:**
1. Task Completion Rate widget
2. Upcoming Milestones section
3. Workload Indicator (priority breakdown)

**Phase 2 - Visualizations:**
4. Status Distribution Chart
5. Activity Sparkline
6. Task Burndown Chart

**Phase 3 - Personalization:**
7. Custom widget toggle (localStorage)
8. Pinned tasks
9. Project health indicators

### Permission Model (Verified)

The dashboard correctly respects user roles and permissions:

| Data | Permission Check |
|------|------------------|
| Projects | User is owner, member, OR project is public |
| Tasks | User is an assignee |
| Hidden Projects | Tasks from user's hidden projects excluded |
| Activities | Only from projects user has access to |

Relevant code locations:
- `ProjectRepository::findByUser()` - Filters by ownership/membership/public
- `TaskRepository::findTasksDueToday()` - Joins on assignees, excludes hidden
- `TaskRepository::excludeHiddenProjects()` - Helper to filter hidden projects

### Files Affected

**Backend:**
- Modified: `src/Controller/DashboardController.php` - Additional data queries
- New: `src/Service/DashboardStatsService.php` - Aggregate calculations
- Modified: `src/Repository/TaskRepository.php` - Completion rate, burndown queries
- Modified: `src/Repository/MilestoneRepository.php` - Upcoming milestones query

**Frontend:**
- Modified: `templates/dashboard/index.html.twig` - New widgets
- New: `assets/vue/components/Dashboard/CompletionRateWidget.js`
- New: `assets/vue/components/Dashboard/StatusChart.js`
- New: `assets/vue/components/Dashboard/ActivitySparkline.js`
- New: `assets/vue/components/Dashboard/UpcomingMilestones.js`
- Modified: `importmap.php` - Add Chart.js for visualizations

---

## 10. Email Notifications Enhancements

**Priority:** Medium
**Complexity:** Medium
**Impact:** Improves email delivery reliability and user experience

### Current State
Email notifications are now sent automatically when in-app notifications are created, respecting user preferences. The `NotificationEmailService` handles all notification types centrally.

### Future Considerations

- **Async email sending**: Use Symfony Messenger to queue emails instead of sending synchronously
- **Email templates**: Move to Twig templates for better maintainability and easier customization
- **Unsubscribe links**: Add one-click unsubscribe per notification type
- **Email batching**: Digest emails instead of individual notifications (daily/weekly summaries)

---

*Last updated: 17 February 2026*

---

## 11. Auto-assign Tasks to Current User in My Tasks
**Priority:** Medium
**Status:** Pending

When creating tasks from the "My Tasks" page, automatically assign the task to the currently logged-in user.

**Requirements:**
- Detect task creation context (My Tasks page vs Project page)
- Auto-populate assignee field with current user
- Show visual feedback that task was auto-assigned
- Allow user to remove themselves if needed

**Implementation:**
- Update TaskController to check request context
- Add `auto_assign_to_me` parameter in task creation
- Update TaskTable component to pass context flag
- Show notification: "Task assigned to you"

---

## 12. Move Task Options for Root Tasks
**Priority:** High
**Status:** Pending

Add comprehensive move/reorganize options for root-level tasks in the task panel footer.

**Requirements:**
- Show move options in footer (similar to subtask move option)
- Available only for root tasks (no parent)
- Three move options:
  1. **Move to Different Project**: Select target project → Select milestone → Confirm
  2. **Move to Different Milestone**: Select milestone in same project → Confirm
  3. **Make Subtask Of**: Select parent task in same milestone → Confirm
- Prevent circular references (task becoming its own ancestor)
- Update task position in new location
- Show confirmation with undo option

**Implementation:**
- Add move button to task panel footer for root tasks
- Create move modal with three tabs/options
- API endpoint: `POST /tasks/{id}/move`
- Validate circular reference on backend
- Update all related caches and views
- Activity log for move operation

**Additional Options to Consider:**
- Convert to standalone task (if currently a subtask)
- Duplicate task to another project/milestone
- Archive task (soft delete)

---

## 13. Task Footer on Detail Page
**Priority:** Medium
**Status:** Pending

Add the same footer actions from task panel to the dedicated task detail page.

**Requirements:**
- Include all footer actions from task panel
- Exclude "Open in full" option (already on full page)
- Keep: Move, Convert to subtask, Delete, Archive, etc.
- Responsive layout for mobile

**Implementation:**
- Extract footer component to reusable partial
- Include in both task panel and detail page templates
- Add parameter to hide "Open in full" button
- Ensure consistent styling and behavior

---

## 14. Default "General" Milestone ✅
**Priority:** High
**Status:** COMPLETED (v1.0.1)

Create mandatory default milestone for all projects.

**Completed Features:**
- ✅ Default "General" milestone created for all projects
- ✅ Always listed first (position 0)
- ✅ Auto-assignment for tasks without milestone
- ✅ Migration for existing projects
- ✅ Milestone reordering logic (drag-and-drop)
- ✅ Project/milestone/task ordering on "All Tasks" page
- ✅ Admin-only project reordering
- ✅ Project manager milestone reordering
- ✅ Cannot delete or rename default milestone
- ✅ Visual "Default" badge in UI

**Implementation Details:**
See migrations:
- Version20260220120000 - Position and isDefault fields
- Version20260220120100 - Project positions
- Version20260220120200 - Create default milestones
- Version20260220120300 - Changelog entry

---

## 15. Profile Hover Cards
**Priority:** Medium
**Status:** Pending

Display user profile information in hover cards throughout the application.

**Requirements:**
- Trigger on hover over user names/avatars
- Show on: Activity feeds, notifications, assignee lists, comments, etc.
- Card content:
  - Profile photo
  - Full name and title
  - Email address
  - Department/team
  - Active projects count
  - Quick actions: Message, View Profile, Add to Project
- Smooth animation (fade in/out)
- Delay before showing (500ms)
- Dismiss on mouse leave

**Implementation:**
- Create reusable ProfileCard Vue component
- Use Tippy.js or custom positioning logic
- Lazy load profile data on hover
- Cache profile data for performance
- Responsive design for mobile (tap to show)

**Public Profile Page:**
- URL: `/users/{id}/profile`
- Show: Bio, projects, activity timeline, stats
- Privacy settings for what's visible to non-admins
- Edit button for own profile

---

## 16. Enhanced Project Member Management
**Priority:** High
**Status:** Pending

Improve project member addition workflow with bulk operations and invitation system.

**Requirements:**

#### A. Add Members Offcanvas Panel
- Open dedicated right-side panel when "Add Member" clicked
- List all eligible portal users (not already in project)
- Search/filter users by name, email, department
- Select multiple users at once (checkboxes)
- Show selected count badge
- Bulk assign role (default: Project Member)
- "Add Selected" button to add all at once

#### B. Invite Non-Portal Users
- Form at top of panel to invite by email
- For Portal Admins:
  - If email in allowed domains → Send invite to portal + project
  - If email NOT in allowed domains → Send invite anyway (admin override)
- For Non-Admins:
  - If email in allowed domains → Send invite to portal + project
  - If email NOT in allowed domains → Create approval request for admin
  
#### C. Visual Feedback & Notifications
- Show status indicators:
  - ✅ "Invited to portal and project"
  - ⏳ "Pending admin approval"
  - ❌ "Email not allowed (request admin approval)"
- Email notifications:
  - User: "You've been invited to [Project]"
  - Admin: "Approval requested for [User] to join portal"
- Toast notifications for success/errors

#### D. Bulk Project Assignment from User Profile
- When viewing another user's profile
- Show section: "Projects where I can add [User]"
- List all projects where logged-in user has PROJECT_EDIT permission
- Checkboxes to select multiple projects
- "Add to Selected Projects" button
- Bulk add in single operation

**Implementation:**
- New controller: `ProjectMemberController`
- Offcanvas component: `AddMembersPanel.vue`
- API endpoints:
  - `GET /projects/{id}/eligible-members` - List users not in project
  - `POST /projects/{id}/members/bulk-add` - Add multiple members
  - `POST /projects/{id}/members/invite` - Invite by email
  - `POST /users/{id}/add-to-projects` - Bulk add user to projects
- Email templates:
  - `project_invitation.html.twig`
  - `admin_approval_request.html.twig`
- Notification types:
  - PROJECT_MEMBER_ADDED
  - PROJECT_INVITATION_RECEIVED
  - ADMIN_APPROVAL_REQUESTED

**Allowed Domains Integration:**
- Check `portal_settings` table for allowed_domains JSON
- Validate email domain before sending invitation
- Show domain restrictions in UI
- Admin can override restrictions

**Admin Approval Workflow:**
- Create `user_approval_requests` table
- Track: requested_by, user_email, project_id, status, notes
- Admin dashboard showing pending approvals
- One-click approve/reject actions
- Email notifications on approval/rejection

---

## Implementation Priority Order

Based on impact and dependencies:

1. **Default "General" Milestone** ✅ - COMPLETED
2. **Move Task Options for Root Tasks** - High impact, frequently requested
3. **Enhanced Project Member Management** - Improves onboarding workflow
4. **Profile Hover Cards** - Enhances UX across entire app
5. **Auto-assign Tasks in My Tasks** - Small but useful improvement
6. **Task Footer on Detail Page** - UI consistency improvement

---

## Technical Notes

### Shared Components Needed
- `MoveTaskModal.vue` - Reusable for all move operations
- `ProfileCard.vue` - User profile hover card
- `AddMembersPanel.vue` - Offcanvas member selection
- `BulkActionBar.vue` - Already exists, may need enhancement

### Database Tables to Add
- `user_approval_requests` - Track admin approval requests
- Consider adding `task_move_history` for audit trail

### API Consistency
- Follow RESTful patterns
- Return consistent error formats
- Include validation messages
- Use proper HTTP status codes
- Document all new endpoints in API section above

