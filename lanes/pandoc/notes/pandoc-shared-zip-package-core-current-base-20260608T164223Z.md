# pandoc-shared-zip-package-core-current-base-20260608T164223Z

Accepted base: `f548c0e7c0c0e27d77af5a4032e60b4aaf51015e`

## Behavior

`ZipPackage::rawStrictImportPreflight()` now embeds bounded local-header name and metadata preflight summaries whenever the raw ZIP layout is scannable without ZIP64. This lets DOCX/ODT/EPUB/WordPress package review detect central-directory/local-header spoofing before strict package instantiation succeeds.

New raw strict diagnostics include:

- `localHeaderNames`
- `localHeaderMetadata`
- `local-header-name-issues`
- `local-header-metadata-issues`

Strict ZIP64 layouts still skip bounded central/local scans and leave those summaries as `null`.

## Evidence

Baseline before the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 1569 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- Result: `zip package writer preflight self-test passed`

Focused verification after the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 1599 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- Result: `zip package writer preflight self-test passed`

The patch maps one additional ZIP package support case. `phpPass` is unchanged because the focused assertion growth was added inside an existing PHP PASS case.

## Dependency Closure

No new native PHP support component is needed. The slice reuses existing bounded ZIP EOCD, central-directory inventory, local-header name, and local-header metadata primitives.

No Pandoc, Cabal/Haskell runner, `zip`/`unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ZIP slices for central-directory signatures, trailing deflate bytes, Unicode name collisions, invalid DOS timestamps, or descriptor-backed package stream provenance. It only widens raw strict import preflight so malformed local-header trust boundaries are visible before package instantiation.

Root harness: not run - isolated micro-slice.
