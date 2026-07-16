# Camera Startup Resilience Design

## Goal

Keep QR scanning available when the preferred rear camera cannot start on a mobile browser.

## Root Cause

The scanner currently retries once after the `environment` constraint fails. It selects a rear-labelled camera ID, but if that ID fails to start, the outer catch stops immediately and replaces the browser error with a generic message. No other available camera is attempted.

## Scope

- Retain rear-camera preference for the initial scanner attempt.
- Build an ordered, duplicate-free list of fallback camera IDs: rear-labelled devices first, then every other listed device.
- Attempt each fallback ID until one starts.
- Preserve the selected-camera label when a fallback starts.
- Map browser camera errors to actionable Indonesian messages: permission denied, camera unavailable/in use, insecure context, no camera, and generic startup failure.
- Add focused JavaScript tests for fallback ordering and error-message mapping.

## Non-goals

- No changes to QR decoding, token validation, scan logging, routes, authorization, or QR card UI.
- No new browser dependency and no persisted camera preference.

## Error Handling

If all candidate cameras fail, the scanner remains stopped and displays a specific message. The underlying error remains logged to the browser console for support diagnosis.

## Validation

- Tests must first fail for the new fallback ordering and error mapping behavior.
- JavaScript tests and production build must pass.
- The scanner must be manually tested on the affected HTTPS phone: start scan, grant permission, and verify a usable camera opens.
