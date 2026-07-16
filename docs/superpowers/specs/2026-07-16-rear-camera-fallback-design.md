# Rear Camera Fallback Design

## Goal

Ensure the QR scanner prefers a mobile device's rear camera when the browser cannot satisfy the existing `facingMode: environment` constraint.

## Scope

- Keep `environment` as the initial requested camera direction.
- When that request fails, inspect the cameras returned by `Html5Qrcode.getCameras()`.
- Prefer a camera whose label indicates a rear camera (`back`, `rear`, or `environment`), case-insensitively.
- Retain the current first-camera fallback when no rear-camera label is available, so scanning still works on devices that do not expose useful labels.
- Add a focused JavaScript regression test for rear-camera selection.

## Non-goals

- No changes to server-side scan verification, authorization, or scan logging.
- No persistence of a user camera choice.
- No changes to the existing manual token fallback.

## Data Flow and Error Handling

1. The scanner requests `facingMode: environment` as it does today.
2. If the browser rejects that constraint, the scanner retrieves available cameras.
3. The fallback helper selects a rear-labeled camera when present; otherwise it returns the first available camera ID.
4. If no cameras are available, the existing camera error message is shown.

## Validation

- A new test will demonstrate that the old helper incorrectly selects the first (front) camera.
- The test will pass after the helper prioritizes the rear-labeled camera.
- The frontend production build and relevant PHP tests will run after the change.
