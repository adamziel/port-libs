# Shared ZIP/OPC Package Entry API

## Scope

This slice adds a bounded package-entry API for ZIP-backed DOCX, EPUB, and ODF
handoff paths:

- `ZipPackage::hasPackagePart()`, `packagePartEntry()`,
  `readPackagePart()`, and `readPackagePartBounded()` normalize OPC package
  part URI references before ZIP lookup.
- `ZipPackage::entryHandoffPreflight()` now records lookup mode, canonical OPC
  part name provenance, compression method names, data descriptor flags, local
  payload offsets, bounded read byte counts, CRC, and SHA-256 hashes for
  selected stored/deflated entries.
- `OpcPackagePath::canonicalPartNameFromUriReference()` canonicalizes URI paths
  while validating query and fragment percent escapes before package readers
  strip suffixes for local ZIP entry lookup.

## Source Truth

Pandoc package readers consume DOCX, EPUB, and ODF as ZIP-backed package parts.
Higher-level readers should be able to request a package part using OPC URI
syntax, including relationship-derived query or fragment suffixes, without
duplicating ZIP path normalization or accidentally treating unsafe percent
escapes as local entry names.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice,
`zip`, `unzip`, EPUBCheck, browser renderer, external validator, online service,
or live provider test was executed.

## Verification

- Current required base: `592488d646306dddcb4f4ddb49e196583fdbab7a`
  (`pandoc: normalize csl biblatex metadata aliases`).
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/OpcPackagePath.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- Focused `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `2 test files, 7046 assertions, 0 failures`.
- Full `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 62449 assertions, 0 failures`.

## Delta

- Focused PHP pass count: `3047 -> 3049`.
- Added ZIP package part lookup/read coverage for URI references, missing
  parts, size bounds, unsafe dot-segment escapes, and unsafe suffix escapes.
- Added selected-entry handoff coverage for OPC lookup mode, canonical part
  name provenance, compression method names, descriptor flags, local payload
  offsets, and unsupported compression reporting.

## Non-Overlap

This does not change central-directory parsing, local-header validation,
data-descriptor parsing, ZIP64 policy, encryption policy, aggregate size
preflight, raw ZIP repair metadata, OPC relationship graph traversal, content
type resolution, DOCX/EPUB/ODF reader semantics, or media byte exposure policy.
It only adds a shared entry lookup/read surface and selected-entry provenance
for package readers that already depend on `ZipPackage`.
