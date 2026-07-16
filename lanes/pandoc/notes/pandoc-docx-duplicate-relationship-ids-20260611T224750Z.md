# DOCX duplicate relationship ID preflight (plib-s7vmp)

Hook: plib-s7vmp, Pandoc DOCX OpenXML package ingestion core blocker slice 20260611T224750Z.
Scope: lanes/pandoc only.

## Implementation

- Preserved ordered raw DOCX/OpenXML `.rels` relationship records alongside the existing ID-indexed relationship map.
- Added duplicate relationship ID provenance per relationship part, including duplicate groups, raw ordinals, target modes, resolved targets, target suffixes, existence, and content-type review metadata.
- Added package-level duplicate relationship ID rollups while keeping last-wins ID map compatibility for existing consumers.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `git diff --check -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 test file, 1038 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66843 assertions, 0 failures.

Current main target: 806d597b3.
