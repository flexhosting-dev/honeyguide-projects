# Known Bugs

## Gantt Chart Native Sidebar Not Displaying

**Status:** In Progress
**Branch:** dev
**Date:** 2026-02-17

**Description:**
The native Frappe Gantt sidebar implementation is not rendering. The sidebar container and SVG are created but the sidebar is not visible.

**Changes Made:**
- Modified `frappe-gantt.js` to add native sidebar support with separate container architecture
- Added `gantt-outer-container` (flex) → `gantt-sidebar-container` + `gantt-container`
- Added sidebar rendering methods, grouping logic, and event handling
- Updated CSS with sidebar styles
- Simplified `GanttView.js` to use native sidebar instead of Vue sidebar

**Files Affected:**
- `public/vendor/frappe-gantt/frappe-gantt.js`
- `public/vendor/frappe-gantt/frappe-gantt.css`
- `assets/vue/components/GanttView.js`

**Needs Investigation:**
- Verify sidebar container is being inserted into DOM correctly
- Check if sidebar SVG dimensions are set properly
- Verify CSS flex layout is working
- May need to restore Vue sidebar as fallback

