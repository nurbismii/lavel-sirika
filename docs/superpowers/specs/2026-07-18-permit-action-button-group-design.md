# Permit Action Button Group Design

## Purpose

Keep the Bulk Generate QR and Clear All Permits actions visually adjacent on the Permit list.

## Problem

The Permit list header uses `justify-content: space-between` and currently has the title block plus two action forms as direct children. Remaining horizontal space is therefore distributed between both forms, creating an unnecessarily large gap.

## Design

Wrap the two authorized action forms in one `permit-actions` container. The container uses flex layout with an 8px gap and wrapping enabled. The existing header continues to position the title block on the left and the complete action group on the right.

On small screens, the existing header grid layout is retained. The action group wraps safely, and the existing full-width button rule continues to provide readable mobile controls.

## Scope

- Modify only the Permit index Blade structure and the shared CSS action-group rule.
- Preserve authorization, routes, labels, form methods, confirmation message, and button styles.
- Add a focused feature assertion for the action-group markup.

## Non-Goals

- No behavior, route, permission, or data lifecycle changes.
- No global change to the layout of headers in other modules.
