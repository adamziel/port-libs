# ZIP Package Core Current-Base Duplicate-Name Slice

Date: 2026-06-09 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-duplicate-20260609T045631Z`

Accepted base: `b44fa1e4c39d90d096b8a3ca7585d5a157201f99`

## Behavior

- Extended `ZipPackage::centralDirectoryInventoryPreflight()` with duplicate
  central-directory package-part metadata:
  - decoded duplicate package names;
  - raw-name duplicate groups for review provenance;
  - central-directory indexes, record offsets, and distinct local-header
    offsets for each duplicate group.
- Raw strict import preflight now reports `central-directory-inventory-issues`
  and `duplicate-central-directory-entry-names` before constructor failure when
  a package advertises the same decoded part name twice.
- Kept existing raw-name collision behavior intact: raw-name duplicates that
  decode to distinct package names remain handled by `rawNamePreflight()` and do
  not gain an extra central-directory inventory rejection.
- Updated the WordPress ZIP preflight smoke so review queues expose duplicate
  package part names before treating embedded DOCX/EPUB/ODT media bytes as
  importable.

## Verification

- Focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 2502 assertions, 0 failures`
  - Added focused coverage: 1 PHP PASS line / 25 assertions over the previous
    recorded ZIP package focused run at `2477` assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- Syntax:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ZipPackage` EOCD parsing, central-directory inventory scanning, raw strict
import aggregation, in-memory ZIP fixtures, the lane-local focused TestRunner,
and the WordPress ZIP package preflight smoke. No Pandoc, Cabal solver/build
or Haskell runner, Word, LibreOffice, `zip`, `unzip`, PHP `ZipArchive`,
external archive tool, external converter, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted duplicate local-header offset rejection,
duplicate extra-field rejection, duplicate Info-ZIP Unicode extra-field
policy, raw-name collision strict import, case-insensitive or normalized-name
collisions, platform metadata sidecar policy, ZIP64, split archive,
local-header metadata mismatch, data-descriptor integrity, central-directory
signature, archive extra-data record, package-prefix, or comment Unicode-control
coverage. It is limited to decoded duplicate package part names in the central
directory and the raw strict diagnostics needed before package instantiation
fails.

## Next

Good follow-ups are DOCX/EPUB/ODT reader consumption of raw strict ZIP
diagnostics, remaining data-descriptor edge diagnostics, or bounded
central-directory recovery policy as separate native PHP slices.
