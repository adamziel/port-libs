# Shared ZIP/OPC Platform Metadata Handoff

Issue: plib-f0kt5
Date: 2026-06-28

`ZipPackage::entryHandoffPreflight()` now carries selected-entry platform
metadata provenance before reader byte exposure. macOS sidecars
(`__MACOSX`, AppleDouble resource forks, `.DS_Store`) and Windows sidecars
(`Thumbs.db`, `desktop.ini`) are tagged on each selected request, blocked as
metadata-only entries, and kept out of `handoffEntries`.

The preflight also reports selected and readable handoff aggregate summaries:
platform counts, issue counts, platform buckets, issue buckets, role lists,
entry names, and byte totals. This lets DOCX, EPUB3, ODF/ODT, and other
package readers audit platform metadata sidecars without importing them as
document content or package media.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 5,423 assertions, 0 failures

Direct-format parity accounting: no input/output format denominator change.
This is a shared native PHP ZIP/OPC package primitive that supports bounded
package ingestion without shelling out to Pandoc, office suites, TeX/browser
engines, ZipArchive, zip/unzip, external validators, or network services.
