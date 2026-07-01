# DOCX Source ZIP Creator Host Provenance

Slice: `plib-7rkwx`

## Scope

DOCX/OpenXML package ingestion now summarizes selected source ZIP creator host-system and creator-version provenance through `DocxOpenXmlReader` package provenance. The summary stays metadata-only and follows the selected source records that are already exposed for DOCX package review.

## Handoff

- `zipPackage.sourceRecords` now includes selected source creator-host counts, known/unknown host counts, creator-version comparison counts, host-system buckets, unknown-host entries, below-needed creator-version entries, and per-entry creator-version rows.
- `packageProvenance.summary` mirrors those source-record fields under `zipSourceCreator*` keys for reviewer dashboards and importer gates.
- Selected creator rows are derived from the existing package-level ZIP creator-host preflight by source-record name, so entries without platform attribute side effects still retain creator host/version metadata.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- conflict-marker scan of the changed files
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` with 1 file, 11851 assertions, 0 failures.
