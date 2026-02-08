# AI Coding Agents Guidelines

This file contains instructions for AI coding agents (Claude, Copilot, Cursor, etc.) working on this codebase.

## Changelog Requirement

**IMPORTANT: When making significant changes to this project, you MUST update the changelog.**

### When to add a changelog entry:
- New features
- Bug fixes
- Breaking changes
- Performance improvements
- UI/UX changes
- API changes
- Security fixes

### How to add a changelog entry:

1. **Via Database Migration** (for new releases):
```php
// In a new migration file
$id = Uuid::uuid4()->toString();
$now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
$changes = json_encode([
    'Description of change 1',
    'Description of change 2',
]);
$this->addSql("INSERT INTO changelog (id, version, title, changes, release_date, created_at, updated_at) VALUES ('{$id}', '1.1.0', 'Feature Release', '{$changes}', '2026-02-08', '{$now}', '{$now}')");
```

2. **Via Code** (if adding programmatically):
```php
$changelog = new Changelog();
$changelog->setVersion('1.1.0');
$changelog->setTitle('Feature Release');
$changelog->setChanges([
    'Added new feature X',
    'Fixed bug in Y',
]);
$changelog->setReleaseDate(new \DateTime());
$entityManager->persist($changelog);
$entityManager->flush();
```

### Changelog Table Structure:
- `version` - Semantic version (e.g., "1.0.0", "1.1.0", "2.0.0")
- `title` - Short release title (e.g., "Initial Release", "Bug Fixes", "New Dashboard")
- `changes` - JSON array of change descriptions
- `release_date` - Date of the release

### Version Numbering:
- **Major** (X.0.0): Breaking changes, major rewrites
- **Minor** (0.X.0): New features, significant improvements
- **Patch** (0.0.X): Bug fixes, small improvements

## Other Guidelines

- Follow existing code patterns and conventions
- Write tests for new features when test infrastructure exists
- Update related documentation when changing functionality
