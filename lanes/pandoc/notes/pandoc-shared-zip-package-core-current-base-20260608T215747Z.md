# Pandoc ZIP Package Current-Base Windows Name Hygiene

Slice: `pandoc-shared-zip-package-core-current-base-20260608T215747Z`
Base: `061aea1caf3c0acd567538f40de503a885da8ad4`

## Behavior

Native `ZipPackage::nameHygienePreflight()` now reports cross-platform unsafe ZIP entry path segments before DOCX/ODT/EPUB and WordPress media handoff:

- Windows reserved device basenames, including extension forms such as `aux.txt`.
- Windows alternate data stream segments such as `review.png:Zone.Identifier`.

Raw ZIP parsing and `read()` remain permissive so reviewers can inspect package bytes, but `strictImportPreflight()` and strict import assertions reject these entries through the existing `name-hygiene-review-entries` diagnostic.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 1894 assertions, 0 failures`.
- Red-first: after adding Windows name-hygiene expectations, `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` failed with `1 test files, 1861 assertions, 1 failures` because the preflight still reported only the three existing whitespace/trailing-dot review entries.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 1942 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` passed with `zip package writer preflight self-test passed`.
- PHP lint passed for `lanes/pandoc/src/ZipPackage.php`, `lanes/pandoc/tests/ZipPackageTest.php`, and `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Non-Overlap

This slice does not change central-directory signatures, ZIP64 policy, unsupported compression methods, trailing deflate payload integrity, encrypted packages, data descriptors, invalid DOS timestamps, case-insensitive or Unicode-normalized collisions, raw name collisions, path hierarchy collisions, Unix permission metadata, platform sidecars, NTFS extra fields, or package comment handling.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP `ZipPackage` parser, name-hygiene preflight, strict-import preflight, focused PHP tests, and the existing WordPress ZIP package preflight smoke. No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Next

Choose a non-overlapping ZIP package primitive such as bounded ZIP64 policy gaps, unsupported creator-host edge cases, content-type handoff integration, or stricter raw-name provenance.
