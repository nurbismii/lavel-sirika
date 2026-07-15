# Shared vehicle permits

## Goal

Allow multiple employees with different NIKs to receive independent permits for
one physical vehicle and one plate number.

## Data rule

`vehicles.plate_number` remains globally unique: one plate represents one
vehicle record. `vehicle_permits` remains unique on `employee_id + vehicle_id`:
the same employee cannot receive the same vehicle permit twice.

Different employees may each have one permit for the same vehicle and may have
active QR tokens simultaneously. A permit remains the identity used by QR and
scan responses, so scans still identify the employee who holds that QR.

## Import and activation

Import resolves a vehicle by plate under a lock. If it already exists, it is
reused regardless of its original `employee_id`; no different-NIK rejection is
produced. It creates a new permit only when that NIK has not already received a
permit for the vehicle.

The active-permit safeguard changes from vehicle-wide to employee-plus-vehicle
scope. A different employee's active permit never blocks activation. Existing
active-permit behavior for the same employee/vehicle remains protected by the
unique permit constraint.

## Compatibility and tests

No table or existing data is deleted. The current `vehicles.employee_id` stays
as the original/importing owner for compatibility, but is not authorization or
exclusivity evidence. Tests cover preview and commit for two NIKs sharing one
plate, concurrent/stale import behavior, two active permits, and QR identity.
