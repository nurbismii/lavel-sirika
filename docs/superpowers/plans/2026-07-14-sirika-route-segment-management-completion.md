# Route Segment Management Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (\`- [ ]\`) syntax for tracking.

**Goal:** Complete master route-segment management so authorized users can edit metadata, activate complete drafts, and inactivate segments safely.

**Architecture:** Keep the existing \`RoadSegment\` table and Leaflet editor. Add a dedicated update Form Request and explicit edit/update routes; lifecycle transitions remain separate controller actions and never delete a segment, preserving permit history. A shared Blade partial keeps create and edit validation feedback consistent.

**Tech Stack:** Laravel 8, PHP 7.4, Eloquent, Form Requests, Blade, PHPUnit.

## Global Constraints

- Only \`super_admin\` and \`admin_hr\` may create, edit metadata, activate, or inactivate route segments.
- New segments begin as \`draft\`; activation needs a complete Leaflet polyline.
- Inactivation replaces deletion and preserves permit history.
- Route codes are normalized uppercase, max 16 characters, letters/digits/hyphens only, and unique case-insensitively.
- Draft and inactive segments remain excluded from new import and permit-review validation by existing active-status queries.

---

### Task 1: Add metadata edit endpoints, validation, and form UX

**Files:**
- Create: \`app/Http/Requests/UpdateRoadSegmentRequest.php\`
- Create: \`resources/views/road-segments/_form.blade.php\`
- Create: \`resources/views/road-segments/edit.blade.php\`
- Modify: \`app/Http/Controllers/RoadSegmentController.php\`
- Modify: \`app/Models/User.php\`
- Modify: \`routes/web.php\`
- Modify: \`resources/views/road-segments/create.blade.php\`
- Modify: \`resources/views/road-segments/index.blade.php\`
- Test: \`tests/Feature/RoadSegmentMapHttpTest.php\`

**Interfaces:**
- Consumes: \`StoreRoadSegmentRequest\` field rules and \`RoadSegment\` route-model binding.
- Produces: GET \`road-segments/{roadSegment}/edit\` named \`road-segments.edit\`; PUT \`road-segments/{roadSegment}\` named \`road-segments.update\`.

- [ ] **Step 1: Write the failing HTTP tests**

\`\`\`php
public function admin_hr_can_update_route_segment_metadata()
{
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
    $segment = $this->segment(['code' => 'OLD-1']);

    $this->actingAs($admin)->put(route('road-segments.update', $segment), [
        'code' => ' new-1 ', 'name' => 'Jalur Baru',
        'start_location' => 'Pos A', 'end_location' => 'Pos B',
    ])->assertRedirect(route('road-segments.index'));

    $this->assertDatabaseHas('road_segments', [
        'id' => $segment->id, 'code' => 'NEW-1', 'name' => 'Jalur Baru',
    ]);
}

public function route_segment_update_rejects_a_code_used_by_another_segment()
{
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
    $segment = $this->segment(['code' => 'OLD-1']);
    $this->segment(['code' => 'USED-1']);

    $this->actingAs($admin)->from(route('road-segments.edit', $segment))
        ->put(route('road-segments.update', $segment), [
            'code' => 'used-1', 'name' => 'Jalur', 'start_location' => 'A', 'end_location' => 'B',
        ])->assertRedirect(route('road-segments.edit', $segment))
        ->assertSessionHasErrors('code');
}
\`\`\`

- [ ] **Step 2: Run the focused test to verify it fails**

Run: \`php artisan test --filter=RoadSegmentMapHttpTest\`

Expected: FAIL with \`Route [road-segments.update] not defined\`.

- [ ] **Step 3: Write the minimal implementation**

Create \`UpdateRoadSegmentRequest\`, matching the store rules but ignoring the bound segment in the unique rule:

\`\`\`php
'code' => [
    'required', 'string', 'max:16', 'regex:/^[A-Za-z0-9-]+$/',
    Rule::unique('road_segments', 'code')->ignore($this->route('roadSegment')),
],
\`\`\`

Normalize \`code\` in \`prepareForValidation()\`. Add controller methods:

\`\`\`php
public function edit(RoadSegment $roadSegment)
{
    return view('road-segments.edit', compact('roadSegment'));
}

public function update(UpdateRoadSegmentRequest $request, RoadSegment $roadSegment)
{
    $roadSegment->update($request->validated());

    return redirect()->route('road-segments.index')
        ->with('status', 'Metadata segmen rute berhasil diperbarui.');
}
\`\`\`

Add \`road-segments.edit\` and \`road-segments.update\` to \`User::routeRoles()\` with Admin HR. Register matching GET/PUT routes protected by those roles. Extract the four inputs into \`_form.blade.php\`, use \`old(..., $roadSegment->...) \`, and display \`@error\` feedback under each field. Render that partial from create and edit templates. Add an \`Edit\` link in the index only when \`$canEditMap\` is true.

- [ ] **Step 4: Run the focused test to verify it passes**

Run: \`php artisan test --filter=RoadSegmentMapHttpTest\`

Expected: PASS with no failures.

- [ ] **Step 5: Commit the task**

\`\`\`powershell
git add app/Http/Requests/UpdateRoadSegmentRequest.php app/Http/Controllers/RoadSegmentController.php app/Models/User.php routes/web.php resources/views/road-segments tests/Feature/RoadSegmentMapHttpTest.php
git commit -m "feat: edit route segment metadata"
\`\`\`

### Task 2: Close lifecycle authorization and UI regression coverage

**Files:**
- Modify: \`tests/Feature/RoadSegmentMapHttpTest.php\`
- Modify: \`resources/views/road-segments/index.blade.php\`

**Interfaces:**
- Consumes: \`road-segments.activate\`, \`road-segments.deactivate\`, and the existing complete polyline payload.
- Produces: regression coverage for successful lifecycle transitions, read-only denials, and conditional actions.

- [ ] **Step 1: Write failing lifecycle tests**

\`\`\`php
public function admin_hr_can_activate_a_segment_with_a_complete_polyline()
{
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
    $segment = $this->segment(['status' => RoadSegment::STATUS_DRAFT, 'polyline_json' => [
        'status' => 'complete',
        'points' => [['x' => 10, 'y' => 20], ['x' => 30, 'y' => 40]],
    ]]);

    $this->actingAs($admin)->post(route('road-segments.activate', $segment))
        ->assertSessionHas('status', 'Segmen rute berhasil diaktifkan.');

    $this->assertSame(RoadSegment::STATUS_ACTIVE, $segment->fresh()->status);
}

public function auditor_and_security_cannot_manage_route_segment_lifecycle()
{
    $segment = $this->segment(['status' => RoadSegment::STATUS_ACTIVE]);

    foreach ([User::ROLE_AUDITOR, User::ROLE_SECURITY] as $role) {
        $user = User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE]);

        $this->actingAs($user)->post(route('road-segments.deactivate', $segment))
            ->assertForbidden();
    }
}
\`\`\`

- [ ] **Step 2: Run focused tests to observe the failure**

Run: \`php artisan test --filter=RoadSegmentMapHttpTest\`

Expected: FAIL for an unimplemented assertion or rendering rule, not a test setup error.

- [ ] **Step 3: Implement only the behavior required by the tests**

Keep activation and deactivation as POST actions. Ensure the index view renders \`Aktifkan\` only for draft records with complete coordinates, \`Nonaktifkan\` only for active records, and neither lifecycle form for read-only users. Both forms require \`@csrf\`; no delete route is introduced.

- [ ] **Step 4: Run the focused tests again**

Run: \`php artisan test --filter=RoadSegmentMapHttpTest\`

Expected: PASS with no failures.

- [ ] **Step 5: Commit the task**

\`\`\`powershell
git add resources/views/road-segments/index.blade.php tests/Feature/RoadSegmentMapHttpTest.php
git commit -m "test: cover route segment lifecycle management"
\`\`\`

### Task 3: Verify the feature branch

**Files:**
- Modify: \`docs/superpowers/plans/2026-07-14-sirika-route-segment-management-completion.md\` (check off only evidence-backed items)

**Interfaces:**
- Consumes: all route-segment routes, requests, views, and service queries.
- Produces: a verified branch ready for review and an explicit merge decision.

- [ ] **Step 1: Run focused and whitespace checks**

\`\`\`powershell
git diff --check
$env:APP_KEY='base64:W4bObKqQ19B0fPYZkuQEgS0F7mo4h1LM9RAQB6ZVbV0='
$env:CORS_ALLOWED_ORIGINS='https://sirika.vdnisite.com'
php artisan test --filter=RoadSegmentMapHttpTest
\`\`\`

Expected: exit code 0 and no whitespace or test failures.

- [ ] **Step 2: Run the full PHP suite**

\`\`\`powershell
$env:APP_KEY='base64:W4bObKqQ19B0fPYZkuQEgS0F7mo4h1LM9RAQB6ZVbV0='
$env:CORS_ALLOWED_ORIGINS='https://sirika.vdnisite.com'
php artisan test
\`\`\`

Expected: exit code 0 with no failures.

- [ ] **Step 3: Build assets if Node dependencies are available**

Run: \`npm run production\`

Expected: exit code 0. Do not commit generated assets unless this repository already tracks them.

- [ ] **Step 4: Commit plan status and present merge choice**

\`\`\`powershell
git add -f docs/superpowers/plans/2026-07-14-sirika-route-segment-management-completion.md
git commit -m "docs: record route segment completion plan"
\`\`\`

Use \`superpowers:finishing-a-development-branch\` before merging. Do not merge without the user's explicit instruction.

