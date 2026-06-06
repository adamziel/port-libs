# pandoc-epub3-package-core-current-base-20260606T031954Z

Slice: EPUB3 package cover-image provenance on accepted base `3c8b9e6cdbfac97ac54f81052e1e910b2e2834ae`.

## Behavior

- Extended `EpubReader::assetReport()` to expose all importable cover-image candidates instead of only the selected candidate.
- Reports `coverImageCount`, `coverImages`, `coverImageDiagnosticCount`, and `coverImageDiagnostics`.
- Emits `multiple-cover-image-candidates` when EPUB3 manifest `properties="cover-image"` and legacy OPF `<meta name="cover">` point at different importable images.
- Emits `missing-meta-cover-image` when legacy OPF meta-cover points at a missing manifest item while an EPUB3 cover image remains available.
- Updated the WordPress EPUB3 package handoff example to expose conflicting cover candidates for attachment review.

## Verification

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 1295 assertions, 0 failures`.
- Red-first after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` failed with `1 test files, 1296 assertions, 1 failures` because `coverImageCount` was missing.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 1319 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed with `epub3 package handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `ZipPackage`, `OpcPackagePath`, `EpubReader`, and `WordPressBlockWriter` EPUB handoff path. No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, browser renderer, external EPUB validator, online service, or live provider test was executed.

## Non-Overlap

Avoided the accepted non-spine OPF asset fallback-chain slice from `pandoc-epub3-package-core-current-base-20260606T010938Z`; this patch is limited to cover-image candidate provenance and conflict/missing-target diagnostics.

## Follow-Up

Keep cover-selection export policy, remote-resource fetch policy, XHTML-to-AST normalization, CSS cascade/media query handling, media overlay playback semantics, and EPUBCheck parity as separate bounded slices.
