# Bulk Clear Vehicle Permits Design

## Purpose

Provide an Admin HR action on the Permit list to empty every vehicle permit while preserving the business rules and data-retention behavior of the existing individual deletion flow.

## Scope

The action will be available only to roles authorized for permit deletion: Admin HR and Super Admin. It will be shown on the Permit list next to the existing bulk QR action.

Before submitting, the user receives an explicit destructive-action confirmation explaining that active permits are revoked first, permits are then permanently deleted, and scan history is retained without a permit reference.

## Behavior

The controller delegates the operation to `PermitLifecycleService`; it does not truncate database tables.

The service processes the current permits using the same lifecycle rules as an individual deletion:

1. An `active` permit is revoked first, including revocation of its active QR token.
2. Once not active, the permit is permanently deleted.
3. Scan logs associated with the permit are retained with `permit_id` set to `null`.
4. Existing foreign-key and model deletion behavior continues to clean permit-owned QR, route, and parking-pivot records.

The action reports the number of permits cleared. If an error occurs, the transaction is rolled back so the operation does not leave a partially cleared permit list.

## Architecture

- Add a named `POST` route, protected by a new `permits.clear-all` authorization mapping.
- Add a controller action that invokes a dedicated bulk method on `PermitLifecycleService` and redirects back to the list with success or error feedback.
- Extend `PermitLifecycleService` with the bulk orchestration method. Existing `revoke()` and `destroy()` remain the canonical individual operations.
- Add the authorized form/button and confirmation copy to the permit index view.

## Validation and Tests

Feature tests will verify that:

- unauthorized users cannot invoke the route or see the action;
- active permits are revoked before deletion;
- inactive permits are deleted;
- scan logs remain and have a null `permit_id`;
- a successful request returns to the list with the cleared-count message.

## Non-Goals

- No table truncation or auto-increment reset.
- No deletion of scan-history records.
- No change to individual permit deletion, review, import, or QR generation flows.
