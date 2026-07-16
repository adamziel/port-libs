# DOCX/OpenXML Malformed Content-Type Record Collision Diagnostics

Date: 2026-06-13
Base: 7389d563 after XML direct input registry

This slice extends `DocxOpenXmlReader` package provenance for malformed `[Content_Types].xml` record collisions without invoking Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, Node tooling, online services, live provider tests, or external validators.

## Behavior

- Package provenance now exposes declared content-type record counts separately from the deduplicated Default/Override maps used for ingestion.
- Duplicate Default extension and Override part-name keys, groups, and counters propagate into the package summary.
- Invalid content-type records are preserved as ordered `invalidContentTypeRecords` snapshots with issue lists.
- Invalid record issue-code buckets and counts propagate into both `contentTypesPart` and the package summary.
- Document ingestion and relationship diagnostics continue to resolve the main document and image relationship content types even when unrelated malformed records are present.

## Evidence

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 file, 2520 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed after rebase: 46 files, 75828 assertions, 0 failures.
