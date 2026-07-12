# Scan Camera Selection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the mobile QR scanner prefer the rear camera while allowing the operator to switch safely to the front camera.

**Architecture:** Extract camera-direction rules into a small ES module testable by Node. The existing Alpine scanner consumes the helper to start, stop, and switch the existing `Html5Qrcode` instance; Blade exposes the state with an accessible control.

**Tech Stack:** Laravel 8, Blade, Alpine.js 3, html5-qrcode 2.3, Laravel Mix/Webpack 5, Node 22 test runner, PHPUnit 9.

## Global Constraints

- Request `environment` (rear) before falling back to an available camera.
- Preserve the existing scan verification payload, roles, scan logs, and manual-token flow.
- Stop the active stream before replacement; never create another scanner instance.
- Do not add dependencies or persist the selected camera.
- Verify physical devices over HTTPS outside localhost.

---

### Task 1: Camera direction helper

**Files:**
- Create: `resources/js/scan-camera.mjs`
- Create: `tests/js/scan-camera.test.mjs`
- Modify: `package.json`

**Interfaces:**
- Produces: `cameraConstraints(direction)`, `cameraDirectionLabel(direction)`, and `oppositeCameraDirection(direction)`.
- Consumes: `'environment'` and `'user'`.
- Used by: `window.sirikaScan` in `resources/js/app.js`.

- [ ] **Step 1: Write the failing test**

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    cameraConstraints,
    cameraDirectionLabel,
    oppositeCameraDirection,
} from '../../resources/js/scan-camera.mjs';

test('uses rear camera as the default request', () => {
    assert.deepEqual(cameraConstraints('environment'), {
        facingMode: { exact: 'environment' },
    });
    assert.equal(cameraDirectionLabel('environment'), 'Kamera belakang');
    assert.equal(oppositeCameraDirection('environment'), 'user');
});

test('uses front camera and identifies rear as its opposite', () => {
    assert.deepEqual(cameraConstraints('user'), {
        facingMode: { exact: 'user' },
    });
    assert.equal(cameraDirectionLabel('user'), 'Kamera depan');
    assert.equal(oppositeCameraDirection('user'), 'environment');
});
```

- [ ] **Step 2: Run RED**

Run: `node --test tests/js/scan-camera.test.mjs`  
Expected: FAIL because `resources/js/scan-camera.mjs` does not exist.

- [ ] **Step 3: Implement the helper**

```js
const labels = {
    environment: 'Kamera belakang',
    user: 'Kamera depan',
};

export function cameraConstraints(direction) {
    return { facingMode: { exact: direction } };
}

export function cameraDirectionLabel(direction) {
    return labels[direction] || labels.environment;
}

export function oppositeCameraDirection(direction) {
    return direction === 'user' ? 'environment' : 'user';
}

```

Add `"test:js": "node --test tests/js/*.test.mjs"` to `package.json`.

- [ ] **Step 4: Run GREEN**

Run: `npm run test:js`  
Expected: PASS with 2 tests and 0 failures.

- [ ] **Step 5: Commit**

```bash
git add package.json resources/js/scan-camera.mjs tests/js/scan-camera.test.mjs
git commit -m "feat: add scan camera direction helpers"
```

### Task 2: Rear-default start and camera switch

**Files:**
- Modify: `resources/js/app.js`
- Test: `tests/js/scan-camera.test.mjs`

**Interfaces:**
- Consumes: the Task 1 helpers and `Html5Qrcode`.
- Produces: Alpine state `cameraDirection`, `cameraDirectionLabel`, `cameraAvailable`, and `switchCamera()`.
- Preserves: `stopCamera`, token submission, and validation response handling.

- [ ] **Step 1: Write the failing fallback helper test**

```js
test('returns null when no fallback camera exists', () => {
    assert.equal(fallbackCameraId([]), null);
});

test('uses the first available camera as fallback', () => {
    assert.equal(fallbackCameraId([{ id: 'fallback-camera' }]), 'fallback-camera');
});
```

- [ ] **Step 2: Run RED**

Run: `npm run test:js`  
Expected: FAIL because `fallbackCameraId` is not exported yet.

- [ ] **Step 3: Implement scanner lifecycle**

First add the missing Task 2 helper to `resources/js/scan-camera.mjs`:

```js
export function fallbackCameraId(cameras) {
    return cameras.length ? cameras[0].id : null;
}
```

Then import the Task 1 and Task 2 helpers. Add initial Alpine state:

```js
cameraDirection: 'environment',
cameraDirectionLabel: 'Kamera belakang',
cameraAvailable: false,
```

Implement `startCamera(direction = this.cameraDirection)` as follows:

```js
try {
    await this.qrReader.start(cameraConstraints(direction), config, onSuccess, onFailure);
    this.cameraDirection = direction;
} catch (error) {
    const cameraId = fallbackCameraId(await Html5Qrcode.getCameras());
    if (!cameraId) throw error;
    await this.qrReader.start(cameraId, config, onSuccess, onFailure);
}
this.cameraDirectionLabel = cameraDirectionLabel(this.cameraDirection);
this.cameraRunning = true;
this.cameraAvailable = true;
```

Implement `switchCamera()` as:

```js
async switchCamera() {
    if (!this.cameraRunning || this.loading) return;
    await this.stopCamera();
    await this.startCamera(oppositeCameraDirection(this.cameraDirection));
}
```

The catch path must set `cameraRunning = false`, set a safe error result, and retain manual-token behavior.

- [ ] **Step 4: Run GREEN and build**

Run: `npm run test:js; npm run production`  
Expected: all JS tests PASS and asset build exits 0.

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.js resources/js/scan-camera.mjs tests/js/scan-camera.test.mjs public/js/app.js
git commit -m "feat: default scanner to rear camera"
```

### Task 3: Scan-page camera control

**Files:**
- Modify: `resources/views/scan/index.blade.php`
- Modify: `tests/Feature/ScanQrHttpTest.php`

**Interfaces:**
- Consumes: `cameraDirectionLabel`, `cameraAvailable`, `cameraRunning`, `loading`, and `switchCamera()`.
- Produces: visible default-camera copy, active-camera label, and switch button.
- Preserves: result markup and manual-token form.

- [ ] **Step 1: Write the failing Blade test**

```php
public function scan_page_exposes_rear_camera_default_and_switch_control()
{
    $html = $this->actingAs($this->security())
        ->get(route('scan.index'))
        ->assertOk()
        ->getContent();

    $this->assertStringContainsString('Kamera belakang digunakan sebagai default.', $html);
    $this->assertStringContainsString('x-on:click="switchCamera"', $html);
    $this->assertStringContainsString('x-text="cameraDirectionLabel"', $html);
}
```

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/ScanQrHttpTest.php --filter=scan_page_exposes_rear_camera_default_and_switch_control`  
Expected: FAIL because the page has no default-camera copy or switch control.

- [ ] **Step 3: Implement the UI**

Below the scanner subtitle add `Kamera belakang digunakan sebagai default.`. Add an Alpine status line with `x-text="cameraDirectionLabel"`, plus a `Ganti Kamera` button with `x-on:click="switchCamera"` and `x-bind:disabled="!cameraAvailable || loading"`. Leave Start and Stop button bindings unchanged.

- [ ] **Step 4: Run GREEN**

Run: `php artisan test tests/Feature/ScanQrHttpTest.php --filter=scan_page_exposes_rear_camera_default_and_switch_control`  
Expected: PASS with 1 test and 0 failures.

- [ ] **Step 5: Commit**

```bash
git add resources/views/scan/index.blade.php tests/Feature/ScanQrHttpTest.php
git commit -m "feat: add scanner camera switch control"
```

### Task 4: Full verification and device handoff

**Files:**
- Modify only files required to fix a regression discovered below.

- [ ] **Step 1: Run automated verification**

Run: `npm run test:js; php artisan test; npm run production; git diff --check`  
Expected: all tests and build pass; no whitespace errors.

- [ ] **Step 2: Verify on a physical phone through HTTPS**

1. Sign in as Security and open `/scan` over HTTPS.
2. Tap `Mulai Kamera`; the rear camera opens by default.
3. Tap `Ganti Kamera`; the front camera opens.
4. Tap `Ganti Kamera` again; the rear camera opens.
5. Deny permission once; confirm a clear message and manual token fallback.
6. Test a one-camera device if available; confirm it scans without a broken stream.

- [ ] **Step 3: Handle a regression only at its owning task**

If a verification command fails, return to the relevant Task 1, 2, or 3 test,
write the failing regression assertion first, apply the smallest fix, rerun that
task's focused command, and commit only that task's named files. Do not create
an empty verification commit.
