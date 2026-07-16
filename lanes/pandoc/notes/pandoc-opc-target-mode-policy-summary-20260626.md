# OPC TargetMode policy summary

Slice: `plib-uxyy8`, shared ZIP/OPC package primitives.
Base: current `origin/main`.

## Change

`OpcRelationship` now preserves whether `TargetMode` was explicitly present
when parsing relationship XML. `OpcRelationshipGraph` exposes a compact
`relationshipTargetModeSummary()` for importer gates, covering:

- implicit internal relationships;
- explicit `TargetMode="Internal"` relationships;
- explicit `TargetMode="External"` relationships;
- optional source-part and relationship-type filters.

Serialization behavior is unchanged: internal target modes remain omitted from
generated relationship XML, while external target modes are still emitted.

## Verification

- `php -l lanes/pandoc/src/OpcRelationship.php`
- `php -l lanes/pandoc/src/OpcRelationships.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed: 1 test file, 4,556 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` is not green in the known broad
  lane baseline: 283 test files, 108,052 assertions, 10,526 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `440 -> 441`.
- Added one focused `OpenPackagingConventionsTest` case for TargetMode
  declaration accounting across package-root and document relationship parts.

## Non-Overlap

This does not repeat the prior external-target package-part shadow diagnostic,
relationship transform TargetMode serialization, malformed TargetMode rejection,
or DOCX-reader relationship-record inventory work. It adds only the shared OPC
graph summary needed by importer policy gates and does not shell out, fetch
external targets, or expose package payload bytes.
