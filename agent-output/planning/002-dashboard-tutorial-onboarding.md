---
ID: 2
Origin: 2
UUID: c82d195e
Status: Active
Target Release: N/A (Feature Enhancement - No versioning required)
---

# Plan 002: Dashboard Tutorial Onboarding Process

**Date:** 2026-04-08  
**Epic Alignment:** User Experience - First-Time User Onboarding  
**Status:** Completed - Implementation Successful

## Changelog

| Date | Agent | Action | Summary |
|------|-------|--------|---------|
| 2026-04-08 | Planner | Created | Initial plan for interactive tutorial onboarding |
| 2026-04-08 | Planner | Updated | Resolved open questions - same tutorial all devices, no analytics |
| 2026-04-08 | Critic | Reviewed | Identified 5 findings (2 Medium, 3 Low) - APPROVED WITH CHANGES REQUIRED |
| 2026-04-08 | Planner | Updated | Addressed all critique findings: verified selectors, added CDN fallback, clarified AJAX approach, specified settings UI placement |
| 2026-04-08 | Critic | Approved | All findings resolved - Status: RESOLVED - Plan ready for implementation |
| 2026-04-08 | Implementer | Completed | Implemented all 8 milestones successfully - 3 files modified, ~90 lines added, all syntax validated |

---

## Value Statement and Business Objective

**As a new user, I want an interactive tutorial that guides me through the dashboard on first login, so that I can quickly understand how to log my OJT hours and use key features without confusion.**

### Current Problem
- New users face a learning curve when first accessing the dashboard
- No guided introduction to key features (Today's Status, Time Logs, allowance tracking)
- Users may not understand the workflow: today's quick logging vs. historical logging
- Risk of incorrect usage, confusion, or abandoned adoption due to lack of guidance

### Proposed Solution
- Implement an interactive onboarding tutorial that triggers automatically on first dashboard visit
- Use a lightweight JavaScript library (e.g., Intro.js, Shepherd.js, or Driver.js) to create step-by-step tooltips
- Highlight key UI elements with explanations and context
- Guide users through the essential workflow in a logical sequence
- Provide controls to skip, restart, or navigate the tutorial
- Store tutorial completion state in user database to prevent repeated displays
- Add "Restart Tutorial" option in settings for users who want a refresher

### Success Criteria
- Tutorial displays automatically for first-time users on dashboard load
- Tutorial can be completed in <2 minutes (4-6 steps)
- Tutorial covers all essential features: Today's Status, Recent Logs, Time Logs navigation, allowance tracking
- Users can skip tutorial without breaking functionality
- Tutorial state persisted in database (completed/skipped flag)
- Users can manually restart tutorial from settings page
- No negative impact on dashboard load performance

---

## Objective

Implement an interactive, step-by-step tutorial overlay on the dashboard that automatically guides new users through key features, reducing confusion and improving adoption rates.

---

## Assumptions

1. **Database Schema Extension**: Users table can be extended with a `tutorial_completed` boolean field
2. **JavaScript Library**: A CDN-hosted tutorial library (e.g., Driver.js) can be used without local installation
3. **User Preference**: Most users will complete or skip the tutorial on first view
4. **Performance**: Tutorial library is lightweight (<50KB) and won't significantly impact page load
5. **Browser Compatibility**: Target modern browsers (Chrome, Firefox, Edge, Safari) - no IE11 support needed
6. **Existing UI Elements**: Dashboard structure remains stable (IDs for Today's Status, Recent Logs, etc. exist)
7. **Tutorial Scope**: Tutorial focuses on dashboard only; Time Logs page tutorial deferred to future enhancement

---

## Context & Current State

### Files Involved
- **`dashboard.php`**: Main dashboard view where tutorial will be triggered
- **`includes/config.php`**: User schema and helper functions (add tutorial_completed field)
- **`settings.php`**: Settings page (add "Restart Tutorial" option)
- **`css/dashboard.css`** (or inline styles): Optional custom styling for tutorial elements
- **External CDN**: Driver.js or similar library (no local file needed)

### Current User Schema
- Users table has: `id`, `name`, `username`, `password`, `required_hours`, `allowance_per_day`, `currency`, `security_question`, `security_answer`, `email`
- User data loaded via `get_user()` and saved via `save_user()` in `includes/config.php`

### Tutorial Flow (Exact Selectors Verified)
1. **Welcome** (no element): "Welcome to Practica! Let's take a quick tour of your OJT tracking dashboard."
2. **Today's Status** (`.status-card`): "Log your hours for today here quickly and easily."
3. **Recent Logs** (`.dash-log-table`): "View your recent log entries and edit/delete them as needed."
4. **Time Logs Navigation** (`.table-link`): "Click here to access Time Logs for bulk entry or historical logging."
5. **Progress Tracking** (`.dash-stat-card--progress`): "Monitor your completion progress toward required hours."
6. **Completion** (no element): "You're all set! You can restart this tutorial anytime from Settings."

**Note**: Changed from allowance card to progress card as it's more universally relevant for new users. All CSS selectors verified to exist in dashboard.php.

---

## Implementation Plan

### Milestone 1: Extend User Schema for Tutorial State

**Objective**: Add database field to track tutorial completion status

**Tasks**:
1. Update `includes/config.php` in the `ensure_users_schema()` function
2. Add `tutorial_completed` column to the `$requiredColumns` array
3. Column spec: `TINYINT(1) NOT NULL DEFAULT 0` (0 = not completed, 1 = completed)
4. Ensure migration runs automatically on next page load via existing schema check logic

**Acceptance Criteria**:
- `tutorial_completed` column exists in `users` table after migration
- Default value is `0` for new and existing users
- No errors during schema update

---

### Milestone 2: Update User Helper Functions

**Objective**: Ensure tutorial_completed field is loaded and saved with user data

**Tasks**:
1. Update `get_user()` function in `includes/config.php` to include `tutorial_completed` in returned array
2. Update `save_user()` function to handle `tutorial_completed` field in UPDATE and INSERT statements
3. Add type casting: `$user['tutorial_completed'] = (int) ($user['tutorial_completed'] ?? 0);`

**Acceptance Criteria**:
- `get_user()` returns `tutorial_completed` field
- `save_user()` persists `tutorial_completed` value correctly
- Existing users default to `0` (tutorial not completed)

---

### Milestone 3: Integrate Tutorial Library

**Objective**: Add JavaScript tutorial library to dashboard

**Tasks**:
1. Choose tutorial library: Use **Driver.js v1.3.1+** (lightweight, no dependencies, modern, accessible)
2. Add CDN links to `dashboard.php`:
   - CSS: `<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">`
   - JS: `<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.iife.js"></script>`
3. Place links before closing `</body>` tag (consistent with existing script placement in dashboard.php)
4. Implement CDN fallback mitigation:
   - Tutorial initialization script checks for `window.driver` existence
   - If undefined after CDN load attempt, gracefully skip tutorial with console warning
   - No user-facing error (silent degradation)
   - Alternative: Download Driver.js locally to `/js/vendor/` as backup (optional enhancement)
5. Verify library loads without errors in browser console

**Acceptance Criteria**:
- Driver.js library loads successfully from CDN under normal conditions
- If CDN fails, tutorial gracefully degrades without user-facing errors
- Tutorial initialization checks for `window.driver` before proceeding
- No console errors that break page functionality (warnings acceptable for CDN failures)

---

### Milestone 4: Implement Tutorial Logic

**Objective**: Create tutorial steps and trigger logic based on user state

**Tasks**:
1. Add inline `<script>` block in `dashboard.php` after Driver.js library loads
2. Check `$user['tutorial_completed']` PHP variable in JavaScript context
3. If `tutorial_completed === 0`, initialize and start Driver.js tutorial
4. Define tutorial steps array with exact CSS selectors (verified in codebase):
   - **Step 1**: Welcome message (no element, popover only) - "Welcome to Practica! Let's take a quick tour."
   - **Step 2**: `.status-card` (dashboard.php:180) - Highlight "Today's Status" card for quick daily logging
   - **Step 3**: `.dash-log-table` (dashboard.php:136) - Highlight Recent Logs table
   - **Step 4**: `.table-link` (dashboard.php:133) - Highlight "View all" link to Time Logs page
   - **Step 5**: `.dash-stat-card--progress` (dashboard.php:85) - Highlight progress tracking card (most relevant for new users)
   - **Step 6**: Completion message (no element, popover only) - "You're all set! Restart from Settings anytime."
5. Configure Driver.js options:
   - Allow keyboard navigation (ESC to skip, arrows to navigate)
   - Show progress indicator (e.g., "Step 2 of 6")
   - Show "Skip" and "Next" buttons
   - Use `onDestroyStarted` hook to trigger AJAX POST request on completion OR skip (both = "don't show again")
6. Implement graceful degradation: Check for `window.driver` existence before initialization. If undefined (CDN failed), log console warning and skip tutorial (no error to user)

**Acceptance Criteria**:
- Tutorial starts automatically for users with `tutorial_completed = 0`
- Tutorial does not start for users with `tutorial_completed = 1`
- Tutorial steps highlight correct UI elements using verified selectors
- Users can navigate forward/backward through steps
- Users can skip tutorial at any point (ESC key or Skip button)
- Both completing AND skipping tutorial mark it as completed (user intent: "don't show again")
- Tutorial completion/skip triggers AJAX POST request to backend
- If CDN fails to load Driver.js, tutorial gracefully skips without user-facing errors

---

### Milestone 5: Create Backend Endpoint for Tutorial Completion

**Objective**: Handle AJAX request to mark tutorial as completed

**Tasks**:
1. Add inline POST handler in `dashboard.php` at top of file (after existing action handlers around line 41)
   - Follow existing codebase pattern (consistent with log actions on lines 10-41)
   - No separate `/ajax/` directory needed
2. Check for `$_POST['action'] === 'complete_tutorial'`
3. Retrieve current user from session via `current_user()`
4. Update user's `tutorial_completed` field to `1`
5. Call `save_user($user)` to persist change
6. Return JSON response: `{"success": true}`
7. Handle errors gracefully with JSON error response
8. Exit after JSON output to prevent HTML rendering

**Acceptance Criteria**:
- AJAX endpoint receives POST request with `action=complete_tutorial`
- User's `tutorial_completed` field updates to `1` in database
- Endpoint returns valid JSON response
- Subsequent dashboard loads do not trigger tutorial

---

### Milestone 6: Add "Restart Tutorial" Option in Settings

**Objective**: Allow users to manually restart the tutorial from settings

**Tasks**:
1. Add new `.settings-card` section in `settings.php` titled "Help & Tutorials"
   - Place below existing Password/Security sections
   - Follow existing card styling pattern (lines 63-70: `.settings-card` with border-radius, shadow, padding)
2. Add "Restart Dashboard Tutorial" button with descriptive subtext:
   - Button text: "Restart Dashboard Tutorial"
   - Subtext: "Re-watch the interactive guide that introduces dashboard features"
3. On button click, submit POST request with `action=restart_tutorial`
4. POST handler resets user's `tutorial_completed` field to `0` via `save_user()`
5. Redirect to `dashboard.php` after reset with flash message
6. Dashboard loads and tutorial triggers automatically for user

**Acceptance Criteria**:
- Settings page displays new "Help & Tutorials" section with consistent card styling
- "Restart Dashboard Tutorial" button is clearly labeled with helpful subtext
- Clicking button resets `tutorial_completed` to `0` in database
- User redirected to dashboard where tutorial starts automatically
- Flash message confirms tutorial will restart: "Tutorial reset! The dashboard guide will start now."

---

### Milestone 7: Style Customization (Optional)

**Objective**: Customize tutorial appearance to match dashboard theme

**Tasks**:
1. Identify Driver.js CSS classes (e.g., `.driver-popover`, `.driver-overlay`)
2. Add custom CSS overrides in `css/dashboard.css` or inline in `dashboard.php`
3. Match color scheme to existing dashboard (e.g., primary color, button styles)
4. Ensure high contrast for readability
5. Test on different screen sizes (responsive behavior)

**Acceptance Criteria**:
- Tutorial popover matches dashboard visual style
- Text is readable with sufficient contrast
- Tutorial is usable on mobile/tablet viewports (if dashboard is responsive)
- No visual glitches or layout breaks

---

### Milestone 8: Verification & Testing

**Objective**: Ensure tutorial works correctly across user flows

**Tasks**:
1. Test as new user (tutorial_completed = 0):
   - Tutorial starts automatically on dashboard load
   - All steps display correctly with accurate highlights
   - Navigation buttons (Next, Previous, Skip) work
   - Completing tutorial updates database
   - Refreshing dashboard does not re-trigger tutorial
2. Test as returning user (tutorial_completed = 1):
   - Tutorial does not auto-start
   - Dashboard functions normally
3. Test "Restart Tutorial" from settings:
   - Button resets tutorial_completed to 0
   - Navigating to dashboard triggers tutorial
4. Test skipping tutorial:
   - Click "Skip" button
   - Database updates to completed
   - Tutorial does not re-appear on refresh
5. Browser console check:
   - No JavaScript errors
   - AJAX requests succeed
6. Performance check:
   - Page load time not significantly increased (<100ms difference)

**Acceptance Criteria**:
- All user flows tested successfully
- No JavaScript console errors
- Tutorial state persists correctly
- Performance impact is negligible

---

## Testing Strategy

**Expected Test Types**:
- **Manual UI Testing**: Verify tutorial displays, navigation, and completion
- **Database Testing**: Confirm `tutorial_completed` field updates correctly
- **AJAX Testing**: Verify backend endpoint handles requests properly
- **Regression Testing**: Ensure dashboard functionality unaffected by tutorial
- **Cross-browser Testing** (if applicable): Test in Chrome, Firefox, Edge, Safari

**Critical Scenarios**:
1. New user sees tutorial on first dashboard visit
2. Returning user does not see tutorial
3. User can skip tutorial and it doesn't re-appear
4. User can complete tutorial and it doesn't re-appear
5. User can restart tutorial from settings
6. Tutorial highlights correct UI elements
7. AJAX completion request succeeds
8. Dashboard remains functional with tutorial disabled

---

## Risks & Mitigation

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| CDN library unavailable or slow | High | Low | Graceful degradation: Check `window.driver` existence, skip silently if undefined; optional local hosting in `/js/vendor/` |
| Tutorial breaks on UI changes | Medium | Medium | All selectors verified in codebase and explicitly documented in Milestone 4; use stable semantic classes |
| Users frustrated by forced tutorial | Medium | Low | Provide clear "Skip" button (ESC key also works); never auto-restart after completion/skip |
| Database migration fails | High | Very Low | Existing schema check logic is battle-tested in `ensure_users_schema()`; test migration in dev first |
| Performance degradation | Medium | Low | Driver.js is lightweight (3.5KB gzipped); script loads at end of body; no impact on initial render |
| Tutorial conflicts with existing JS | Medium | Low | Driver.js has no dependencies; check for existence before init; test in isolation |

---

## Rollback Plan

If issues arise post-deployment:
1. Disable tutorial trigger: Set all users' `tutorial_completed` to `1` via SQL
2. Remove CDN links from `dashboard.php`
3. Remove tutorial initialization script
4. Remove "Restart Tutorial" button from settings
5. Leave database schema intact (no harm in having unused column)
6. Investigate root cause before re-enabling

---

## Dependencies

- **External Dependency**: Driver.js library from CDN (or alternative: Intro.js, Shepherd.js)
- **No Backend Dependencies**: Uses existing PHP/MySQL stack

---

## Handoff Notes

### For Critic
- This plan introduces a new feature (tutorial) with database schema changes
- Scope is appropriately small (dashboard only, single feature)
- No architectural changes; leverages existing user management system
- CDN dependency is acceptable for this use case (fallback option available)

### For Implementer
- **Library Choice**: Driver.js recommended, but Intro.js or Shepherd.js are acceptable alternatives with similar APIs
- **Selectors**: Use existing element IDs/classes in dashboard.php (e.g., `.status-card`, `.dash-log-table`)
- **AJAX Implementation**: Can use vanilla JavaScript Fetch API or integrate with existing AJAX patterns in the codebase
- **Tutorial Content**: Keep step descriptions concise (1-2 sentences per step)
- **Accessibility**: Ensure tutorial is keyboard-navigable and screen-reader friendly (Driver.js provides this by default)
- **Testing**: Create a test user account to verify tutorial flows without affecting production data

### For QA
- QA agent will define specific test cases in `agent-output/qa/`
- Focus testing on:
  - Tutorial trigger logic (first-time vs. returning users)
  - Tutorial completion persistence
  - Restart functionality
  - Cross-browser compatibility
  - AJAX request/response validation

---

## Open Questions

**OPEN QUESTION [RESOLVED]**: Which tutorial library should we use?  
**Resolution**: Recommend **Driver.js** (modern, lightweight, no dependencies). Alternative: Intro.js (more mature, slightly heavier).

**OPEN QUESTION [RESOLVED]**: Should tutorial be dismissible or required?  
**Resolution**: Dismissible with clear "Skip" button. Never force users to complete tutorial.

**OPEN QUESTION [RESOLVED]**: Should tutorial state be per-user or per-browser (localStorage)?  
**Resolution**: Per-user in database. Ensures tutorial state persists across devices/browsers.

**OPEN QUESTION [RESOLVED]**: Should mobile/tablet users see a different (simplified) tutorial?  
**Resolution**: Show the same tutorial on all devices. Driver.js is responsive and will adapt to viewport size. No separate mobile tutorial needed.

**OPEN QUESTION [RESOLVED]**: Should we add analytics tracking for tutorial completion rates?  
**Resolution**: No analytics tracking in this implementation. Focus on core tutorial functionality. Analytics can be added in future enhancement if needed.

---

## Notes

- This is a **feature enhancement** with no user-facing feature changes to existing functionality, so no version bump is required unless product owner decides otherwise
- The tutorial is additive only - existing dashboard functionality remains unchanged
- Tutorial can be safely disabled by removing CDN links and trigger script
- Future enhancement: Extend tutorial to Time Logs page, Settings page, etc.
- Consider A/B testing tutorial effectiveness if analytics are added later

---

## Technical Architecture (ILLUSTRATIVE ONLY)

**Database Schema Addition:**
```sql
-- ILLUSTRATIVE ONLY - Implementer will use existing migration pattern
ALTER TABLE users ADD COLUMN tutorial_completed TINYINT(1) NOT NULL DEFAULT 0;
```

**Tutorial Initialization (Conceptual Flow):**
```javascript
// ILLUSTRATIVE ONLY - Not prescriptive code
if (userTutorialCompleted === 0) {
    const driver = window.driver({
        showProgress: true,
        steps: [
            { element: '.status-card', popover: { title: 'Step 2', description: '...' } },
            // ... more steps
        ],
        onDestroyStarted: () => {
            // Mark tutorial complete via AJAX
            fetch('dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=complete_tutorial'
            });
        }
    });
    driver.drive();
}
```

**This is for conceptual understanding only. Implementer has freedom to implement using best practices.**
