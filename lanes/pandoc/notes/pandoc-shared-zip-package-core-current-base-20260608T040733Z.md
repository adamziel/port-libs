# Pandoc ZIP Package Core Current-Base Slice

Slice: `pandoc-shared-zip-package-core-current-base-20260608T040733Z`
Base: `e8c43317726abb932805c171a399c58fb2c01c99`

## Behavior

`ZipPackage` now rejects Info-ZIP Unicode comment extra fields that replace a
non-empty raw entry comment with an empty decoded value. Before this slice, a
crafted `0x6375` Unicode comment extra with a matching CRC and no UTF-8 text
could make strict comment preflight treat the entry as comment-free, hiding
review metadata before Office/EPUB/ODT or WordPress package media handoff.

This does not overlap the existing ZIP coverage for central directory
signatures, trailing deflate bytes, Unicode name collisions, duplicate Unicode
extra fields, UTF-8 flag contradictions, invalid DOS timestamps, ZIP64, split
archives, permissions, or data descriptors.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1315 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  failed with `1 test files, 1316 assertions, 1 failures` because the empty
  Unicode comment extra did not throw `RuntimeException`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1323 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed.
- Syntax checks passed for `lanes/pandoc/src/ZipPackage.php`,
  `lanes/pandoc/tests/ZipPackageTest.php`, and
  `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.
- JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and
  `lanes/pandoc/lane-status.json`.
- `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage`
Info-ZIP Unicode extra-field parsing, strict comment preflight, focused ZIP
tests, and the lane-local WordPress ZIP package preflight example.

Pandoc, Cabal/Haskell runners, zip/unzip, ZipArchive, Word, LibreOffice,
external archive tools, online services, live provider tests, and live-service
provider tests were not run.

Root harness: not run - isolated micro-slice.
