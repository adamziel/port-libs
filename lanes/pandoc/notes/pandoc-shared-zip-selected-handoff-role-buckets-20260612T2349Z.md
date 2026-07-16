# Pandoc Shared ZIP/OPC Selected Handoff Role Buckets

Slice: `plib-1awy4`, shared ZIP/OPC package closure on accepted base `70d29eb6f8`.

## Gap

Shared ZIP/OPC selected-entry handoff already reported role request summaries,
selected unique bytes, missing entries, and failed entries. It did not expose
role-level readable handoff byte buckets or issue counts, which made DOCX,
EPUB, and ODT package readers do extra work to distinguish readable document
payloads from duplicate requests, oversized sidecars, and missing required
package parts before exposing selected bytes.

## Implementation

`ZipPackage::entryHandoffPreflight()` role summaries now include:

- `handoffUniqueEntryCount`
- `handoffCompressedBytes`
- `handoffUncompressedBytes`
- `handoffEntryNames`
- `issueCounts`

The implementation keeps duplicate selected requests from double-counting
handoff byte totals while still preserving request-level `handoffEntryCount`
and failed issue counts.

## Metric

- `phpPass`: `3331 -> 3332`
- `phpFail`: `0`
- `mappedZipSelectedHandoffRoleBucketCases`: `1`
- `zipSelectedHandoffRoleBucketAssertions`: `13`
- Shared ZIP/OPC local matrix numerator: `106 -> 107` over upstream denominator
  `67`; verdict remains not shippable because DOCX and EPUB package readers
  are partial, ODT remains coupled to continued ZIP/OPC hardening, and
  PPTX/XLSX readers are still unsupported.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 4448 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `45 test files, 74831 assertions, 0 failures`

No Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, `ZipArchive`,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Scope

This does not repeat accepted manifest content-type byte buckets, role/handoff
kind byte buckets, selected compression summaries, selected data descriptor
provenance, selected local fixed-header provenance, selected platform
attributes, selected raw name/comment provenance, or raw central-directory
preflight. The slice is only role-level selected-entry handoff accounting for
readable byte exposure and blocked package sidecars.
