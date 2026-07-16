# ZIP Package Core Current-Base Slice

Date: 2026-06-08 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260608T232424Z`

Accepted base: `98e8999bf9b8bc75393d3cdf7374793f03cbce9c`

## Behavior

- Added stored-first ZIP container preflight metadata for `generalPurposeFlags` and `usesDataDescriptor`.
- `ZipPackage::storedFirstEntryPreflight()` now rejects descriptor-bearing first `mimetype` entries even when the entry is first, stored, extra-free, readable, and byte-exact.
- Extended the WordPress ZIP package preflight example with a raw descriptor-backed ODT `mimetype` fixture to keep streamed descriptor placeholders blocked for ODT/EPUB-style handoff.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 1999 assertions, 0 failures`.
- Red-first: after adding the focused descriptor-backed `mimetype` assertions before implementation, `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` failed with `1 test files, 1970 assertions, 1 failures` because `usesDataDescriptor` was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2010 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`.
- PHP lint: `php -l lanes/pandoc/src/ZipPackage.php`, `php -l lanes/pandoc/tests/ZipPackageTest.php`, and `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` all reported no syntax errors.
- JSON/diff checks: `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); echo $file, ": valid\n"; }'` passed, and `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native `ZipPackage` local-header/data-descriptor parsing, stored-first container preflight, raw in-test ZIP fixture construction, and the existing WordPress ZIP preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted central-directory signature, trailing-deflate, Unicode-name collision, invalid DOS timestamp, ZIP64, split archive, archive extra-data record, encryption, unsupported compression, local-header name/metadata, data-descriptor integrity, or strict import aggregate behavior. It is limited to the stored-first `mimetype` package preflight policy needed by ODT/EPUB container handoff.

## Next

Pick a non-overlapping ZIP/package primitive such as central-directory/local-header offset recovery diagnostics, remaining extra-field policy gaps, or DOCX/EPUB/ODT package-reader handoff behavior.
