# QR Card Containment Design

## Goal

Keep the generated QR code fully inside the printable vehicle-permit card without reducing its scannability.

## Scope

- Retain the existing permit-card markup and generated SVG QR content.
- Update only QR presentation styles in `resources/css/app.css`.
- Give the QR column an internal white card surface with a solid border and padding.
- Constrain the SVG to the QR container with `max-width: 100%` and proportional height.
- Preserve centered alignment and the existing single-column mobile layout.

## Non-goals

- No changes to QR token generation, QR payloads, renewal, authorization, or print actions.
- No resizing of the primary permit card or changes to permit data.

## Behavior

The QR container remains the card's right-hand column on desktop and moves below permit data on small screens. Its padding and overflow boundary ensure the QR's rendered SVG cannot exceed the visual card area, while responsive sizing keeps it readable.

## Validation

- Add a focused feature test asserting the printable permit-card page retains the QR card wrapper.
- Run the focused feature test and production frontend build.
