# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T053710Z`

Base: `a4ad52be6fbaf502880fe18d75ada98ee39a8d84`

## Behavior

- Added `ZipPackage::localEntries()` and `ZipPackage::localNames()` so package
  readers can distinguish central-directory order from physical local-header
  order.
- Switched EPUB3 and ODT mimetype preflight to require `mimetype` as the first
  local ZIP entry, not merely the first central-directory record.
- Updated the WordPress ZIP package preflight smoke to expose local package
  order for import review.

This closes a bounded support-library gap for EPUB/ODF containers whose central
directory order differs from local file-header order. It does not add ZIP64,
split archives, encryption, unsupported compression methods beyond stored and
deflated entries, or external archive tooling.

## Red-First Evidence

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` failed before
  implementation: `ZipPackage::localEntries()` was missing.
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` failed before
  implementation: EPUB rejected a valid package where `mimetype` was the first
  local entry but last in the central directory.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed before
  implementation: ODT rejected the valid local-first/central-last case and
  accepted a central-first package whose local first entry was not `mimetype`.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php` passed.
- `php -l lanes/pandoc/src/EpubReader.php` passed.
- `php -l lanes/pandoc/src/OdfReader.php` passed.
- `php -l lanes/pandoc/tests/ZipPackageTest.php` passed.
- `php -l lanes/pandoc/tests/EpubReaderTest.php` passed.
- `php -l lanes/pandoc/tests/OdfReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed:
  1 test file, 250 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed:
  1 test file, 400 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed:
  1 test file, 437 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed:
  20 test files, 7610 assertions, 0 failures.
- Counted PASS lines from the full lane command: 659.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  passed.
- `git diff --check -- lanes/pandoc` passed.

Root harness was not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP `ZipPackage`,
`ZipPackageEntry`, EPUB3, ODF, and WordPress preflight paths. No Pandoc, Cabal,
Haskell runner, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external
template engine, TeX/PDF engine, browser renderer, online sanitizer, or online
service was executed.

## Follow-Up

- Keep ZIP64, spanning archives, encrypted entries, and additional compression
  methods as explicit future package-core slices.
- Broader EPUB/ODF physical-layout audits can now build on `localEntries()`
  without re-parsing central-directory offsets.
