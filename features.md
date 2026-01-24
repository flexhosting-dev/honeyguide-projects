# Future Features

This document tracks planned features for the ZohoClone project.

---

## Tagging System

### Description
Add tagging functionality to organize and filter items across the application.

### Scope
- **Task Tags**: Color-coded tags that can be applied to tasks for categorization
- **Project Tags**: Tags for organizing projects by type, client, or category (future)
- **Milestone Tags**: Tags for milestone classification (future)

### Features
- Create, edit, delete tags with custom colors
- Filter views by tags
- Tag autocomplete when adding to items
- Bulk tag operations

### User Experience
- Tags can be added when viewing a task (in task panel) or when creating/editing a task
- As user types a tag name, existing tags are searched and shown as autocomplete suggestions
- If the tag doesn't exist and user has permission (can edit task), the tag is created on-the-fly
- No need to navigate to a dedicated tag management screen - tags are created inline
- Users can click existing tags to remove them from a task
- Tags display with their assigned color as a visual indicator

---

## Activity Logs

### Description
Comprehensive activity logging system to track all changes to tasks, milestones, and projects.

### Scope
- **Task Activity**: Log all task field changes with before/after values
- **Milestone Activity**: Log milestone updates and task movements
- **Project Activity**: Log project-level changes

### Task Activity Events to Track
- Task created
- Title changed (from "X" to "Y")
- Description changed
- Status changed (from "To Do" to "In Progress")
- Priority changed (from "None" to "High")
- Start date changed
- Due date changed
- Assignee added
- Assignee removed
- Milestone changed
- Comment added
- (Future fields as they are added)

### UI Implementation
- Add tabs to task detail panel: "Comments" | "Activity"
- Activity tab loads asynchronously when clicked (lazy loading)
- Timeline view showing chronological activity
- Filter activity by type (field changes, assignments, comments)

### Data Model
```
TaskActivity:
  - id
  - task_id
  - user_id (who made the change)
  - action (created, updated, assigned, unassigned, commented)
  - field (nullable - which field changed)
  - old_value (nullable)
  - new_value (nullable)
  - created_at
```

---

## Task Checklists

### Description
Simple checklist items within tasks for breaking down work into smaller steps.

### Features
- Add checklist items (title only, no nesting)
- Mark items complete/incomplete
- Reorder items via drag-drop
- Progress indicator (3/5 complete)
- Quick add with Enter key

### UI Implementation
- In the task detail panel, add tabs: "Checklist" | "Comments"
- Checklist tab loads asynchronously when clicked (lazy loading)
- Only fetch checklist data after the task panel is already open
- Show progress summary in tab header (e.g., "Checklist (3/5)")

### Data Model
```
TaskChecklist:
  - id
  - task_id
  - title
  - is_completed
  - position
  - created_at
```

---

## Notes

- Features are listed in no particular order of priority
- Each feature should be fully specified before implementation
- Consider dependencies between features when planning sprints
