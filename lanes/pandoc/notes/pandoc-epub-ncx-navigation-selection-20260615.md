# Pandoc EPUB NCX Navigation Selection Slice 2026-06-15

Slice: `pandoc-epub-ncx-navigation-selection`

This slice stays under `lanes/pandoc` and adds a compact EPUB package handoff report for legacy NCX navigation selection. `EpubPackage::summary()` now exposes `ncxNavigationSelection`, and the same packet is available through `wordpressImport`.

The report carries:

- navigation source and whether the selected source is NCX;
- explicit `spine toc` id, resolved toc item, and whether that binding is usable;
- selected NCX manifest item, selected part name, and selected-by policy;
- fallback-to-manifest-scan state for missing or non-NCX `spine toc` selectors;
- target counts and compact diagnostic types.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` -> 1 file, 2637 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 85849 assertions, 0 failures

Accounting:

- rebased current main: `477b0f78f`
- `phpPass`: 3644 -> 3645
- `phpFail`: 0
- mapped upstream cases: 3681 -> 3682
- `mappedEpubNcxNavigationSelectionCases`: 1
- `epubNcxNavigationSelectionAssertions`: 26

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
