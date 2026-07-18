# Permit Action Button Group Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Group the Permit list bulk-action buttons so they display with a compact gap.

**Architecture:** The permit index wraps its existing authorized forms in a presentational container. Scoped CSS lays out that container with flex and an 8px gap; routes, permissions, and form behavior do not change.

**Tech Stack:** Laravel 8 Blade, CSS, PHPUnit 9.

## Global Constraints

- Preserve existing action labels, methods, routes, authorization checks, and confirmation copy.
- Do not change layout behavior in headers outside the Permit list.
- Keep mobile controls usable with existing responsive button styles.

---

### Task 1: Group and style permit bulk actions

**Files:**
- Modify: `resources/views/permits/index.blade.php:16-29`
- Modify: `resources/css/app.css:652-658`
- Modify: `tests/Feature/PermitLifecycleHttpTest.php:211-230`

**Interfaces:**
- Consumes: existing `permits.qr.bulk-generate` and `permits.clear-all` forms.
- Produces: one `.permit-actions` wrapper around both forms.

- [ ] **Step 1: Write the failing markup assertion**

```php
$response->assertSee('<div class="permit-actions">', false);
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `php artisan test --filter=PermitLifecycleHttpTest`

Expected: FAIL because the `.permit-actions` wrapper does not exist.

- [ ] **Step 3: Add the minimal Blade wrapper**

```blade
<div class="permit-actions">
    {{-- existing Bulk Generate QR and Kosongkan Semua Izin forms, unchanged --}}
</div>
```

- [ ] **Step 4: Add scoped CSS**

```css
.permit-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
```

- [ ] **Step 5: Run focused test to verify it passes**

Run: `php artisan test --filter=PermitLifecycleHttpTest`

Expected: PASS.

- [ ] **Step 6: Run full verification and commit**

Run: `$env:CORS_ALLOWED_ORIGINS='https://sirika.vdnisite.com'; php artisan test`

Run: `git add resources/views/permits/index.blade.php resources/css/app.css tests/Feature/PermitLifecycleHttpTest.php`

Run: `git commit -m "fix: group permit bulk actions"`

Expected: all tests pass and the commit contains only the Blade, CSS, and test changes.
