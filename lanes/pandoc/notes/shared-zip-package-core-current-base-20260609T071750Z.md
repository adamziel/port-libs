# ZIP Package Raw Central-Directory Name Collision Preflight

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T071750Z`

Base accepted HEAD: `606e24ec818a38feb2a796c2f2b7d182ce531afd`

## Behavior

`ZipPackage::centralDirectoryNameCollisionPreflight()` now scans the raw
central directory before package instantiation and reports two bounded review
risks:

- decoded entry names that collide after the existing case-insensitive and
  Unicode-normalized ZIP name fold;
- raw central-directory header names that collide even when Info-ZIP Unicode
  path extras decode to different visible names.

`ZipPackage::rawStrictImportPreflight()` now includes the
`centralDirectoryNameCollisions` summary and promotes
`central-directory-name-collision-issues`,
`case-insensitive-name-collisions`, and `raw-name-collisions` diagnostics before
returning a strict import package.

The focused fixture keeps every entry encrypted so normal `fromString()` package
construction still fails. That proves the new policy is sourced from the raw
central-directory inventory path rather than post-instantiation entry objects.

## Verification

Baseline before patch:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 2736 assertions, 0 failures`

Focused verification after patch:

- `php -l lanes/pandoc/src/ZipPackage.php`
  - `No syntax errors detected`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `No syntax errors detected`
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 2768 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - `zip package writer preflight self-test passed`
- `git diff --check -- lanes/pandoc`
  - passed with no output

Focused movement: +1 PHP PASS line, +32 focused assertions, +1 mapped ZIP
package core support case.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
EOCD locator, central-directory inventory scanner, bounded ZIP name decoding,
Info-ZIP Unicode path extra handling, raw strict import preflight, and
in-memory test fixtures.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external
template engine, TeX/PDF engine, browser renderer, online service, live
provider test, or ZipArchive process was executed.

## Non-Overlap

This slice does not change ZIP extra-field structure parsing, timestamp policy,
Unix mode/symlink policy, local-header mismatch policy, data-descriptor policy,
span/offset repair, compression/encryption handling, ZIP64 handling, archive
repair, DOCX/EPUB/ODT conversion, or any external-tool path. It only adds raw
central-directory name-collision diagnostics for ZIP package preflight.

## Next Task

Wire the raw strict name-collision diagnostics into DOCX/EPUB/ODT import
reports, or choose another bounded central-directory/path-hygiene policy that
can be proven with native PHP fixtures and focused tests.
