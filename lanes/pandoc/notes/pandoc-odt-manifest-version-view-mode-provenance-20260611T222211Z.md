# ODT manifest version and preferred-view-mode package provenance (plib-9h7f6)

Hook: plib-9h7f6, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice 20260611T222211Z.
Scope: lanes/pandoc only.

## Implementation

- Added manifest file-entry `version` and `preferredViewMode` provenance to ODF package provenance order rows.
- Added aggregate manifest version and preferred-view-mode counts for package review packets.
- Added per-ZIP-part `manifestVersion` and `manifestPreferredViewMode` fields to ODF package inventory records exposed in document manifest attrs and import reports.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed: 1 test file, 4072 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66570 assertions, 0 failures.

Current main target: 895143aff.
