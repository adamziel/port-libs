# DOCX Source ZIP Leaf Name Provenance - 2026-07-02

Slice: `plib-fe227`

`DocxOpenXmlReader` now preserves metadata-only source ZIP leaf-name provenance
for native DOCX package ingestion.

## Scope

- ZIP package entries expose parent directory, leaf name, base name, extension,
  normalized extension key, and path depth.
- ZIP package provenance includes all leaf-name summaries plus shared leaf-name
  rollups for duplicate leaf names in different package directories.
- Package summary surfaces shared leaf-name counts and names for review queues.
- Loaded DOCX package parts inherit ZIP parent directory, leaf name, base name,
  extension, normalized extension key, and path depth fields.

The slice is metadata-only. It does not expose package bytes and does not invoke
Pandoc, Office suites, zip/unzip, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 12550 assertions, 0 failures`
