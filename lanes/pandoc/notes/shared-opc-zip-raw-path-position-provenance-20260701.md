# Shared OPC ZIP Raw Path Position Provenance

Bead: plib-h654t
Date: 2026-07-01

## Scope

Raw ZIP central-directory OPC preflights now expose metadata-only path-shape
handoff fields that were already available on constructed ZipPackage OPC
manifests:

- per-entry `pathSegments`, `pathSegmentPositionReviews`, `pathSegmentCount`,
  and `directoryDepth`
- `pathSegmentPositionRoleEntryCounts`
- `entryNamesByPathSegmentPositionRole`
- `pathSegmentPositionHandoffKindEntryCounts`
- `entryNamesByPathSegmentPositionHandoffKind`

The aggregate provenance is shared by constructed-package and raw
central-directory preflights so role and handoff-kind buckets stay aligned.

## Payload Policy

The raw path derives these fields from central-directory entry names plus OPC
name-based role classification. It does not read package payload XML, expose
payload bytes, or invoke external ZIP/office/Pandoc validators.

## Validation

Focused validation passed:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  with 1 file, 5,261 assertions, 0 failures
