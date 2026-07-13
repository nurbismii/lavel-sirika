# SIRIKA Route Segment Management Design

**Date:** 2026-07-13  
**Status:** Approved for specification review

## Objective

Allow Super Admin and Admin HR to add missing master route segments without
breaking historical permits or requiring a complete Leaflet polyline at the
time of creation.

## Lifecycle

- **Draft:** created with route metadata; a map polyline is optional; draft
  segments are excluded from import and permit-review route validation.
- **Active:** requires a complete Leaflet polyline; active segments are
  eligible for import and permit-review route validation.
- **Inactive:** replaces permanent deletion. Existing permit relations remain
  readable, while inactive segments are excluded from new validation.

## Authorization

Only `super_admin` and `admin_hr` may create, edit metadata, activate, or
inactivate route segments. Auditor retains read-only access; Security retains
no master-route management access.

## UI and Data Flow

The Master Rute index gets a “Tambah Segmen” action and a create/edit form
with required unique code, name, start location, and end location. A new
record is stored as draft. The existing Leaflet map editor remains the only
place to draw or reset the polyline. After a complete polyline is saved, an
authorized user may activate the segment. Inactivation is a confirmation-backed
state update, never a database delete.

## Validation and Safety

- Normalize and validate the segment code using the existing route-code rules;
  reject duplicates case-insensitively.
- Do not permit activation without a complete polyline.
- Do not allow inactive or draft segments to enter new route validation.
- Do not remove rows referenced by historic permits.
- Use Form Requests for all write operations and retain existing CSRF and role
  middleware patterns.

## Testing

- HTTP tests for authorized create, update, activate, inactivate, and role
  denial.
- Regression tests for duplicate code, incomplete-polyline activation, and
  active-only route lookup.
- Render tests for the Master Rute actions and state labels.
- Full PHPUnit suite plus production asset build before merge.

## Acceptance Criteria

1. Admin HR and Super Admin can create a draft segment with metadata only.
2. A draft cannot be activated until its Leaflet polyline is complete.
3. Active segments are accepted by new import/review validation.
4. Inactive segments remain visible in historical permit data but cannot be
   selected for new validation.
5. Auditor and Security cannot modify master route segments.
