# Task 3 Report: Implement Road Segment Coordinate Editor HTTP Flow

## What I implemented

- Added `UpdateRoadSegmentPolylineRequest` to validate:
  - `save_mode` in `draft|complete`
  - `points_json` as JSON
  - decoded `points` array with max 200 entries
  - per-point `x`/`y` bounds against `RouteMapConfig`
  - minimum 1 point for any save, minimum 2 points for `complete`
- Extended `RoadSegmentController` with:
  - upgraded `index()` to expose summary/map preview compatibility data from existing services
  - `map()` editor page action
  - `updateMap()` save action using `RoadSegmentPolylineService::buildPayload()`
  - `resetMap()` clear-all action via `DELETE /road-segments/{roadSegment}/map`
- Added role mappings in `User::routeRoles()` for:
  - `road-segments.map`
  - `road-segments.map.update`
  - `road-segments.map.reset`
- Added routes:
  - `road-segments.map`
  - `road-segments.map.update`
  - `road-segments.map.reset`
- Added `resources/views/road-segments/map.blade.php` editor view with:
  - map canvas
  - segment metadata
  - Undo, Save Draft, Save Complete
  - Reset Koordinat via `DELETE` form
  - Back link
- Intentionally did **not** add any client-side clear-all button/action to respect the Task 2 reset-only constraint.
- Added new HTTP feature coverage in `RoadSegmentMapHttpTest`.
- Expanded `SirikaModuleAccessTest` to cover map editor access by role.

## TDD evidence

### RED

Command:

```powershell
php artisan test --filter=RoadSegmentMapHttpTest
```

Output:

```text
FAIL  Tests\Feature\RoadSegmentMapHttpTest
⨯ admin hr can open road segment map editor
⨯ auditor can open but cannot save or reset road segment map
⨯ security cannot open road segment map editor
⨯ admin hr can save complete polyline
⨯ complete polyline requires two points
⨯ out of bounds points are rejected
⨯ admin hr can reset polyline

Route [road-segments.map] not defined.
Route [road-segments.map.update] not defined.
Route [road-segments.map.reset] not defined.

Tests: 7 failed
```

### GREEN

Command:

```powershell
php artisan test --filter=RoadSegmentMapHttpTest
```

Output:

```text
PASS  Tests\Feature\RoadSegmentMapHttpTest
✓ admin hr can open road segment map editor
✓ auditor can open but cannot save or reset road segment map
✓ security cannot open road segment map editor
✓ admin hr can save complete polyline
✓ complete polyline requires two points
✓ out of bounds points are rejected
✓ admin hr can reset polyline

Tests: 7 passed
```

## Tests run and results

```powershell
php artisan test --filter=RoadSegmentMapHttpTest
```

- Result: `7 passed`

```powershell
php artisan test --filter=SirikaModuleAccessTest
```

- Result: `3 passed`

```powershell
php artisan test --filter=RoadSegmentPolylineServiceTest
```

- Result: `8 passed`

```powershell
php artisan test --filter=DashboardUiTest
```

- Result: `2 passed`

```powershell
php artisan test --filter=RouteMapConfigTest
```

- Result: `1 passed`

## Files changed

- `app/Http/Requests/UpdateRoadSegmentPolylineRequest.php`
- `app/Http/Controllers/RoadSegmentController.php`
- `app/Models/User.php`
- `routes/web.php`
- `resources/views/road-segments/map.blade.php`
- `tests/Feature/RoadSegmentMapHttpTest.php`
- `tests/Feature/SirikaModuleAccessTest.php`

## Self-review findings

- Request validation now catches bounds errors on `points.*.x` / `points.*.y` before service persistence.
- Save/reset authorization stays at route middleware level, so auditors can open the editor read-only but cannot mutate data.
- Reset remains server-side only through `DELETE /road-segments/{roadSegment}/map`; no client-side clear-all action was reintroduced.
- `SirikaModuleAccessTest` was adjusted to use the first seeded `RoadSegment` instead of hardcoded ID `1`, which is less brittle.
- `RoadSegmentController::index()` was kept compatible with the existing route map service/config objects already present in the worktree.

## Concerns

- No blocking concerns found.

## Review follow-up notes

- Added HTTP regression coverage in `tests/Feature/RoadSegmentMapHttpTest.php` for:
  - draft save with one valid point
  - rejection of payloads with more than 200 points
  - `super_admin` save/reset behavior through the editor routes
- TDD follow-up RED used a narrow filter for the new tests:

```powershell
php artisan test --filter="admin_hr_can_save_draft_polyline_with_one_valid_point|payload_with_more_than_two_hundred_points_is_rejected|super_admin_can_save_and_reset_polyline"
```

- First RED result:
  - `payload_with_more_than_two_hundred_points_is_rejected` already passed immediately
  - `super_admin_can_save_and_reset_polyline` already passed immediately
  - `admin_hr_can_save_draft_polyline_with_one_valid_point` failed because the new assertion was too strict on numeric type (`10.0` vs `10`), not because of an application bug
- After tightening the test to the actual persisted payload shape, the narrow filter passed.
- No production code change was needed in `UpdateRoadSegmentPolylineRequest.php`; the existing `points` max `200` rule already enforced the limit correctly.
