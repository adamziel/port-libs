# EPUB3 rootfile rendition media-type slice 2026-06-11T164259Z

Scope: compact EPUB3 package ingestion now keeps OPF rootfile rendition summaries when OCF `media-type` values include MIME parameters.

Changes:
- `EpubReader::readRenditions()` now matches OPF rootfiles by MIME base type, aligning rendition reporting with container rootfile selection.
- Selected and alternate rootfiles preserve the raw parameterized `media-type` string in their rendition summaries.
- Added coverage for a primary and fixed-layout alternate OPF rootfile declared as `application/oebps-package+xml; profile=...`.

Verification:
- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`: 1 file, 4010 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 63974 assertions, 0 failures after rebase onto `51a89684e`.

No Pandoc, EPUBCheck, zip/unzip, browser renderer, office suite, Node, Jupyter, TeX/PDF engine, external validator, online service, live provider test, or live-service provider test was invoked.

Status:
- `lane-status.json` `phpPass`: `3072 -> 3073`.
- Focused case added: `preserves parameterized OPF rootfile renditions for EPUB package review`.
