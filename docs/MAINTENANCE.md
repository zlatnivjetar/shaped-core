# Maintenance Workflow

> **Last generated:** 2025-12-08

This document defines the automated workflow for keeping shaped-core implementation and documentation in sync.

---

## Table of Contents

1. [Overview](#overview)
2. [Implementation Workflow](#implementation-workflow)
3. [Documentation Audit](#documentation-audit)
4. [Commit Templates](#commit-templates)
5. [Quick Reference](#quick-reference)

---

## Overview

The shaped-core documentation system uses **CLAUDE.md as the single source of truth**. All other documentation files reference and are generated from CLAUDE.md entries.

### System Components

| File | Purpose | Updated By | When |
|------|---------|------------|------|
| `CLAUDE.md` | Implementation log | Claude Code | After every implementation |
| `HOOKS_REFERENCE.md` | Hook reference | Claude Code | From CLAUDE.md |
| `CORE_MODULES.md` | Class/method reference | Claude Code | From CLAUDE.md |
| `CUSTOMIZATION_GUIDE.md` | Extension examples | Claude Code | From CLAUDE.md |
| `DEBUGGING.md` | Troubleshooting | Claude Code | From CLAUDE.md |
| `MAINTENANCE.md` | This workflow | Manual | Rarely |

### The Flow

```
Developer: "Add custom commission hook"
         ↓
Claude Code: Implement in shaped-core
         ↓
Claude Code: Create CLAUDE.md entry (IMPL-###)
         ↓
Claude Code: Auto-update relevant docs
         ↓
Claude Code: Commit code + docs
         ↓
Developer: Everything synced. Ship it.
```

---

## Implementation Workflow

### Step 1: Submit Implementation Request

Use this template when requesting a new feature:

```
IMPLEMENTATION REQUEST:
Feature: [Feature name]
Category: [Pricing|Payments|Bookings|Email|RoomCloud|Reviews|Shortcode|Core]
Description: [What should it do]
Example usage: [How will it be used]

---

AFTER COMPLETING THIS:
1. Create CLAUDE.md entry with IMPL-### ID
2. Update relevant documentation files
3. Commit with proper message format
4. Report completion status
```

### Step 2: Implementation

Claude Code will:

1. **Implement the feature** in shaped-core
2. **Test** the implementation
3. **Document** in CLAUDE.md

### Step 3: CLAUDE.md Entry

After implementation, Claude Code creates an entry:

```markdown
### YYYY-MM-DD IMPL-### – [Feature Name]
**Status:** Complete
**Category:** [Category]

**Files Changed:**
- shaped-core/path/to/file.php (what changed)

**What was added:**
- New hook: `shaped_hook_name`
  - Type: Filter
  - Args: $arg1 (type), $arg2 (type)
  - Returns: Modified $arg1
- New method: `ClassName::method_name($param)`
  - Purpose: What it does
  - Returns: Return type

**Where to find it in docs:**
- HOOKS_REFERENCE.md#shaped_hook_name
- CUSTOMIZATION_GUIDE.md#section-name

**Example usage:**
```php
add_filter('shaped_hook_name', function($arg1, $arg2) {
    // Customization code
    return $arg1;
}, 10, 2);
```

**Related to:** IMPL-### (if applicable)
```

### Step 4: Documentation Updates

Claude Code updates relevant docs based on what was added:

| If Added | Update |
|----------|--------|
| Hook | HOOKS_REFERENCE.md |
| Method/Class | CORE_MODULES.md |
| Shortcode | SHORTCODES_GUIDE.md |
| Extension pattern | CUSTOMIZATION_GUIDE.md |
| Bug fix | DEBUGGING.md |
| RoomCloud feature | ROOMCLOUD_INTEGRATION.md |

Each doc update includes:
- `**Added:** YYYY-MM-DD (IMPL-###)` date reference
- Link back to CLAUDE.md entry

### Step 5: Commit

Claude Code commits with proper format:

```bash
# Code commit
git commit -m "feat: [description] (IMPL-###)

Files: [changed files]
Added: [hooks|methods|shortcodes]
See: docs/CLAUDE.md#IMPL-###"

# Docs commit (if separate)
git commit -m "docs: update for IMPL-### – [description]

Updated: [doc files]
Related: CLAUDE.md#IMPL-###"
```

### Step 6: Completion Report

Claude Code reports:

```
✅ Implementation complete

CLAUDE.md entry: IMPL-### created
Docs updated:
- HOOKS_REFERENCE.md (new hook: shaped_hook_name)
- CUSTOMIZATION_GUIDE.md (new example)
Code committed: abc1234
Ready to use.
```

---

## Documentation Audit

Run this audit every 2 weeks to ensure docs stay in sync.

### Audit Prompt

Copy and paste this to Claude Code:

```
Audit shaped-core repo against /docs/CLAUDE.md:

1. Cross-check CLAUDE.md against actual code:
   - Scan for all hooks (apply_filters, do_action with "shaped" prefix)
   - Scan for all Shaped_ classes and their public methods
   - Compare against entries in CLAUDE.md
   - List: hooks/methods in code NOT in CLAUDE.md
   - List: entries in CLAUDE.md that NO LONGER exist in code

2. Cross-check CLAUDE.md against /docs/:
   - For each CLAUDE.md entry, verify referenced docs exist
   - Check HOOKS_REFERENCE.md has all hooks from CLAUDE.md
   - Check CORE_MODULES.md has all classes from CLAUDE.md
   - List: CLAUDE.md entries not reflected in docs

3. Generate audit report:
   - Missing from CLAUDE.md: [list with file locations]
   - Stale CLAUDE.md entries: [list]
   - Docs needing updates: [list with specific sections]

4. Fix all issues:
   - Add missing entries to CLAUDE.md
   - Remove stale entries
   - Update all affected documentation
   - Single commit: "docs: audit and sync with CLAUDE.md"

5. Report:
   "Audit complete. [X] issues found, [Y] fixed."
   List all changes made.
```

### Audit Checklist

Manual verification points:

- [ ] All `apply_filters('shaped/...')` documented in HOOKS_REFERENCE.md
- [ ] All `do_action('shaped_...')` documented in HOOKS_REFERENCE.md
- [ ] All `Shaped_*` classes documented in CORE_MODULES.md
- [ ] All shortcodes documented in SHORTCODES_GUIDE.md
- [ ] All RoomCloud classes documented in ROOMCLOUD_INTEGRATION.md
- [ ] CLAUDE.md entry count matches statistics
- [ ] No broken doc links
- [ ] All code examples are runnable

---

## Commit Templates

### Feature Implementation

```
feat: [short description] (IMPL-###)

[Optional longer description]

Files: path/to/file.php, path/to/other.php
Added: shaped_hook_name (filter), ClassName::method()
See: docs/CLAUDE.md#IMPL-###
```

### Documentation Update

```
docs: update for IMPL-### – [description]

Updated files:
- HOOKS_REFERENCE.md (added shaped_hook_name)
- CUSTOMIZATION_GUIDE.md (added example)

Related: CLAUDE.md#IMPL-###
```

### Bug Fix

```
fix: [description] (IMPL-###)

Issue: [what was wrong]
Fix: [what was changed]
Files: path/to/file.php

See: docs/CLAUDE.md#IMPL-###
```

### Audit/Sync

```
docs: audit and sync with CLAUDE.md

Synchronized: [X] items
Added to CLAUDE.md: [list]
Removed from CLAUDE.md: [list]
Updated docs: [list]
```

### Initial Documentation

```
docs: initial shaped-core documentation system

Added:
- ARCHITECTURE_GUIDE.md
- HOOKS_REFERENCE.md
- CORE_MODULES.md
- ROOMCLOUD_INTEGRATION.md
- SHORTCODES_GUIDE.md
- CUSTOMIZATION_GUIDE.md
- DEBUGGING.md
- CLAUDE.md (change log)
- MAINTENANCE.md (workflow)
- README.md (index)

System ready for: implementation → auto-doc-update workflow
```

---

## Quick Reference

### Implementation Request Template

```
IMPLEMENTATION REQUEST:
Feature: Add custom commission hook to pricing module
Category: Pricing
Description: Allow per-property commission overrides without editing core
Example usage:
  add_filter('shaped_commission_calculation', fn($c, $b) =>
    $b->property_id === 123 ? $c * 0.85 : $c, 10, 2);
```

### CLAUDE.md Entry Checklist

When adding an entry, include:

- [ ] Date (YYYY-MM-DD)
- [ ] Unique IMPL-### ID (increment from last)
- [ ] Status (Complete/In Progress/Blocked)
- [ ] Category
- [ ] Files Changed (exact paths)
- [ ] What was added (hooks, methods, classes)
- [ ] Where in docs (links to sections)
- [ ] Example usage (copy-paste ready)
- [ ] Related to (previous implementations)
- [ ] Blocked by (if applicable)

### Doc Update Rules

| Change Type | Update These Docs |
|-------------|-------------------|
| New filter hook | HOOKS_REFERENCE.md, CUSTOMIZATION_GUIDE.md |
| New action hook | HOOKS_REFERENCE.md |
| New method | CORE_MODULES.md |
| New class | CORE_MODULES.md, ARCHITECTURE_GUIDE.md |
| New shortcode | SHORTCODES_GUIDE.md |
| RoomCloud feature | ROOMCLOUD_INTEGRATION.md |
| Bug fix | DEBUGGING.md |
| Config change | ARCHITECTURE_GUIDE.md, CUSTOMIZATION_GUIDE.md |

### Files in This System

```
docs/
├── README.md              # Index and reading order
├── CLAUDE.md              # Implementation log (source of truth)
├── MAINTENANCE.md         # This file (workflow)
├── ARCHITECTURE_GUIDE.md  # Plugin structure
├── HOOKS_REFERENCE.md     # All hooks
├── CORE_MODULES.md        # Core classes
├── ROOMCLOUD_INTEGRATION.md # RoomCloud module
├── SHORTCODES_GUIDE.md    # All shortcodes
├── CUSTOMIZATION_GUIDE.md # Extension examples
├── DEBUGGING.md           # Troubleshooting
└── CLIENTS/               # Client-specific docs
    └── [CLIENT_NAME].md
```

---

## Client Documentation

When setting up a new property, create `docs/CLIENTS/[PROPERTY_NAME].md`:

```markdown
# Client: [Property Name]

**Setup Date:** YYYY-MM-DD
**Property ID:** [ID]
**Based on:** IMPL-001, IMPL-###, ...
**Contact:** [Owner name/email]

## Configuration

- Modules enabled: [list]
- Modules disabled: [list]
- Payment mode: scheduled | deposit
- Deposit percentage: [X]%
- Custom pricing: [description]

## Custom Hooks Implemented

- `shaped_hook_name` — [what it does]
- `shaped_another_hook` — [what it does]

## Implementation Entries Used

- IMPL-001: Initial structure
- IMPL-###: [description]

## Notes

- [Setup notes]
- [Special considerations]
- [Known issues]
```

---

## Troubleshooting the System

### CLAUDE.md Out of Sync

If CLAUDE.md doesn't match code:
1. Run the audit prompt
2. Claude Code will identify discrepancies
3. Review and approve fixes
4. Commit with audit message

### Missing Documentation

If docs are missing for a feature:
1. Find the code in shaped-core
2. Create CLAUDE.md entry for it
3. Run doc update for that entry
4. Commit both

### Stale Documentation

If docs reference deleted code:
1. Run the audit prompt
2. Claude Code will identify stale refs
3. Remove or update as needed
4. Commit with audit message
