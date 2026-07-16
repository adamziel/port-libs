# pandoc-shared-zip-package-core-current-base-20260608T115057Z

Accepted base: `ef204610238d00e07d53becb139e28941de74b31`

## Scope

Added a bounded native ZIP package preflight for platform metadata sidecars:
`__MACOSX/` entries, AppleDouble resource-fork entries such as `._name`, and
`.DS_Store` finder metadata. Raw ZIP entry reads remain permissive, but strict
package import now reports `platform-metadata-entries` before Office/EPUB/ODT
media handoff.

This does not overlap the accepted ZIP local-header order, traditional
encryption, Unicode name collision, invalid DOS timestamp, trailing-deflate,
central-directory signature, archive-extra-data, ZIP64, raw-name, or name
hygiene slices.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this slice.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1453 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 1500 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.
- PHP lint passed for:
  `lanes/pandoc/src/ZipPackage.php`,
  `lanes/pandoc/tests/ZipPackageTest.php`, and
  `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.
- Lane JSON validation passed for:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

## Delta

- Added one named focused PHP PASS case.
- Added 47 focused ZIP package assertions.
- Updated lane `phpPass` from `1630` to `1631`.
- Updated mapped denominator from `2051` to `2052`.
- Updated ZIP package core support cases from `22` to `23`.
- Updated ZIP package core assertion tally from `161` to `208`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `ZipPackage`
central/local entry inventory, raw entry reads, `strictImportPreflight()`, and
the existing WordPress ZIP package preflight example. No Pandoc, Cabal solver
or build command, Haskell runner, Word, LibreOffice, `zip`/`unzip`,
`ZipArchive`, external archive tool, online service, live provider test, or
live-service provider test was executed.

Root harness: not run - isolated micro-slice.
