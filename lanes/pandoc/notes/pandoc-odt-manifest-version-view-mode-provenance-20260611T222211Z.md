# ODT manifest version and preferred-view-mode package provenance (plib-9h7f6)

Hook: plib-9h7f6, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice 20260611T222211Z.
Scope: lanes/pandoc only.

## Implementation

- Added aggregate manifest file-entry `version` counts to ODF package provenance review packets.
- Added aggregate manifest file-entry `preferred-view-mode` counts to ODF package provenance review packets.
- Preserved manifest version and preferred-view-mode values on URI suffix provenance items alongside the existing manifest order rows and ZIP inventory records.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- Single-test `TestRunner` invocation for `preserves ODT manifest version and preferred view mode package provenance` passed: 18 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` confirmed the new test passes in-file; the file remains red at 1 file, 5023 assertions, 22 known unrelated legacy expectation failures.

Current integration target: `origin/integration/pandoc-package-odf` at `b834a68dd`.
