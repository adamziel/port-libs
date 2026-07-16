# EPUB Direct Reader Spine Page Spread Review

Slice: `pandoc-epub-reader-spine-page-spread`

This slice extends native PHP EPUB3 directory package ingestion in
`EpubPackageReader`. Direct OPF spine `itemref` rows now preserve page-spread
semantics for package review:

- selected page-spread placement from `page-spread-left`,
  `page-spread-right`, `spread-none`, and rendition-prefixed equivalents;
- raw page-spread property tokens and placement matches on each spine item;
- conflicting placement diagnostics;
- aggregate `spineReport` page-spread rows and top-level handoff aliases.

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 file, 1430 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 88376 assertions, 0 failures

Accounting:

- `phpPass`: 3725 -> 3726
- `phpFail`: 0
- mapped upstream cases: 3744 -> 3745
- `mappedEpubReaderSpinePageSpreadCases`: 1
- `epubReaderSpinePageSpreadAssertions`: 28
