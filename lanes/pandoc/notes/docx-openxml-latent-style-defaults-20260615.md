# DOCX/OpenXML latent style defaults

Slice: `plib-f6krh`, DOCX/OpenXML package ingestion.

## Behavior

- `DocxOpenXmlReader` now reads `w:latentStyles` from the selected
  `word/styles.xml` package part.
- The reader preserves metadata-only style catalog defaults:
  `defLockedState`, `defUIPriority`, `defSemiHidden`,
  `defUnhideWhenUsed`, `defQFormat`, and declared style count.
- The reader also indexes `w:lsdException` records by style name and summarizes
  quick-format, semi-hidden, unhide-when-used, and locked exception buckets for
  package review handoff.
- This is review provenance only; it does not change body rendering, heading
  detection, list mapping, or writer output.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 3446 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 test files, 90148 assertions, 0 failures`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check` passed.

## Delta

- `phpPass`: `3789 -> 3790`
- mapped upstream cases: `3780 -> 3781`
- `mappedDocxOpenXmlLatentStyleDefaultsCases = 1`
- `docxOpenXmlLatentStyleDefaultsAssertions = 28`
- `mappedDocxOpenXmlCoreCases`: `39 -> 40`
- `docxOpenXmlCoreAssertions`: `575 -> 603`

## Non-Overlap

This does not repeat accepted DOCX style-linked numbering, table style
inheritance, conditional table style regions, settings policy, content
controls, package relationships, theme font/color metadata, comments/endnotes,
custom XML, chart package metadata, or XML/HTML5 DOM work. It only closes the
bounded DOCX `styles.xml` latent-style catalog metadata gap.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, `ZipArchive`, external
validators, online services, live provider tests, or live-service provider tests
were run.
