# SIRIKA Scan Camera Selection Design

**Date:** 2026-07-12  
**Status:** Approved for planning

## Objective

Enable the QR scanner to use either mobile camera while requesting the rear
camera by default. The manual token flow and the server-side scan validation
contract remain unchanged.

## Scope

- Request the rear-facing camera first when the user starts the scanner.
- Fall back to an available camera when the device has no rear camera or the
  browser cannot satisfy the rear-camera constraint.
- Provide one control that switches between the available front and rear
  cameras while scanning.
- Stop the active stream before starting the replacement stream.
- Preserve clear Indonesian error messages for unavailable, denied, and failed
  camera access.

## Out of Scope

- No change to QR token generation, scan result validation, roles, or scan-log
  storage.
- No persistence of the selected camera across devices or sessions.
- No redesign of the scan result or manual-token form.

## Design

The existing Alpine component (`window.sirikaScan`) owns camera state. It will
store a normalized list of available cameras, the active camera ID, and a
preferred camera direction. On start, it will select a camera labelled as rear
or environment-facing when one is available; otherwise it selects the first
available camera.

The scanner will use the selected camera ID with `Html5Qrcode.start`. This is
more deterministic than relying only on `facingMode`, while still allowing the
application to identify the rear camera from enumerated device metadata. The
component will expose `switchCamera`, which stops the current stream, selects
the next available front/rear candidate, and starts it. If a matching opposite
camera cannot be found, it shows a non-destructive message and leaves scanning
stopped rather than retaining an unknown stream state.

The scan page will display the current preference and one camera-switch button.
The button is disabled while a request is in progress or when fewer than two
usable cameras exist. Existing Start and Stop controls remain.

## Error Handling

- No camera found: display `Kamera tidak ditemukan.`
- Permission denied: display a specific instruction to allow camera access in
  the browser.
- Rear camera unavailable: fall back automatically and identify the selected
  camera in the UI.
- Any stream startup/switch failure: reset `cameraRunning` and display a safe
  error without submitting a scan.

## Testing

- Add a server-rendered scan-page regression test for the switch control and
  explanatory default-camera text.
- Add focused JavaScript tests, or extract a small pure camera-selection helper
  if needed, to prove rear preference, fallback, and alternating camera choice.
- Run the focused test(s), then the complete PHPUnit suite and production asset
  build.
- Manually verify on a physical phone over HTTPS because browser camera access
  requires a secure context outside localhost.

## Acceptance Criteria

1. On a phone with front and rear cameras, starting the scanner opens the rear
   camera by default.
2. The user can switch to the front camera and switch back without a page
   reload.
3. A device without a rear camera still scans using an available camera.
4. Stopping, switching, and failed startup do not leave an active camera stream
   or duplicate scanner instance.
5. Manual token validation continues to work exactly as before.
