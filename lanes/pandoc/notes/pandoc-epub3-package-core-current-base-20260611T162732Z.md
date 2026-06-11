# EPUB3 manifest media-type parameter provenance

## Slice

- Hook: `plib-z668p` / Pandoc EPUB3 package ingestion core blocker.
- Target: `lanes/pandoc` only.
- Base target recorded for submit: `origin/main` at `4c7bc38809bb3ea7c285607b4bd8e5006f64a9b8`.

## Implementation

- `EpubPackage` now preserves OPF manifest media-type parameter provenance as `mediaTypeBase`, `mediaTypeHasParameters`, `mediaTypeParameterCount`, `mediaTypeParameters`, and `mediaTypeParameterMap`.
- Compact package validation now reports manifest items with media-type parameters under `mediaTypeParameterItems` and emits `manifest-media-type-parameters` review diagnostics.
- XHTML navigation loading, `xhtmlAssets()`, and asset summaries now classify by base media type so parameterized XHTML/CSS/image entries remain visible to review handoff.

## Non-overlap

- This does not repeat prior EPUB guide, nav label, accessibility metadata, compression provenance, fallback, encryption, SMIL, CFI, remote-resource, or ZIP/OPC policy slices.
- No Pandoc, EPUBCheck, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test is invoked.

## Evidence

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` -> `1 test files, 1184 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 63907 assertions, 0 failures`

## Accounting

- `phpPass` moves `3069 -> 3070` after rebasing over mainline ODF manifest URI suffix coverage.
- Mapped denominator moves `3193 -> 3194`.
- Added `mappedEpubManifestMediaTypeParameterCases: 1`.
- Added `epubManifestMediaTypeParameterAssertions: 23`.
