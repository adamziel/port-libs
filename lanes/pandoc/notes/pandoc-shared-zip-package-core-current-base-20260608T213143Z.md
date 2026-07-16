# pandoc shared ZIP package core current-base 20260608T213143Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-shared-zip-package-core-current-base-20260608T213143Z`
- Accepted base: `029fcc990f7291eb0301393365e17c220ca14b3f`
- Implemented one bounded ZIP package primitive: raw package and entry comments containing control bytes remain inspectable, but native strict import now reports a specific `comment-control-bytes` diagnostic before DOCX/EPUB/ODT/WordPress media handoff.

## Behavior

- Extended `ZipPackage::commentPreflight()` with:
  - package-level raw comment control-byte offsets and `package-comment-control-bytes` issues;
  - entry-level raw comment control-byte offsets and `entry-comment-control-bytes` issues;
  - aggregate `hasCommentControlBytes`, `commentControlByteEntryCount`, and `commentControlByteEntries` fields.
- Extended `ZipPackage::strictImportPreflight()` to preserve the existing `package-or-entry-comments` policy while adding `comment-control-bytes` when raw package or entry comments contain C0/DEL control bytes.
- Updated the WordPress ZIP package preflight smoke to cover a package comment with `NUL` and an entry comment with `DEL`, without using external ZIP tooling.

## Verification

- Rework notes checked: no `port-pandoc-*.needs-lane-rework.md` notes existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1850 assertions, 0 failures`
- Focused final: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1882 assertions, 0 failures`
  - Delta: `+32` focused assertions in the existing ZIP package test file
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/ZipPackage.php`: no syntax errors
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`: no syntax errors
- JSON validation:
  - `lanes/pandoc/lane-status.json`: valid JSON
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: valid JSON
- `git diff --check -- lanes/pandoc`: passed
- Root harness: not run - isolated micro-slice

## Dependency Closure

- No new support component is needed. The slice reuses the native `ZipPackage` parser, in-memory package writer, strict import preflight, and the existing WordPress ZIP package smoke.
- No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`/`unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was run.

## Non-overlap

- Avoided already accepted ZIP package coverage for comment visibility/presence, central-directory signatures, invalid DOS timestamps, Unicode/case-insensitive name collisions, split archive markers, creator-host metadata, permission policy, encryption/AES policy, ZIP64 accounting, and trailing deflate payload integrity.
- Follow-up should choose a distinct ZIP/OPC package primitive, such as deeper ZIP64 central-directory edge validation, data-descriptor/layout provenance, or OPC relationship/content-type semantics.
