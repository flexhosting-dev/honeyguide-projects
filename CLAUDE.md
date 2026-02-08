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
