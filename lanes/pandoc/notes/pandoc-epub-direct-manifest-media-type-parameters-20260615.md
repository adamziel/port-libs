# EPUB Direct Manifest Media-Type Parameters

Slice: `plib-tmxap`, EPUB3 package ingestion.

`EpubPackageReader` now normalizes OPF manifest `media-type` base values while
preserving raw MIME strings, parameter maps, duplicate parameter diagnostics,
invalid parameter diagnostics, and aggregate manifest media-type review rows.
Parameterized XHTML spine content, NCX navigation, CSS `fallback-style` targets,
and SMIL `media-overlay` targets remain discoverable in the direct directory
reader.

This stays under `lanes/pandoc` and does not invoke Pandoc, EPUBCheck,
zip/unzip, ZipArchive, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - `1 test files, 1168 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86718 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check`
- Conflict-marker scan over the edited PHP, test, status, and note files

Accounting:

- Rebase base: `e983cbfe2f`
- `phpPass`: `3675 -> 3676`
- `phpFail`: `0`
- `mappedEpubDirectManifestMediaTypeParameterCases`: `1`
- `epubDirectManifestMediaTypeParameterAssertions`: `30`
