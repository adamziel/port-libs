# Shared ZIP Selected-Entry Status Policy

Slice: `plib-g0ym5`, shared ZIP/OPC package core blocker.

## Behavior

`ZipPackage::entryHandoffPreflight()` now returns compact `statusSummaries`
alongside the existing role, requirement, order, extension, path-depth, and
package-kind summaries.

The new summaries group selected package requests by `ready`, `blocked`,
`missing-required`, and `missing-optional` status. Each bucket carries request
counts, required/optional counts, present/missing counts, readable and failed
counts, duplicate-request counts, role lists, selected/handoff/missing/failed
entry names, selected and handoff byte totals, issues, and issue counts.

This lets DOCX, EPUB, and ODT package readers audit request policy outcomes
before exposing selected entry bytes. No additional package payload bytes are
read beyond the existing `entryHandoffPreflight()` readable-entry path.

## Evidence

Focused validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 5133 assertions, 0 failures`.

Status delta: focused PHP pass cases `464 -> 465`; `phpFail` remains `0`.

## Non-Overlap

This does not repeat accepted selected-entry summaries for role, requirement,
directory root, extension, order, path depth, package kind, compression method,
source-byte spans, raw names/comments, extra fields, platform attributes, fixed
headers, or data descriptors. It only adds a status-policy rollup over the
already-normalized selected-entry records.
