# pandoc-epub3-spine-duplicate-itemref-id-20260612T014428Z

Slice: `plib-gtk11` / EPUB3 package ingestion.

This slice extends `EpubReader` spine package review metadata so duplicate OPF
`spine/itemref` IDs are no longer invisible when metadata refinements or linked
resources target that ID. `spineProperties` now reports `duplicateItemIdCount`,
`duplicateItemIds`, `duplicateItemIdItemCount`, and item diagnostics for each
duplicate entry while preserving reading-order XHTML handoff.

The focused fixture uses two spine entries sharing `id="spine-review"` with one
`rendition:viewport` refinement. The reader keeps both spine items and raw HTML
document children intact, while exposing `duplicate-spine-itemref-id`
diagnostics in the import report and document attrs.

Verification:
- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` (1 file, 4086 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 68667 assertions, 0 failures)

Accounting:
- Adds one focused `EpubReaderTest.php` PASS case and 26 focused assertions for
  EPUB3 spine duplicate itemref ID package provenance.
- No Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators,
  online services, live provider tests, or live-service provider tests were run.
