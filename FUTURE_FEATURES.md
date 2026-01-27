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

## 8. Rich Text Editor & File Attachments

**Priority:** High
**Complexity:** Medium
**Impact:** Enhanced content creation and document management

### Overview
Add rich text editing (basic formatting) and file attachments to descriptions across Projects, Milestones, and Tasks. Transform plain text fields into full-featured content areas.

### Scope

| Entity | Rich Text | Attachments |
|--------|-----------|-------------|
| Project Description | ✓ | ✓ |
| Milestone Description | ✓ | ✓ |
| Task Description | ✓ | ✓ |

### Rich Text Editor Features

**Basic Formatting Toolbar:**
```
┌────────────────────────────────────────────────────────────────────────────┐
│ B  I  U  S  │ H1 H2 H3 │ • ─ 1. │ "" │ <> │ 🔗 │ 📎 │         [Markdown] │
└────────────────────────────────────────────────────────────────────────────┘
│                                                                            │
│ This is **bold** and _italic_ text.                                       │
│                                                                            │
│ ## Heading                                                                 │
│                                                                            │
│ - Bullet point                                                             │
│ - Another item                                                             │
│                                                                            │
│ 1. Numbered list                                                           │
│ 2. Second item                                                             │
│                                                                            │
│ > Blockquote for important notes                                          │
│                                                                            │
│ `inline code` and code blocks                                             │
│                                                                            │
│ [Link text](https://example.com)                                          │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

**Supported Formatting:**
| Format | Toolbar | Shortcut | Markdown |
|--------|---------|----------|----------|
| Bold | B | Ctrl+B | `**text**` |
| Italic | I | Ctrl+I | `_text_` |
| Underline | U | Ctrl+U | — |
| Strikethrough | S | — | `~~text~~` |
| Heading 1 | H1 | — | `# text` |
| Heading 2 | H2 | — | `## text` |
| Heading 3 | H3 | — | `### text` |
| Bullet List | • | — | `- item` |
| Numbered List | 1. | — | `1. item` |
| Blockquote | "" | — | `> text` |
| Code Inline | <> | — | `` `code` `` |
| Code Block | <> | — | ``` ``` |
| Link | 🔗 | Ctrl+K | `[text](url)` |
| Horizontal Rule | ─ | — | `---` |

**Editor Library Options:**
1. **Tiptap** (Recommended) - Vue-friendly, extensible, MIT license
2. **Editor.js** - Block-based, clean output
3. **Quill** - Popular, rich features
4. **SimpleMDE** - Markdown-focused, lightweight

**Recommended: Tiptap**
```javascript
import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'

const editor = new Editor({
  element: document.querySelector('#editor'),
  extensions: [StarterKit, Link],
  content: initialContent,
  onUpdate: ({ editor }) => {
    // Auto-save or track changes
  }
})
```

### File Attachments

**Attachment UI in Description:**
```
┌────────────────────────────────────────────────────────────────────────────┐
│ [Editor toolbar...]                                              [📎 Add] │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│ Description content here...                                                │
│                                                                            │
├────────────────────────────────────────────────────────────────────────────┤
│ ATTACHMENTS (3)                                                            │
│ ┌──────────────────────────────────────────────────────────────────────┐  │
│ │ 📄 requirements.pdf (2.4 MB)                    [View] [Download] [×] │  │
│ │ 🖼️ mockup-v2.png (890 KB)                      [View] [Download] [×] │  │
│ │ 📊 data-analysis.xlsx (1.2 MB)                 [View] [Download] [×] │  │
│ └──────────────────────────────────────────────────────────────────────┘  │
│                                                                            │
│ [Drop files here or click to upload]                                       │
└────────────────────────────────────────────────────────────────────────────┘
```

**Attachment Features:**
- Drag & drop upload zone
- Click to browse files
- Multiple file upload
- Progress indicator during upload
- File type icons (PDF, image, document, spreadsheet, etc.)
- Image preview (thumbnails for images)
- View in modal/new tab
- Download button
- Delete with confirmation
- File size display
- Upload limits (configurable, e.g., 10MB per file, 50MB per entity)

**Supported File Types:**
| Category | Extensions | Max Size |
|----------|------------|----------|
| Images | jpg, png, gif, webp, svg | 5 MB |
| Documents | pdf, doc, docx, txt, rtf | 10 MB |
| Spreadsheets | xls, xlsx, csv | 10 MB |
| Archives | zip, rar, 7z | 20 MB |
| Other | * (configurable whitelist) | 10 MB |

### Data Model

**Attachment Entity:**
```php
// src/Entity/Attachment.php
#[ORM\Entity]
class Attachment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 255)]
    private string $storedName;  // UUID-based for uniqueness

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $size;  // bytes

    #[ORM\Column(length: 500)]
    private string $path;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $uploadedBy;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    // Polymorphic relation
    #[ORM\Column(length: 50)]
    private string $attachableType;  // 'project', 'milestone', 'task', 'comment'

    #[ORM\Column(type: 'uuid')]
    private Uuid $attachableId;
}
```

**Entity Updates:**
```php
// src/Entity/Task.php (and Project, Milestone)
#[ORM\Column(type: 'text', nullable: true)]
private ?string $description = null;  // Now stores HTML

#[ORM\Column(type: 'text', nullable: true)]
private ?string $descriptionPlain = null;  // Plain text for search

// Attachments loaded via repository query (polymorphic)
public function getAttachments(): array;
```

### API Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| POST | `/attachments/upload` | Upload file, returns attachment object |
| DELETE | `/attachments/{id}` | Delete attachment |
| GET | `/attachments/{id}/download` | Download file |
| GET | `/{type}/{id}/attachments` | List attachments for entity |
| POST | `/{type}/{id}/description` | Update description (HTML content) |

**Upload Response:**
```json
{
  "id": "uuid",
  "originalName": "document.pdf",
  "mimeType": "application/pdf",
  "size": 2457600,
  "url": "/attachments/uuid/download",
  "thumbnail": "/attachments/uuid/thumbnail"  // for images
}
```

### Storage Strategy

**Directory Structure:**
```
var/uploads/
├── attachments/
│   ├── 2026/
│   │   ├── 01/
│   │   │   ├── abc123-document.pdf
│   │   │   ├── def456-image.png
│   │   │   └── def456-image-thumb.png
│   │   └── 02/
│   └── ...
```

**Storage Options:**
1. **Local filesystem** (default) - Simple, works everywhere
2. **AWS S3** - Scalable, CDN-friendly
3. **Flysystem abstraction** - Switch between storage backends

### Security Considerations

- Validate file types (check magic bytes, not just extension)
- Scan for malware (ClamAV integration)
- Randomize stored filenames (prevent enumeration)
- Check file size before upload completes
- Serve files through controller (not direct public access)
- CSRF protection on upload/delete
- Permission check (can user access this entity?)

### Implementation Phases

1. **Attachment Infrastructure**
   - Create Attachment entity and migration
   - File upload service with validation
   - Storage abstraction (local first)
   - Download controller with auth check

2. **Rich Text Editor**
   - Integrate Tiptap (or chosen editor)
   - Create Vue component wrapper
   - Toolbar customization
   - HTML sanitization (prevent XSS)

3. **Task Description**
   - Replace textarea with rich editor
   - Add attachment zone below editor
   - Update panel and detail page

4. **Project & Milestone**
   - Add rich editor to project description
   - Add rich editor to milestone description
   - Attachment support for both

5. **Image Handling**
   - Generate thumbnails on upload
   - Image preview in lightbox
   - Paste image from clipboard

### Files Affected

**Backend:**
- New: `src/Entity/Attachment.php`
- New: `src/Service/FileUploadService.php`
- New: `src/Controller/AttachmentController.php`
- New: `migrations/VersionXXX.php`
- Modified: `src/Entity/Task.php` - HTML description field
- Modified: `src/Entity/Project.php`
- Modified: `src/Entity/Milestone.php`
- Config: `config/services.yaml` - Upload paths, limits

**Frontend:**
- New: `assets/vue/components/RichTextEditor.js`
- New: `assets/vue/components/AttachmentZone.js`
- New: `assets/vue/components/AttachmentList.js`
- Modified: `templates/task/_panel.html.twig`
- Modified: `templates/task/show.html.twig`
- Modified: `templates/project/show.html.twig`
- Modified: `templates/milestone/show.html.twig`

---

## 9. Enhanced Comments (Attachments & @Mentions)

**Priority:** Medium
**Complexity:** Medium
**Impact:** Improved team communication and collaboration

### Overview
Enhance the task comment system with file attachments and @mention functionality for notifying team members directly within comments.

### Enhanced Comment UI

```
┌────────────────────────────────────────────────────────────────────────────┐
│ COMMENTS (5)                                                               │
├────────────────────────────────────────────────────────────────────────────┤
│ ┌────────────────────────────────────────────────────────────────────────┐ │
│ │ 👤 John Doe                                              2 hours ago  │ │
│ │                                                                        │ │
│ │ @Jane can you review the API changes? I've attached the updated       │ │
│ │ specs document.                                                        │ │
│ │                                                                        │ │
│ │ 📎 Attachments:                                                        │ │
│ │ ┌──────────────────────────────────────┐                               │ │
│ │ │ 📄 api-specs-v2.pdf (1.2 MB) [View]  │                               │ │
│ │ └──────────────────────────────────────┘                               │ │
│ │                                                        [Edit] [Delete] │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│ ┌────────────────────────────────────────────────────────────────────────┐ │
│ │ 👤 Jane Smith                                           30 mins ago   │ │
│ │                                                                        │ │
│ │ @John looks good! Just a few minor changes needed:                    │ │
│ │ - Update the auth endpoint                                             │ │
│ │ - Add rate limiting docs                                               │ │
│ │                                                        [Edit] [Delete] │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
├────────────────────────────────────────────────────────────────────────────┤
│ ┌────────────────────────────────────────────────────────────────────────┐ │
│ │ Add a comment...                                                       │ │
│ │                                                                        │ │
│ │ Type @ to mention someone                                   [📎] [➤] │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────────┘
```

### @Mentions Feature

**Mention Autocomplete:**
```
┌────────────────────────────────────────────────────────────────────────────┐
│ Hey @j█                                                                    │
│     ┌─────────────────────────────┐                                        │
│     │ 👤 John Doe                 │                                        │
│     │ 👤 Jane Smith               │                                        │
│     │ 👤 James Wilson             │                                        │
│     └─────────────────────────────┘                                        │
└────────────────────────────────────────────────────────────────────────────┘
```

**Mention Features:**
- Type `@` to trigger autocomplete
- Filter by typing name
- Arrow keys to navigate, Enter to select
- Shows project members first, then all users
- Mentioned users appear as styled chips/links
- Click mention to view user profile

**Mention Display:**
```html
<!-- Stored in database -->
Hey <mention data-user-id="uuid">@John Doe</mention> can you check this?

<!-- Rendered -->
Hey <a href="/users/uuid" class="mention">@John Doe</a> can you check this?
```

### Comment Attachments

**Attachment Button in Comment Input:**
- Paperclip icon next to send button
- Opens file picker
- Drag & drop onto comment area
- Paste image from clipboard
- Multiple files per comment
- Same file type/size limits as description attachments

**Comment with Attachments Storage:**
```php
// Comment can have multiple attachments via polymorphic relation
// attachableType = 'comment', attachableId = comment.id
```

### Notifications

**When @Mentioned:**
1. **In-App Notification**
   ```
   🔔 John Doe mentioned you in a comment on "API Integration"
   ```

2. **Email Notification** (if enabled)
   ```
   Subject: John mentioned you in "API Integration"

   John Doe mentioned you in a comment:

   "@Jane can you review the API changes?"

   [View Comment]
   ```

3. **Real-Time** (with WebSocket feature)
   - Instant notification popup
   - Unread badge on notifications icon

**Notification Entity:**
```php
#[ORM\Entity]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $recipient;

    #[ORM\Column(length: 50)]
    private string $type;  // 'mention', 'assignment', 'due_date', etc.

    #[ORM\Column(type: 'json')]
    private array $data;  // { actorId, taskId, commentId, ... }

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
}
```

### Data Model Updates

**Comment Entity:**
```php
// src/Entity/Comment.php
#[ORM\Column(type: 'text')]
private string $content;  // Now can contain mention markup

#[ORM\Column(type: 'json', nullable: true)]
private ?array $mentionedUserIds = null;  // For quick notification lookup

// Attachments via polymorphic Attachment entity
public function getAttachments(): array;
public function getMentionedUsers(): array;
```

### API Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/projects/{id}/members/search?q=` | Search users for @mention |
| POST | `/tasks/{id}/comments` | Create comment (with mentions/attachments) |
| GET | `/notifications` | Get user notifications |
| POST | `/notifications/mark-read` | Mark notifications as read |

**Create Comment Request:**
```json
{
  "content": "Hey <mention data-user-id=\"uuid\">@John</mention> check this",
  "mentionedUserIds": ["uuid1", "uuid2"],
  "attachmentIds": ["attach-uuid-1", "attach-uuid-2"]
}
```

### Implementation Phases

1. **@Mention Autocomplete**
   - User search endpoint (project members)
   - Autocomplete dropdown component
   - Mention insertion in editor
   - Mention parsing and storage

2. **Mention Display**
   - Render mentions as styled links
   - User hover card (optional)

3. **Notifications**
   - Notification entity and migration
   - Create notifications on mention
   - Notification list UI
   - Mark as read functionality

4. **Comment Attachments**
   - Add attachment button to comment form
   - File upload integration
   - Display attachments in comments
   - Delete attachment from comment

5. **Email Notifications**
   - Email templates for mentions
   - User preference for email notifications
   - Queue emails for async sending

### Files Affected

**Backend:**
- New: `src/Entity/Notification.php`
- New: `src/Service/NotificationService.php`
- New: `src/Service/MentionParser.php`
- Modified: `src/Entity/Comment.php` - Add mentionedUserIds
- Modified: `src/Controller/CommentController.php` - Handle mentions
- New: `src/Controller/NotificationController.php`
- New: `migrations/VersionXXX.php`

**Frontend:**
- New: `assets/vue/components/MentionInput.js` - Autocomplete editor
- New: `assets/vue/components/NotificationDropdown.js`
- Modified: `assets/vue/components/CommentsEditor.js` - Integrate mentions
- Modified: `templates/layout.html.twig` - Notification bell icon
- New: `templates/notification/_list.html.twig`

### Editor Integration

**Using Tiptap Mention Extension:**
```javascript
import Mention from '@tiptap/extension-mention'

const editor = new Editor({
  extensions: [
    StarterKit,
    Mention.configure({
      HTMLAttributes: { class: 'mention' },
      suggestion: {
        items: ({ query }) => fetchUsers(query),
        render: () => ({ /* dropdown renderer */ }),
      },
    }),
  ],
})
```

---

## 10. User Notifications System

**Priority:** High
**Complexity:** Medium
**Impact:** Keeps users informed and engaged with project activity

### Overview
Comprehensive notification system with in-app notifications, optional email notifications, and user-customizable preferences for notification types and delivery methods.

### Notification Types

| Event | Description | Default In-App | Default Email |
|-------|-------------|----------------|---------------|
| **Task Assigned** | You were assigned to a task | ✓ | ✓ |
| **Task Unassigned** | You were removed from a task | ✓ | ○ |
| **Task Completed** | A task you're assigned to was completed | ✓ | ○ |
| **Task Due Soon** | Task due in 24/48 hours | ✓ | ✓ |
| **Task Overdue** | Task is past due date | ✓ | ✓ |
| **Comment Added** | New comment on your task | ✓ | ✓ |
| **@Mentioned** | Someone mentioned you | ✓ | ✓ |
| **Comment Reply** | Reply to your comment | ✓ | ○ |
| **Project Invited** | Added to a project | ✓ | ✓ |
| **Project Removed** | Removed from a project | ✓ | ✓ |
| **Milestone Due** | Milestone due soon | ✓ | ○ |
| **Task Status Changed** | Status change on your task | ✓ | ○ |
| **Attachment Added** | File added to your task | ○ | ○ |
| **Subtask Completed** | Subtask of your task completed | ○ | ○ |

### In-App Notifications UI

**Notification Bell (Header):**
```
┌────────────────────────────────────────────────────────────────┐
│  WorkFlow                           🔍   🔔(3)   👤 John ▼    │
└────────────────────────────────────────────────────────────────┘
                                           │
                                           ▼
┌─────────────────────────────────────────────────────────────┐
│ NOTIFICATIONS                              [Mark all read]  │
├─────────────────────────────────────────────────────────────┤
│ ● Jane Smith assigned you to "API Integration"    2m ago   │
│   Project Alpha                                    [View]   │
├─────────────────────────────────────────────────────────────┤
│ ● Bob mentioned you in a comment                  15m ago   │
│   "Setup database schema"                          [View]   │
├─────────────────────────────────────────────────────────────┤
│ ● Task "Login UI" is due tomorrow                  1h ago   │
│   Project Beta                                     [View]   │
├─────────────────────────────────────────────────────────────┤
│ ○ Alice completed "Unit tests"                     3h ago   │
│   Project Alpha                                    [View]   │
├─────────────────────────────────────────────────────────────┤
│ ○ You were added to Project Gamma                Yesterday  │
│                                                    [View]   │
├─────────────────────────────────────────────────────────────┤
│                    [View All Notifications]                 │
└─────────────────────────────────────────────────────────────┘

● = Unread    ○ = Read
```

**Full Notifications Page (`/notifications`):**
```
┌─────────────────────────────────────────────────────────────────────────────┐
│ NOTIFICATIONS                                                               │
├──────────────────┬──────────────────────────────────────────────────────────┤
│ FILTERS          │                                                          │
│                  │  ┌─────────────────────────────────────────────────────┐ │
│ ○ All            │  │ ● Jane Smith assigned you to "API Integration"     │ │
│ ● Unread (3)     │  │   Project Alpha · 2 minutes ago                     │ │
│ ○ Mentions       │  │                                            [View →] │ │
│ ○ Assignments    │  └─────────────────────────────────────────────────────┘ │
│ ○ Comments       │                                                          │
│ ○ Due Dates      │  ┌─────────────────────────────────────────────────────┐ │
│                  │  │ ● Bob mentioned you in a comment                    │ │
│ ───────────────  │  │   "Hey @John can you review this?"                  │ │
│                  │  │   Setup database schema · 15 minutes ago            │ │
│ [⚙️ Settings]    │  │                                            [View →] │ │
│                  │  └─────────────────────────────────────────────────────┘ │
│                  │                                                          │
│                  │  ┌─────────────────────────────────────────────────────┐ │
│                  │  │ ● Task "Login UI" is due tomorrow                   │ │
│                  │  │   Project Beta · 1 hour ago                         │ │
│                  │  │                                            [View →] │ │
│                  │  └─────────────────────────────────────────────────────┘ │
│                  │                                                          │
│                  │  [Load More...]                                          │
└──────────────────┴──────────────────────────────────────────────────────────┘
```

### User Notification Preferences

**Settings Page (`/settings/notifications`):**
```
┌─────────────────────────────────────────────────────────────────────────────┐
│ NOTIFICATION PREFERENCES                                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│ EMAIL SETTINGS                                                              │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ Email Frequency:  ○ Instant  ● Daily Digest  ○ Weekly Digest  ○ Never  │ │
│ │                                                                         │ │
│ │ Daily digest sent at: [09:00 AM ▼]                                      │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│ NOTIFICATION TYPES                                            In-App  Email │
│ ─────────────────────────────────────────────────────────────────────────── │
│                                                                             │
│ TASKS                                                                       │
│ ├─ Assigned to task                                            [✓]    [✓]  │
│ ├─ Unassigned from task                                        [✓]    [ ]  │
│ ├─ Task completed                                              [✓]    [ ]  │
│ ├─ Task status changed                                         [✓]    [ ]  │
│ ├─ Task due in 24 hours                                        [✓]    [✓]  │
│ └─ Task overdue                                                [✓]    [✓]  │
│                                                                             │
│ COMMENTS                                                                    │
│ ├─ New comment on your task                                    [✓]    [✓]  │
│ ├─ @Mentioned in comment                                       [✓]    [✓]  │
│ └─ Reply to your comment                                       [✓]    [ ]  │
│                                                                             │
│ PROJECTS                                                                    │
│ ├─ Added to project                                            [✓]    [✓]  │
│ ├─ Removed from project                                        [✓]    [✓]  │
│ └─ Milestone due soon                                          [✓]    [ ]  │
│                                                                             │
│ ATTACHMENTS                                                                 │
│ └─ File added to your task                                     [ ]    [ ]  │
│                                                                             │
│                                                       [Reset to Defaults]  │
│                                                       [Save Preferences]   │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Data Model

**Notification Entity:**
```php
// src/Entity/Notification.php
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(columns: ['recipient_id', 'is_read', 'created_at'])]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $recipient;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $actor = null;  // Who triggered the notification

    #[ORM\Column(length: 50)]
    private string $type;  // 'task_assigned', 'mentioned', 'comment', etc.

    #[ORM\Column(type: 'json')]
    private array $data;
    // {
    //   taskId, taskTitle,
    //   projectId, projectName,
    //   commentId, commentPreview,
    //   ...
    // }

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $emailSent = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailSentAt = null;
}
```

**User Notification Preferences:**
```php
// src/Entity/UserNotificationPreference.php
#[ORM\Entity]
#[ORM\Table(uniqueConstraints: [
    new ORM\UniqueConstraint(columns: ['user_id', 'notification_type'])
])]
class UserNotificationPreference
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\Column(length: 50)]
    private string $notificationType;

    #[ORM\Column]
    private bool $inAppEnabled = true;

    #[ORM\Column]
    private bool $emailEnabled = true;
}

// Or simpler: JSON column on User entity
// src/Entity/User.php
#[ORM\Column(type: 'json')]
private array $notificationPreferences = [
    'email_frequency' => 'instant',  // 'instant', 'daily', 'weekly', 'never'
    'email_time' => '09:00',
    'types' => [
        'task_assigned' => ['in_app' => true, 'email' => true],
        'mentioned' => ['in_app' => true, 'email' => true],
        // ...
    ]
];
```

### Notification Service

```php
// src/Service/NotificationService.php
class NotificationService
{
    public function notify(
        User $recipient,
        string $type,
        array $data,
        ?User $actor = null
    ): void {
        // Check user preferences
        $prefs = $this->getUserPreferences($recipient, $type);

        // Create in-app notification if enabled
        if ($prefs['in_app']) {
            $notification = new Notification();
            $notification->setRecipient($recipient);
            $notification->setActor($actor);
            $notification->setType($type);
            $notification->setData($data);
            $this->em->persist($notification);

            // Real-time push (if WebSocket enabled)
            $this->realtimePusher->push($recipient, $notification);
        }

        // Queue email if enabled
        if ($prefs['email']) {
            $this->emailQueue->add($recipient, $type, $data);
        }
    }

    public function notifyMany(array $recipients, string $type, array $data): void
    {
        foreach ($recipients as $recipient) {
            $this->notify($recipient, $type, $data);
        }
    }
}
```

### Email Notifications

**Email Frequency Options:**
1. **Instant** - Email sent immediately (via queue)
2. **Daily Digest** - All notifications compiled into one daily email
3. **Weekly Digest** - Weekly summary email
4. **Never** - No emails, in-app only

**Email Templates:**

*Instant Email (Task Assigned):*
```
Subject: You've been assigned to "API Integration"

Hi John,

Jane Smith assigned you to a task:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
API Integration
Project: Project Alpha
Due: January 25, 2026
Priority: High
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[View Task]

---
You're receiving this because you have email notifications enabled.
[Manage notification preferences]
```

*Daily Digest:*
```
Subject: Your WorkFlow Daily Digest - 5 notifications

Hi John,

Here's what happened today:

TASKS
• You were assigned to "API Integration" by Jane
• "Login UI" is due tomorrow
• Bob completed "Database setup"

COMMENTS
• Jane mentioned you: "@John can you review?"
• 2 new comments on "Homepage design"

[View All in WorkFlow]

---
[Manage notification preferences]
```

### API Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/notifications` | List notifications (paginated) |
| GET | `/notifications/unread-count` | Get unread count for badge |
| POST | `/notifications/{id}/read` | Mark single as read |
| POST | `/notifications/mark-all-read` | Mark all as read |
| DELETE | `/notifications/{id}` | Delete notification |
| GET | `/settings/notifications` | Get preferences |
| PUT | `/settings/notifications` | Update preferences |

**Notifications Response:**
```json
{
  "notifications": [
    {
      "id": "uuid",
      "type": "task_assigned",
      "actor": { "id": "uuid", "name": "Jane Smith", "initials": "JS" },
      "data": {
        "taskId": "uuid",
        "taskTitle": "API Integration",
        "projectId": "uuid",
        "projectName": "Project Alpha"
      },
      "isRead": false,
      "createdAt": "2026-01-28T10:30:00Z",
      "timeAgo": "2 minutes ago"
    }
  ],
  "unreadCount": 3,
  "hasMore": true
}
```

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

### Cleanup & Retention

- Auto-delete read notifications after 30 days
- Auto-delete all notifications after 90 days
- Scheduled cleanup command
- User can manually clear all notifications

---

## 11. Additional Future Considerations

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
