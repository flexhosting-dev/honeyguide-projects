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

### Ideal Use Cases for This App

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

## 3. Project Dashboard / Homepage

**Priority:** Medium
**Complexity:** Medium
**Impact:** Provides at-a-glance project overview and quick access to key metrics

### Overview
Add a dedicated "Overview" or "Dashboard" tab as the first tab in project view (before Milestones). This serves as the project's homepage, showing key metrics, recent activity, and quick actions.

### Tab Order
```
[Overview] [Milestones] [Tasks] [Members] [Activity] [Settings]
     ↑
   NEW
```

### Dashboard Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  PROJECT DASHBOARD                                                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐             │
│  │   12 / 45       │  │   3             │  │   Jan 31        │             │
│  │   Tasks Done    │  │   Overdue       │  │   Next Deadline │             │
│  │   ████████░░░   │  │   ⚠️ Warning    │  │   Feature X     │             │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘             │
│                                                                             │
│  ┌─────────────────────────────────────┐  ┌─────────────────────────────┐  │
│  │  TASK STATUS BREAKDOWN              │  │  MILESTONE PROGRESS         │  │
│  │  ┌────────────────────────────────┐ │  │                             │  │
│  │  │ To Do        ████████░░ 15     │ │  │  Phase 1    ████████████ ✓ │  │
│  │  │ In Progress  ████░░░░░░  8     │ │  │  Phase 2    ████████░░░░   │  │
│  │  │ In Review    ██░░░░░░░░  4     │ │  │  Phase 3    ██░░░░░░░░░░   │  │
│  │  │ Completed    ████████░░ 12     │ │  │  Phase 4    ░░░░░░░░░░░░   │  │
│  │  └────────────────────────────────┘ │  │                             │  │
│  └─────────────────────────────────────┘  └─────────────────────────────┘  │
│                                                                             │
│  ┌─────────────────────────────────────┐  ┌─────────────────────────────┐  │
│  │  MY TASKS IN THIS PROJECT           │  │  TEAM MEMBERS               │  │
│  │                                     │  │                             │  │
│  │  ☐ Implement login API    Due: 2d  │  │  👤 John D. (Owner)         │  │
│  │  ☐ Fix navbar bug         Due: 3d  │  │  👤 Jane S. (5 tasks)       │  │
│  │  ☐ Review PR #42          Overdue  │  │  👤 Bob M. (3 tasks)        │  │
│  │                                     │  │  👤 Alice K. (2 tasks)      │  │
│  │  [View All My Tasks →]              │  │  [+ Invite Member]          │  │
│  └─────────────────────────────────────┘  └─────────────────────────────┘  │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  RECENT ACTIVITY                                                      │  │
│  │                                                                       │  │
│  │  • John completed "Setup database schema"              2 hours ago   │  │
│  │  • Jane commented on "API design"                      3 hours ago   │  │
│  │  • Bob moved "Frontend layout" to In Progress          5 hours ago   │  │
│  │  • Alice created new task "Unit tests"                 Yesterday     │  │
│  │                                                                       │  │
│  │  [View Full Activity →]                                               │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Dashboard Widgets

1. **Summary Stats Cards**
   - Tasks completed / total (with progress bar)
   - Overdue tasks count (warning indicator)
   - Next deadline (task name + date)
   - Optional: Tasks due this week

2. **Task Status Breakdown**
   - Horizontal bar chart or stacked bar
   - Shows count per status
   - Clickable to filter task list

3. **Milestone Progress**
   - List of milestones with progress bars
   - Completion percentage
   - Checkmark for completed milestones
   - Click to navigate to milestone

4. **My Tasks (Current User)**
   - Tasks assigned to logged-in user in this project
   - Shows 3-5 most urgent (by due date)
   - Quick status toggle
   - Link to full "My Tasks" filtered by project

5. **Team Members**
   - Avatar list of project members
   - Task count per member
   - Quick invite button (for owners/admins)
   - Click to see member's tasks

6. **Recent Activity Feed**
   - Last 5-10 activities in project
   - Compact format
   - Link to full activity tab

### Optional Widgets (Future)

- **Burndown Chart** - Sprint/milestone progress over time
- **Workload Distribution** - Tasks per team member (bar chart)
- **Upcoming Deadlines** - Calendar view of next 7 days
- **Blockers/At Risk** - Tasks marked as blocked or at risk
- **Quick Actions** - Create task, create milestone, invite member

### Technical Implementation

**Controller:**
```php
// src/Controller/ProjectController.php
#[Route('/projects/{id}', name: 'app_project_show')]
public function show(Project $project): Response
{
    return $this->render('project/show.html.twig', [
        'project' => $project,
        'active_tab' => 'overview',  // Default to overview
        'dashboard_data' => $this->getDashboardData($project),
    ]);
}

private function getDashboardData(Project $project): array
{
    return [
        'task_stats' => $this->taskRepository->getStatusCounts($project),
        'overdue_count' => $this->taskRepository->countOverdue($project),
        'next_deadline' => $this->taskRepository->getNextDeadline($project),
        'milestone_progress' => $this->milestoneRepository->getProgress($project),
        'my_tasks' => $this->taskRepository->findByUserAndProject($this->getUser(), $project, limit: 5),
        'recent_activity' => $this->activityRepository->findByProject($project, limit: 10),
    ];
}
```

**Repository Methods:**
```php
// src/Repository/TaskRepository.php
public function getStatusCounts(Project $project): array;
public function countOverdue(Project $project): int;
public function getNextDeadline(Project $project): ?Task;
public function findByUserAndProject(User $user, Project $project, int $limit): array;
```

### Implementation Phases

1. **Backend Data**
   - Add repository methods for dashboard queries
   - Create dashboard data service/aggregator
   - Optimize queries (single query for stats where possible)

2. **Template Structure**
   - Create `templates/project/_overview.html.twig`
   - Add Overview tab to project navigation
   - Responsive grid layout for widgets

3. **Stat Cards Component**
   - Reusable stat card partial
   - Progress bar component
   - Warning/success indicators

4. **Charts (Optional)**
   - Integrate Chart.js or similar (via CDN)
   - Task status pie/bar chart
   - Milestone burndown (if time tracking exists)

5. **Interactive Elements**
   - Quick status toggle on "My Tasks"
   - Clickable stats to filter/navigate
   - Refresh button for real-time updates

### Files Affected

**Backend:**
- Modified: `src/Controller/ProjectController.php` - Dashboard data
- Modified: `src/Repository/TaskRepository.php` - Stats queries
- Modified: `src/Repository/MilestoneRepository.php` - Progress queries
- Optional: `src/Service/ProjectDashboardService.php` - Aggregate logic

**Frontend:**
- New: `templates/project/_overview.html.twig` - Dashboard template
- Modified: `templates/project/show.html.twig` - Add Overview tab
- New: `templates/components/_stat_card.html.twig` - Reusable stat card
- New: `templates/components/_progress_bar.html.twig` - Progress bar component
- Optional: `assets/js/charts.js` - Chart initialization

### Caching Considerations

Dashboard queries could be expensive. Consider:
- Cache stats for 5 minutes (invalidate on task changes)
- Lazy-load activity feed via AJAX
- Use database views for complex aggregations

---

## 4. Subtasks (Nested Tasks)

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
     └── Subtask (Level 1)
         └── Sub-subtask (Level 2)
             └── Sub-sub-subtask (Level 3) ← Maximum depth
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
┌─────────────────────────────────────────────────────────┐
│ [+ Add Subtask]                          2/5 completed  │
├─────────────────────────────────────────────────────────┤
│ ☑ Design database schema                    ✓ Completed │
│ ☑ Create API endpoints                      ✓ Completed │
│ ☐ Build frontend components                 → In Progress│
│   └── ☐ Create form component                  To Do    │
│   └── ☐ Create list component                  To Do    │
│ ☐ Write tests                                  To Do    │
│ ☐ Documentation                                To Do    │
└─────────────────────────────────────────────────────────┘
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

## 5. Gantt Chart View

**Priority:** Medium
**Complexity:** High
**Impact:** Visual timeline planning and dependency management

### Overview
Add Gantt chart visualization for tasks at both project level and global (cross-project) level. Enables timeline-based planning, dependency tracking, and resource allocation visibility.

### Two Levels of Gantt View

**1. Project Gantt** - All tasks within a single project
- Access: New "Gantt" tab in project view (after Tasks tab)
- Scope: Tasks grouped by milestone
- Use case: Sprint planning, project timeline management

**2. Global Gantt** - Tasks across all projects
- Access: New "Timeline" item in main sidebar navigation
- Scope: All tasks assigned to user OR all tasks (admin view)
- Use case: Cross-project resource planning, executive overview

### Gantt Chart Layout

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│  PROJECT GANTT                                        [Day] [Week] [Month] [Qtr] │
│  ◀ Jan 2026                                                          Feb 2026 ▶ │
├────────────────────┬─────────────────────────────────────────────────────────────┤
│  TASK              │ 1  2  3  4  5  6  7  8  9  10 11 12 13 14 15 16 17 18 19 20│
├────────────────────┼─────────────────────────────────────────────────────────────┤
│  ▼ Phase 1         │                                                             │
│    Database design │ ████████████                                                │
│    API endpoints   │          ████████████████                                   │
│    Unit tests      │                   └──────▶ ████████                         │
│                    │                            (dependency)                     │
│  ▼ Phase 2         │                                                             │
│    Frontend UI     │                ████████████████████████                     │
│    Integration     │                                    ████████████████         │
│                    │                                                             │
│  ▼ Phase 3         │                                                             │
│    Documentation   │                                              ░░░░░░░░░░░░░░ │
│    Deployment      │                                                    ████████ │
└────────────────────┴─────────────────────────────────────────────────────────────┘

Legend: ████ In Progress   ░░░░ Not Started   ──▶ Dependency   │Today
```

### Core Features

1. **Timeline Visualization**
   - Horizontal bars showing task duration (start date → due date)
   - Color coding by status or priority
   - Today marker (vertical line)
   - Zoom levels: Day, Week, Month, Quarter

2. **Task Grouping**
   - Project Gantt: Group by Milestone (collapsible)
   - Global Gantt: Group by Project (collapsible)
   - Optional: Group by assignee

3. **Dependency Lines**
   - Visual arrows connecting dependent tasks
   - Types: Finish-to-Start, Start-to-Start, Finish-to-Finish
   - Auto-adjust dates when dependencies shift (optional)

4. **Interactive Editing**
   - Drag bar edges to adjust start/due dates
   - Drag entire bar to move task timeline
   - Click task to open detail panel
   - Right-click context menu (edit, delete, add dependency)

5. **Critical Path Highlighting**
   - Identify tasks that directly impact project end date
   - Highlight in red/bold
   - Show slack time for non-critical tasks

### Global Gantt Specific Features

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│  MY TIMELINE                                    [All Projects ▼] [Export PDF]    │
├────────────────────┬─────────────────────────────────────────────────────────────┤
│  ▼ Project Alpha   │ Jan                    Feb                    Mar           │
│    Task A          │ ████████                                                    │
│    Task B          │      ████████████                                           │
│                    │                                                             │
│  ▼ Project Beta    │                                                             │
│    Task X          │         ████████████████                                    │
│    Task Y          │                    ████████████████                         │
│                    │                                                             │
│  ▼ Project Gamma   │                                                             │
│    Task 1          │ ████████████████████████████                                │
└────────────────────┴─────────────────────────────────────────────────────────────┘
```

- Filter by project(s)
- Filter by date range
- Show only "My Tasks" or all tasks (permission-based)
- Cross-project dependency visualization
- Workload indicators (over-allocated dates highlighted)

### Data Requirements

**Task Entity Additions:**
```php
// For dependencies (new entity)
// src/Entity/TaskDependency.php
#[ORM\Entity]
class TaskDependency
{
    #[ORM\ManyToOne(targetEntity: Task::class)]
    private Task $predecessor;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'dependencies')]
    private Task $successor;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = 'finish_to_start'; // FS, SS, FF, SF

    #[ORM\Column(type: 'integer')]
    private int $lagDays = 0; // Delay between tasks
}
```

**Task Entity Updates:**
```php
// src/Entity/Task.php
#[ORM\OneToMany(mappedBy: 'successor', targetEntity: TaskDependency::class)]
private Collection $dependencies; // Tasks this task depends on

#[ORM\OneToMany(mappedBy: 'predecessor', targetEntity: TaskDependency::class)]
private Collection $dependents; // Tasks that depend on this task
```

### API Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/projects/{id}/gantt` | Get project tasks formatted for Gantt |
| GET | `/gantt/global` | Get all user tasks for global Gantt |
| POST | `/tasks/{id}/dependencies` | Add task dependency |
| DELETE | `/tasks/{id}/dependencies/{depId}` | Remove dependency |
| PATCH | `/tasks/{id}/dates` | Update start/due dates (drag) |
| GET | `/projects/{id}/critical-path` | Calculate critical path |

### Gantt Response Format

```json
{
  "tasks": [
    {
      "id": "uuid",
      "title": "Database design",
      "startDate": "2026-01-05",
      "dueDate": "2026-01-12",
      "status": "completed",
      "priority": "high",
      "progress": 100,
      "milestone": { "id": "uuid", "name": "Phase 1" },
      "assignees": [...],
      "dependencies": [
        { "predecessorId": "uuid", "type": "finish_to_start", "lag": 0 }
      ]
    }
  ],
  "milestones": [
    { "id": "uuid", "name": "Phase 1", "dueDate": "2026-01-15" }
  ],
  "dateRange": { "start": "2026-01-01", "end": "2026-03-31" }
}
```

### Technical Implementation

**Library Options:**
1. **Frappe Gantt** (Recommended) - Lightweight, MIT license, vanilla JS
2. **DHTMLX Gantt** - Feature-rich, commercial license
3. **Bryntum Gantt** - Enterprise-grade, expensive
4. **Custom with D3.js** - Full control, more development time

**Recommended: Frappe Gantt**
```html
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.min.js"></script>
```

```javascript
const tasks = [
  { id: 'task-1', name: 'Database design', start: '2026-01-05', end: '2026-01-12', progress: 100 },
  { id: 'task-2', name: 'API endpoints', start: '2026-01-10', end: '2026-01-20', progress: 60, dependencies: 'task-1' },
];

const gantt = new Gantt('#gantt-container', tasks, {
  view_mode: 'Week',
  on_click: (task) => openTaskPanel(task.id),
  on_date_change: (task, start, end) => updateTaskDates(task.id, start, end),
});
```

### Implementation Phases

1. **Dependencies System**
   - Create TaskDependency entity
   - Migration for dependency table
   - API endpoints for CRUD
   - UI for adding dependencies in task panel

2. **Project Gantt (Basic)**
   - Integrate Frappe Gantt library
   - Create Gantt tab in project view
   - Read-only visualization
   - Task click opens panel

3. **Project Gantt (Interactive)**
   - Drag to adjust dates
   - Dependency line visualization
   - Zoom controls (day/week/month)
   - Milestone markers

4. **Global Gantt**
   - New sidebar navigation item
   - Cross-project data aggregation
   - Project filtering
   - Export functionality

5. **Advanced Features**
   - Critical path calculation
   - Auto-scheduling (shift dependent tasks)
   - Workload/resource view
   - Baseline comparison (planned vs actual)

### Files Affected

**Backend:**
- New: `src/Entity/TaskDependency.php`
- New: `migrations/VersionXXX.php` - Dependencies table
- Modified: `src/Entity/Task.php` - Add dependency relations
- New: `src/Controller/GanttController.php` - Gantt endpoints
- New: `src/Service/CriticalPathService.php` - Critical path calculation

**Frontend:**
- New: `templates/project/_gantt.html.twig` - Project Gantt view
- New: `templates/gantt/index.html.twig` - Global Gantt page
- New: `assets/js/gantt.js` - Gantt initialization and handlers
- Modified: `templates/project/show.html.twig` - Add Gantt tab
- Modified: `templates/layout.html.twig` - Add Timeline to sidebar
- Modified: `templates/task/_panel.html.twig` - Add dependencies section

### Mobile Considerations

Gantt charts are challenging on mobile. Options:
- Show simplified list view on small screens
- Horizontal scroll with touch support
- "Timeline" view as alternative (vertical)
- Prompt user to rotate to landscape

---

## 6. Task Filters (Side Panel)

**Priority:** High
**Complexity:** Low-Medium
**Impact:** Significantly improves task discovery and management in large projects

### Overview
Add a collapsible filter panel on the side of task views (list, kanban) allowing users to filter tasks by multiple criteria. Filters persist in URL for shareability and bookmarking.

### Filter Panel Location

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  PROJECT TASKS                                              [Filter ▼] [+ Task] │
├──────────────────┬──────────────────────────────────────────────────────────────┤
│                  │                                                              │
│  FILTERS         │  KANBAN VIEW                                                 │
│  ───────────     │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐            │
│                  │  │ To Do   │ │In Prog  │ │In Review│ │Complete │            │
│  Status          │  │         │ │         │ │         │ │         │            │
│  ☑ To Do         │  │  Card   │ │  Card   │ │  Card   │ │  Card   │            │
│  ☑ In Progress   │  │  Card   │ │  Card   │ │         │ │  Card   │            │
│  ☑ In Review     │  │  Card   │ │         │ │         │ │  Card   │            │
│  ☐ Completed     │  │         │ │         │ │         │ │         │            │
│                  │  └─────────┘ └─────────┘ └─────────┘ └─────────┘            │
│  Priority        │                                                              │
│  ☐ High          │                                                              │
│  ☐ Medium        │                                                              │
│  ☐ Low           │                                                              │
│  ☐ None          │                                                              │
│                  │                                                              │
│  Assignee        │                                                              │
│  [Select... ▼]   │                                                              │
│                  │                                                              │
│  Milestone       │                                                              │
│  [Select... ▼]   │                                                              │
│                  │                                                              │
│  Due Date        │                                                              │
│  ○ Any           │                                                              │
│  ○ Overdue       │                                                              │
│  ○ Due today     │                                                              │
│  ○ Due this week │                                                              │
│  ○ Due this month│                                                              │
│  ○ No due date   │                                                              │
│  ○ Custom range  │                                                              │
│                  │                                                              │
│  Tags            │                                                              │
│  [Select... ▼]   │                                                              │
│                  │                                                              │
│  [Clear All]     │                                                              │
│                  │                                                              │
└──────────────────┴──────────────────────────────────────────────────────────────┘
```

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
   - Toggle button in header: `[Filter ▼]` / `[Filter ▲]`
   - Remembers open/closed state (localStorage)
   - Shows active filter count: `[Filter (3) ▼]`

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
   Active: [Status: To Do ×] [Priority: High ×] [Assignee: John ×] [Clear All]
   ```

5. **Filter Counts**
   - Show count next to each option
   - Updates dynamically as other filters change
   ```
   Status
   ☑ To Do (12)
   ☑ In Progress (5)
   ☐ Completed (23)
   ```

6. **Quick Filters (Header Shortcuts)**
   - Common filters as buttons above task view
   ```
   [My Tasks] [Overdue] [Due This Week] [Unassigned] [More Filters...]
   ```

### Global vs Project Filters

**Project Task View:**
- All filters available
- Milestone filter shows project milestones

**My Tasks (Global):**
- Additional "Project" filter
- Milestone filter grouped by project
- Cross-project filtering

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

**JavaScript (Client-Side Filtering Option):**
```javascript
// For instant filtering without server round-trip
const filterTasks = (tasks, filters) => {
    return tasks.filter(task => {
        if (filters.status.length && !filters.status.includes(task.status)) return false;
        if (filters.priority.length && !filters.priority.includes(task.priority)) return false;
        if (filters.assignees.length && !task.assignees.some(a => filters.assignees.includes(a.id))) return false;
        // ... more conditions
        return true;
    });
};
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

## 7. Advanced Task Table View

**Priority:** High
**Complexity:** Medium-High
**Impact:** Power-user productivity, spreadsheet-like task management

### Overview
Add a full-featured datatable view for tasks with spreadsheet-like capabilities: sortable/resizable columns, column visibility toggle, instant search, grouping, inline editing, and quick task creation anywhere in the table.

### Table Layout

```
┌──────────────────────────────────────────────────────────────────────────────────────────────┐
│  TASK TABLE                    🔍 [Search tasks...]        [Columns ▼] [Group By ▼] [Export] │
├──────────────────────────────────────────────────────────────────────────────────────────────┤
│  ☐ │ Task                    │ Status      │ Priority │ Assignee    │ Due Date   │ Tags     │
│────┼─────────────────────────┼─────────────┼──────────┼─────────────┼────────────┼──────────│
│  ▼ PHASE 1: FOUNDATION (5 tasks, 3 completed)                                      [+ Add]  │
│────┼─────────────────────────┼─────────────┼──────────┼─────────────┼────────────┼──────────│
│  ☐ │ Database schema design  │ ✓ Completed │ High     │ 👤 John     │ Jan 10     │ backend  │
│  ☐ │ Setup CI/CD pipeline    │ ✓ Completed │ Medium   │ 👤 Jane     │ Jan 12     │ devops   │
│  ☐ │ API architecture doc    │ ✓ Completed │ High     │ 👤 John     │ Jan 15     │ docs     │
│  ☐ │ Create base endpoints   │ → Progress  │ High     │ 👤 Bob      │ Jan 20     │ backend  │
│  ☐ │ Unit test framework     │ ○ To Do     │ Medium   │ —           │ Jan 22     │ testing  │
│  + │ Add task...             │             │          │             │            │          │
│────┼─────────────────────────┼─────────────┼──────────┼─────────────┼────────────┼──────────│
│  ▼ PHASE 2: FRONTEND (8 tasks, 1 completed)                                        [+ Add]  │
│────┼─────────────────────────┼─────────────┼──────────┼─────────────┼────────────┼──────────│
│  ☐ │ Component library setup │ ✓ Completed │ High     │ 👤 Alice    │ Jan 18     │ frontend │
│  ☐ │ Login page UI           │ → Progress  │ High     │ 👤 Alice    │ Jan 25     │ frontend │
│  ☐ │ Dashboard layout        │ ○ To Do     │ Medium   │ 👤 Alice    │ Jan 28     │ frontend │
│  + │ Add task...             │             │          │             │            │          │
│────┼─────────────────────────┼─────────────┼──────────┼─────────────┼────────────┼──────────│
│  ▶ PHASE 3: TESTING (4 tasks, 0 completed) — collapsed                             [+ Add]  │
│────┼─────────────────────────┼─────────────┼──────────┼─────────────┼────────────┼──────────│
│  + │ Add new milestone...                                                                   │
└──────────────────────────────────────────────────────────────────────────────────────────────┘
│  Showing 13 of 17 tasks │ Selected: 0 │ ◀ 1 2 3 ▶                                           │
└──────────────────────────────────────────────────────────────────────────────────────────────┘
```

### Core Features

#### 1. Column Management

**Available Columns:**
| Column | Default | Sortable | Editable | Width |
|--------|---------|----------|----------|-------|
| Checkbox (select) | ✓ | — | — | 40px |
| Task Title | ✓ | ✓ | ✓ | flex |
| Status | ✓ | ✓ | ✓ | 120px |
| Priority | ✓ | ✓ | ✓ | 100px |
| Assignee(s) | ✓ | ✓ | ✓ | 150px |
| Due Date | ✓ | ✓ | ✓ | 100px |
| Start Date | ○ | ✓ | ✓ | 100px |
| Tags | ✓ | — | ✓ | 150px |
| Milestone | ○ | ✓ | ✓ | 150px |
| Created | ○ | ✓ | — | 100px |
| Updated | ○ | ✓ | — | 100px |
| Created By | ○ | ✓ | — | 120px |
| Progress | ○ | ✓ | — | 100px |
| Subtasks | ○ | ✓ | — | 80px |
| Comments | ○ | — | — | 80px |
| Description | ○ | — | ✓ | 200px |

**Column Visibility Dropdown:**
```
┌─────────────────────┐
│  VISIBLE COLUMNS    │
├─────────────────────┤
│  ☑ Task Title       │
│  ☑ Status           │
│  ☑ Priority         │
│  ☑ Assignee         │
│  ☑ Due Date         │
│  ☐ Start Date       │
│  ☑ Tags             │
│  ☐ Milestone        │
│  ☐ Created          │
│  ☐ Description      │
├─────────────────────┤
│  [Reset to Default] │
└─────────────────────┘
```

**Column Features:**
- Drag to reorder columns
- Drag column edge to resize
- Click header to sort (asc/desc/none)
- Double-click edge to auto-fit width
- Column config saved to localStorage

#### 2. Instant Search

```
🔍 [Search tasks...________________________] [×]
    ↓
Filters as you type (debounced 200ms)
Searches: title, description, tags, assignee names
Highlights matching text in results
```

- Real-time filtering (client-side for loaded data)
- Highlights search matches
- Combines with active filters
- Clear button to reset

#### 3. Row Grouping

**Group By Dropdown:**
```
┌──────────────────┐
│  GROUP BY        │
├──────────────────┤
│  ○ None          │
│  ● Milestone     │
│  ○ Status        │
│  ○ Priority      │
│  ○ Assignee      │
│  ○ Due Date      │
│    (This Week,   │
│     Next Week,   │
│     Later, None) │
└──────────────────┘
```

**Group Header Features:**
- Collapsible (click to expand/collapse)
- Task count and completion stats
- Bulk actions on group
- Quick "Add task" button per group
- Drag tasks between groups

#### 4. Collapsible Sections

```javascript
// State persisted to localStorage
collapsedGroups: {
  'milestone-uuid-1': false,  // expanded
  'milestone-uuid-2': true,   // collapsed
  'status-completed': true,   // collapsed
}
```

- Click group header to toggle
- Keyboard: Arrow keys to navigate, Enter to toggle
- "Collapse All" / "Expand All" buttons
- Remember state per project

#### 5. Inline Editing

**Click cell to edit:**
```
┌─────────────────────────────────────────────────────┐
│  Status cell clicked:                               │
│  ┌─────────────────┐                                │
│  │ ○ To Do         │  ← Dropdown appears inline    │
│  │ ● In Progress   │                                │
│  │ ○ In Review     │                                │
│  │ ○ Completed     │                                │
│  └─────────────────┘                                │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  Title cell clicked:                                │
│  ┌───────────────────────────────────────┐          │
│  │ Database schema design█               │  ← Input │
│  └───────────────────────────────────────┘          │
│  Enter to save, Esc to cancel                       │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  Due Date cell clicked:                             │
│  ┌──────────────┐                                   │
│  │ 📅 Jan 20    │  ← Date picker                   │
│  │  < Jan 2026 >│                                   │
│  │ Su Mo Tu ... │                                   │
│  └──────────────┘                                   │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  Assignee cell clicked:                             │
│  ┌─────────────────┐                                │
│  │ 🔍 Search...    │                                │
│  │ ☑ John Doe      │  ← Multi-select               │
│  │ ☐ Jane Smith    │                                │
│  │ ☑ Bob Wilson    │                                │
│  └─────────────────┘                                │
└─────────────────────────────────────────────────────┘
```

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

#### 6. Quick Add Row

**Add Task Inline:**
```
│  + │ [Type task title and press Enter...]│          │          │             │            │          │
```

- Empty row at end of each group
- Click or Tab into it to start typing
- Enter creates task with:
  - Title from input
  - Milestone from current group (if grouped by milestone)
  - Status from current group (if grouped by status)
  - Default priority: None
- Shift+Enter: Create and add another
- Tab through cells to set more fields before saving

**Add Milestone Inline:**
```
│  + │ Add new milestone...                                                                   │
```
- Last row in table
- Creates milestone, then shows "Add task" row under it

#### 7. Bulk Actions

**Row Selection:**
- Checkbox column for multi-select
- Click row (outside cells) to select
- Shift+Click for range select
- Ctrl/Cmd+Click for toggle select
- Header checkbox: Select all visible

**Bulk Action Bar (appears when rows selected):**
```
┌────────────────────────────────────────────────────────────────────────┐
│  3 tasks selected    [Set Status ▼] [Set Priority ▼] [Assign ▼] [🗑️]  │
└────────────────────────────────────────────────────────────────────────┘
```

- Set status for all selected
- Set priority for all selected
- Assign/unassign users
- Set milestone
- Add tags
- Delete (with confirmation)

#### 8. Filter Integration

- Responds to side panel filters (Feature #6)
- Table shows filtered results
- Group counts update based on filters
- Empty groups can be hidden or shown
- Filter chips shown above table

### Technical Implementation

**Component Architecture:**
```
TaskTable (Vue component)
├── TableHeader
│   ├── ColumnHeader (sortable, resizable)
│   └── ColumnVisibilityDropdown
├── TableBody
│   ├── GroupRow (collapsible header)
│   │   └── TaskRow (for each task)
│   │       └── EditableCell (per column)
│   └── AddTaskRow
├── BulkActionBar
├── Pagination
└── TableFooter (stats)
```

**State Management:**
```javascript
const tableState = reactive({
  // Data
  tasks: [],
  groups: [],

  // View config
  visibleColumns: ['title', 'status', 'priority', 'assignee', 'dueDate', 'tags'],
  columnOrder: [...],
  columnWidths: { title: 300, status: 120, ... },

  // Grouping
  groupBy: 'milestone', // null | 'milestone' | 'status' | 'priority' | 'assignee'
  collapsedGroups: new Set(),

  // Sorting
  sortColumn: 'dueDate',
  sortDirection: 'asc',

  // Selection
  selectedIds: new Set(),

  // Editing
  editingCell: null, // { taskId, column }

  // Search
  searchQuery: '',
});
```

**Virtualization (for large datasets):**
```javascript
// Only render visible rows for performance
// Use vue-virtual-scroller or similar
<virtual-scroller :items="visibleTasks" :item-height="48">
  <template #default="{ item }">
    <TaskRow :task="item" />
  </template>
</virtual-scroller>
```

### API Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/projects/{id}/tasks/table` | Get tasks with table metadata |
| PATCH | `/tasks/{id}/quick-update` | Update single field (inline edit) |
| POST | `/tasks/bulk-update` | Update multiple tasks |
| POST | `/tasks/quick-create` | Create task with minimal data |
| GET | `/users/{id}/table-preferences` | Get saved column config |
| PUT | `/users/{id}/table-preferences` | Save column config |

**Quick Update Request:**
```json
POST /tasks/{id}/quick-update
{
  "field": "status",
  "value": "in_progress"
}
```

**Bulk Update Request:**
```json
POST /tasks/bulk-update
{
  "taskIds": ["uuid1", "uuid2", "uuid3"],
  "updates": {
    "status": "completed",
    "priority": "high"
  }
}
```

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
- New: `assets/vue/components/TaskTable.js` - Main table component (~500 lines)
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
- Daily/weekly/monthly task templates
- Auto-create tasks on schedule
- Useful for maintenance and routine work

### Advanced Search
- Full-text search across all tasks
- Filter by multiple criteria
- Saved searches/filters

---

*Last updated: January 2026*
