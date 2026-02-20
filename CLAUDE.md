# Claude Code Project Instructions

## Changelog Reminder

**When making significant changes to this project, remember to update the changelog!**

Add a new entry to the `changelog` database table for:
- New features
- Bug fixes
- Breaking changes
- UI/UX improvements

### Quick reference:
```sql
INSERT INTO changelog (id, version, title, changes, release_date, created_at, updated_at)
VALUES (UUID(), '1.x.x', 'Release Title', '["Change 1", "Change 2"]', CURDATE(), NOW(), NOW());
```

Or create a migration - see `AGENTS.md` for full details.

---

## BasePath Handling

**This application may be deployed under a subdirectory (e.g., `/dev` or `/staging`), not at the web root.**

### Rules for API Endpoints and URLs

**ALWAYS respect the basePath when making fetch/AJAX requests:**

#### ❌ WRONG:
```javascript
fetch('/projects/reorder', { ... })
fetch('/tasks/123/update', { ... })
```

#### ✅ CORRECT:
```javascript
// In Twig templates
const basePath = '{{ app.request.basePath }}';
fetch(`${basePath}/projects/reorder`, { ... })

// In standalone JS files
function basePath() {
    return (window.BASE_PATH || '').replace(/\/+$/, '');
}
fetch(`${basePath()}/projects/reorder`, { ... })
```

### Examples

**In Twig templates:**
```twig
<script>
const basePath = '{{ app.request.basePath }}';
const projectId = '{{ project.id }}';

fetch(`${basePath}/projects/${projectId}/milestones/reorder`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({ milestoneIds })
});
</script>
```

**For Symfony routes in Twig:**
```twig
<a href="{{ path('app_project_show', {id: project.id}) }}">Project</a>
<!-- path() helper automatically includes basePath -->
```

**In Vue/JS files:**
```javascript
// Set BASE_PATH globally in layout
window.BASE_PATH = '{{ app.request.basePath }}';

// Use in components
const url = `${window.BASE_PATH}/api/endpoint`;
```

### Why This Matters
- Production: `https://projects.example.com/` (no basePath)
- Staging: `https://dev.flexhosting.co/` (basePath may vary)
- Development: `http://localhost:8000/` (no basePath)

Without proper basePath handling, API calls will fail with 404 errors in non-root deployments.

