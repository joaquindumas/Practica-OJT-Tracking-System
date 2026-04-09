---
ID: 2
Origin: 2
UUID: c82d195e
Status: Resolved
---

# Critique 002: Dashboard Tutorial Onboarding Process

**Date:** 2026-04-08  
**Plan:** agent-output/planning/002-dashboard-tutorial-onboarding.md  
**Critic:** Workflow Critic Agent  
**Plan Status at Review:** Active - Awaiting Critic Review

---

## Executive Summary

**Overall Assessment:** ✅ **APPROVED WITH RECOMMENDATIONS**

Plan 002 is well-structured, delivers clear user value, and follows established patterns from the existing codebase. The scope is appropriate, milestones are logical, and risks are adequately identified. However, several technical and implementation details require clarification or adjustment before handoff to the Implementer.

**Key Strengths:**
- Clear value statement aligned with UX improvement goals
- Leverages existing database migration pattern (no new infrastructure)
- Appropriate library choice (Driver.js - lightweight, modern, no dependencies)
- Database-backed persistence (cross-device consistency)
- Well-defined rollback plan
- Scope is focused and deliverable

**Key Concerns (ALL RESOLVED):**
- ✅ **MEDIUM**: CDN fallback strategy now specified with graceful degradation
- ✅ **MEDIUM**: Tutorial step selectors verified and explicitly documented with line numbers
- ✅ **LOW**: AJAX endpoint approach clarified (inline handler, matches codebase patterns)
- ✅ **LOW**: Settings page integration details completed with UI placement guidance

---

## Findings

### Finding 1: Selector Mismatch (MEDIUM) ✅ RESOLVED

**Issue:** Plan proposes tutorial step selectors that don't match actual dashboard HTML structure.

**RESOLUTION:** Planner updated Milestone 4 with explicit selector verification:

**Evidence:**
- Plan suggests `.status-card` (Line 398) - ✅ EXISTS (dashboard.php:180)
- Plan suggests `.dash-log-table` (Line 399) - ✅ EXISTS (dashboard.php:136)
- Plan suggests `.table-link` (Line 399) - ✅ EXISTS (dashboard.php:133)
- Plan suggests `.dash-stat-card--allowance` (Line 399) - ✅ EXISTS (dashboard.php:111)

**However:**
- Plan references "allowance card" but there are THREE stat cards:
  - `.dash-stat-card--progress` (Progress tracking)
  - `.dash-stat-card--remaining` (Hours remaining)
  - `.dash-stat-card--allowance` (Allowance tracking)
- The tutorial flow (lines 85-91) lists 6 steps but only highlights 4 UI elements
- No clear mapping between proposed tutorial steps and actual selectors

**Recommendation:**
Update Milestone 4 to explicitly list the exact CSS selectors and verify they exist:
1. Step 1: Welcome (no element) ✅
2. Step 2: `.status-card` - Today's Status card ✅
3. Step 3: `.dash-log-table` - Recent Logs table ✅
4. Step 4: `.table-link` - "View all" link to Time Logs ✅
5. Step 5: `.dash-stat-card--allowance` - Allowance tracking card ✅
6. Step 6: Completion (no element) ✅

Alternative: Highlight all three stat cards in sequence or choose the most relevant one for new users (likely `.dash-stat-card--progress` for tracking completion).

- Step 1: Welcome (no element) ✅
- Step 2: `.status-card` (dashboard.php:180) ✅
- Step 3: `.dash-log-table` (dashboard.php:136) ✅
- Step 4: `.table-link` (dashboard.php:133) ✅
- Step 5: `.dash-stat-card--progress` (dashboard.php:85) ✅ (changed from allowance to progress - more relevant)
- Step 6: Completion (no element) ✅

All selectors verified in codebase with line numbers. Tutorial flow section also updated (lines 87-93).

**Impact if not addressed:** Tutorial may fail to highlight correct elements, causing confusion or JavaScript errors.  
**STATUS:** ✅ **RESOLVED** - All selectors explicitly documented and verified.

---

### Finding 2: CDN Fallback Not Implemented (MEDIUM) ✅ RESOLVED

**Issue:** Plan identifies CDN unavailability as "High Impact" risk but provides no concrete mitigation in implementation milestones.

**RESOLUTION:** Planner updated Milestone 3 and Milestone 4 with graceful degradation:

**Evidence:**
- Risk table (Line 300): "CDN library unavailable or slow | High | Low | Host library locally as fallback; implement timeout check"
- Milestone 3 (Lines 130-146): Only adds CDN links, no fallback logic
- No milestone task addresses local hosting or timeout detection

**Recommendation:**
Add a subtask to Milestone 3:
- "Implement CDN fallback: Add `<script>` with timeout check. If Driver.js fails to load within 3 seconds, load from local copy (`/js/vendor/driver.js`) or disable tutorial gracefully."

Alternative minimal approach:
- Add note in Milestone 3: "Check for `window.driver` existence before initializing tutorial. If undefined, log warning and skip tutorial (graceful degradation)."

Milestone 3 Task 4 (lines 144-148):
- Tutorial initialization checks for `window.driver` existence
- If undefined, gracefully skip tutorial with console warning only
- No user-facing errors (silent degradation)
- Optional enhancement: local hosting in `/js/vendor/`

Milestone 4 Task 6 (line 179):
- "Implement graceful degradation: Check for `window.driver` existence before initialization"

Acceptance criteria updated (lines 152-155):
- CDN failure results in graceful degradation
- No console errors that break functionality (warnings acceptable)

Risk table updated (line 301): Specific mitigation strategy documented.

**Impact if not addressed:** Tutorial completely fails if CDN is down/blocked, with no user-facing explanation. Risk is marked High Impact but has no actual implementation safeguard.  
**STATUS:** ✅ **RESOLVED** - Graceful degradation implemented with `window.driver` check.

---

### Finding 3: AJAX Endpoint Approach Unclear (LOW) ✅ RESOLVED

**Issue:** Plan proposes two different approaches for tutorial completion endpoint but doesn't specify which to use.

**RESOLUTION:** Planner updated Milestone 5 to specify inline POST handler:

**Evidence:**
- Milestone 5 (Line 185): "Add POST handler in `dashboard.php` (or create dedicated endpoint like `ajax/mark_tutorial_complete.php`)"
- No clear guidance on which approach implementer should use
- Existing codebase uses inline POST handlers in `dashboard.php` (lines 10-41) for log actions

**Recommendation:**
Follow existing pattern from Plan 001 implementation and codebase conventions:
- **Preferred:** Add inline POST handler in `dashboard.php` (consistent with existing log actions)
- Update Milestone 5 to specify: "Add POST handler in `dashboard.php` at the top of the file (after existing action handlers, line ~41)"

**Rationale:** 
- Existing codebase already uses inline handlers for all dashboard actions
- No `/ajax/` directory exists in the project
- Creating new architecture pattern for single endpoint is over-engineering

Milestone 5 Task 1 (lines 198-200):
- "Add inline POST handler in `dashboard.php` at top of file (after existing action handlers around line 41)"
- "Follow existing codebase pattern (consistent with log actions on lines 10-41)"
- "No separate `/ajax/` directory needed"

Task 8 added: "Exit after JSON output to prevent HTML rendering"

**Impact if not addressed:** Implementer may waste time deciding between approaches or choose inconsistent pattern.  
**STATUS:** ✅ **RESOLVED** - Inline POST handler approach specified, matches existing patterns.

---

### Finding 4: Settings Page Integration Incomplete (LOW) ✅ RESOLVED

**Issue:** Milestone 6 describes "Restart Tutorial" feature but doesn't specify where in Settings UI to place it.

**RESOLUTION:** Planner updated Milestone 6 with explicit UI placement:

**Evidence:**
- settings.php has sections for Profile, Password, Security (lines 60+)
- No "Help" or "Tutorial" section exists
- Plan says "Add button or link" but doesn't specify visual placement or section grouping

**Recommendation:**
Update Milestone 6 to specify:
- "Add new settings card titled 'Help & Tutorials' below existing sections"
- "Include button 'Restart Dashboard Tutorial' with descriptive subtext: 'Re-watch the interactive dashboard guide'"
- Follow existing `.settings-card` styling pattern (lines 63-70 in settings.php)

Alternative:
- Add to existing "Profile Settings" section as a separate row (simpler integration)

Milestone 6 Tasks (lines 222-231):
- Task 1: "Add new `.settings-card` section in `settings.php` titled 'Help & Tutorials'"
- "Place below existing Password/Security sections"
- "Follow existing card styling pattern (lines 63-70: `.settings-card` with border-radius, shadow, padding)"
- Task 2: Specific button text and subtext provided
- Task 5: Flash message text specified: "Tutorial reset! The dashboard guide will start now."

Acceptance criteria updated (lines 234-238) with specific UI requirements.

**Impact if not addressed:** Implementer makes arbitrary UI placement decision without UX consistency guidance.  
**STATUS:** ✅ **RESOLVED** - New "Help & Tutorials" section specified with styling guidance.

---

### Finding 5: Tutorial Completion AJAX Logic Ambiguous (LOW) ✅ RESOLVED

**Issue:** Plan states tutorial should mark completion "on completion OR skip" but implementation details are unclear.

**RESOLUTION:** Planner clarified skip/completion behavior:

**Evidence:**
- Milestone 4 (Line 168): "On completion or skip, make AJAX POST request"
- Driver.js has multiple lifecycle hooks: `onDestroyStarted`, `onDestroy`, `onDestroyCompleted`
- Plan's illustrative code (Line 403) uses `onDestroyStarted` which fires on BOTH skip and completion
- No guidance on whether skipping should be treated differently from completing

**Recommendation:**
Clarify in Milestone 4:
- "Use `onDestroyStarted` hook to mark tutorial as completed regardless of skip/completion status. User intent is 'don't show again' in both cases."

Add acceptance criterion:
- "Skipping tutorial via ESC or 'Skip' button marks tutorial as completed (same as finishing all steps)"

Milestone 4 Task 5 (line 178):
- "Use `onDestroyStarted` hook to trigger AJAX POST request on completion OR skip (both = 'don't show again')"

Acceptance criteria updated (lines 187-188):
- "Both completing AND skipping tutorial mark it as completed (user intent: 'don't show again')"

Explicit clarification that skip = complete for persistence purposes.

**Impact if not addressed:** Low - implementer likely arrives at correct solution, but explicit guidance reduces guesswork.  
**STATUS:** ✅ **RESOLVED** - Skip behavior explicitly clarified as equivalent to completion.

---

## Architecture Review

### Database Schema ✅ APPROVED

- Uses existing migration pattern in `ensure_users_schema()` (config.php lines 42-68)
- New column `tutorial_completed TINYINT(1) NOT NULL DEFAULT 0` follows conventions
- Migration is additive-only (safe, no data loss risk)
- Properly integrated with `get_user()` and `save_user()` helpers

**No changes needed.**

---

### JavaScript Integration ✅ APPROVED WITH NOTES

- Driver.js is appropriate choice (3.5KB gzipped, modern, accessible)
- CDN approach acceptable for this project size
- Inline `<script>` block is consistent with existing dashboard.php patterns (lines 287+)

**Note:** Plan's illustrative code (lines 393-413) is appropriately labeled "ILLUSTRATIVE ONLY" and conceptually sound.

---

### Testing Strategy ✅ APPROVED

- Manual UI testing appropriate for feature scope
- Critical scenarios well-identified (Lines 284-292)
- No unit testing needed (minimal business logic)
- Regression testing scope is appropriate

**No changes needed.**

---

## Value Delivery Review

**Value Statement (Lines 24-26):**  
> "As a new user, I want an interactive tutorial that guides me through the dashboard on first login, so that I can quickly understand how to log my OJT hours and use key features without confusion."

**Assessment:** ✅ Value is delivered

- Tutorial reduces onboarding friction ✅
- Guides through essential workflow (quick logging vs. historical) ✅
- Dismissible (respects user agency) ✅
- Restartable (users can revisit if needed) ✅
- Core value NOT deferred - all essentials in initial implementation ✅

Success criteria are measurable (Lines 43-50):
- <2 minutes completion time ✅ (4-6 steps)
- Covers essential features ✅ (Today's Status, Recent Logs, Time Logs, Allowance)
- No performance degradation ✅ (<50KB library)

---

## Risk Assessment

| Risk | Plan Rating | Critic Assessment | Notes |
|------|-------------|-------------------|-------|
| CDN unavailable | High Impact, Low Likelihood | ⚠️ **MITIGATION NEEDED** | No fallback implemented despite High rating |
| Tutorial breaks on UI changes | Medium/Medium | ✅ Acceptable | Good selector documentation mitigates |
| Users frustrated by forced tutorial | Medium/Low | ✅ Well-handled | Clear skip button, never auto-restarts |
| Database migration fails | High/Very Low | ✅ Acceptable | Existing pattern is battle-tested |
| Performance degradation | Medium/Low | ✅ Acceptable | Library is lightweight |

**Recommendation:** Address CDN fallback per Finding 2.

---

## Scope Review

**Estimated Complexity:** Medium (2-3 days implementation)  

**Files Modified:**
1. `includes/config.php` (schema + helpers)
2. `dashboard.php` (CDN links + tutorial script)
3. `settings.php` (restart button)
4. Optional: `css/dashboard.css` (custom styling)

**Scope Assessment:** ✅ **APPROPRIATE**

- Single feature, cohesive deliverable
- No architectural changes
- Leverages existing infrastructure
- <10 files modified
- Atomic work (no dependencies on other features)

---

## Recommendations Summary

### Must Address Before Implementation (MEDIUM Priority)

1. **Finding 1:** Verify and explicitly list exact CSS selectors for each tutorial step in Milestone 4
2. **Finding 2:** Add CDN fallback strategy to Milestone 3 (at minimum: graceful degradation with `window.driver` check)

### Should Address Before Implementation (LOW Priority)

3. **Finding 3:** Specify inline POST handler approach in Milestone 5 (align with codebase patterns)
4. **Finding 4:** Add UI placement guidance for settings "Restart Tutorial" button in Milestone 6
5. **Finding 5:** Clarify skip vs. completion behavior in Milestone 4 acceptance criteria

---

## Approval Status

✅ **APPROVED - READY FOR IMPLEMENTATION**

**All findings resolved. Plan is implementation-ready.**

**Completed Steps:**
1. ✅ Planner updated plan to address Finding 1 and Finding 2 (MEDIUM priority)
2. ✅ Planner addressed Findings 3-5 for implementation clarity (LOW priority)
3. ✅ Critic verified updates and moved to RESOLVED status
4. **NEXT:** Handoff to Implementer for execution

---

## Changelog

| Date | Agent | Action | Summary |
|------|-------|--------|---------|
| 2026-04-08 | Critic | Created | Initial review of Plan 002 - Identified 5 findings (2 Medium, 3 Low) |
| 2026-04-08 | Planner | Updated Plan | Addressed all 5 findings with explicit technical details |
| 2026-04-08 | Critic | Verified | All findings resolved - Status changed to RESOLVED - APPROVED for implementation |

---

## Notes for Implementer (Post-Approval)

- Use Driver.js v1.3.1+ (or latest stable at implementation time)
- Follow existing dashboard.php JavaScript patterns (check for element existence with `?.` optional chaining)
- Test tutorial on clean browser (no cached tutorial_completed state)
- Consider creating a demo/test user for repeated testing
- Allowance card selector is confirmed `.dash-stat-card--allowance` but consider if `.dash-stat-card--progress` is more relevant for new users (discuss with Planner if unclear)
