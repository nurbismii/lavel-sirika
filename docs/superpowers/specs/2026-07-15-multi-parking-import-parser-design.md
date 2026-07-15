# Multi-location parking and import-route parser

## Goal

Allow one vehicle permit to have more than one parking location and import the
final SIRIKA spreadsheet safely. A route continues to use `→` between road
segments, while parking codes retain `-` whether they appear at the beginning,
end, or between route details.

## Data model

Create `vehicle_permit_parking_locations` with unique
`vehicle_permit_id + parking_location_id`. The migration copies every existing
non-null `vehicle_permits.parking_location_id` into this table. The existing
foreign key remains during this change as a compatibility field and is kept in
sync with the first selected location.

`VehiclePermit` gains `parkingLocations()` and presentation code uses it. The
existing `parkingLocation()` relation remains for backward compatibility.

## Import format

The `lokasi parkir` cell may contain one or more codes separated by commas,
line breaks, or `/`. Whitespace is trimmed, empty entries are ignored, and
duplicates are removed. Codes longer than the existing master-data limit place
the row in `needs_review` without creating an unsafe master record.

Examples:

- `GA-MES1-P01, GA-MES3-P02`
- `CY-CC-P02 / CY-CC-P03`
- one code per line

## Route parsing

Route road segments are tokenized independently from parking codes. `→` is the
canonical separator; ` - ` (with spaces) is accepted as a legacy separator.
Parking codes supplied by the parking-location column are removed from the
route before road-segment validation, regardless of their position. The parser
also recognizes remaining hierarchical parking-code tokens such as
`PLTU-PC-6-P10` and `HSE-KLINIK-P01`, so they do not become false unknown-road
warnings.

Only road tokens are validated against active road segments. Unknown road
tokens, free text, malformed parking codes, and an empty road-segment result
continue to require manual review.

## Read and review behavior

Review accepts multiple active parking-location IDs. Activation requires at
least one location. Permit list, detail, QR, route map, scan response, reports,
and exports show the parking codes as a comma-separated list. Parking filters
match permits containing the selected location.

## Safety and tests

The migration is additive and copies legacy data without deletion. Import and
review writes run in their existing transactions. Tests cover migration
backfill, multi-value import, start/end parking codes in a route, mixed
hierarchical codes, duplicate suppression, review activation, filters, QR, and
export display.
