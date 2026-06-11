# DOCX Support Relationship Suffix Provenance

Bead: `plib-akdu4`
Date: 2026-06-11 UTC
Base target: `origin/main 0f7efc602cb4133001cda309904e94e86c32b4b9`

## Scope

This slice adds bounded native PHP DOCX/OpenXML package-ingestion provenance for
query and fragment suffixes on relationship-selected support parts and settings
relationship children.

`DocxReader` now preserves `targetQuery` and `targetFragment` for:

- `word/settings.xml` selected through the document relationship graph;
- `word/webSettings.xml` settings-child relationships;
- mail-merge header-source relationship children.

The reader still loads path-only package parts for parsing, so package ingestion
does not treat URI suffixes as part names.

## Evidence

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 test file, 5002 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64525 assertions, 0 failures

## Accounting

- `phpPass`: 3084 -> 3085
- `mappedDocxSupportRelationshipSuffixCases`: 1
- `docxSupportRelationshipSuffixAssertions`: 7
- Mapped denominator: 3201 -> 3202

## Boundaries

No Pandoc, Word, LibreOffice, office suite, `zip`/`unzip`, browser renderer,
Node tooling, Jupyter, external validator, online service, live provider test,
or live-service provider test was invoked.

This does not repeat accepted DOCX numbering, note-source, selected
relationship, font-table, package-property, or extended-property slices. It only
adds suffix provenance for support relationships and settings-child
relationship summaries.
