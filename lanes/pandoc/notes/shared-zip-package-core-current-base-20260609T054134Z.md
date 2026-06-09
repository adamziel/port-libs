# Pandoc ZIP Package Core Current Base

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T054134Z`
Base accepted HEAD: `50ff75128f57e5d1c91c6f6643df81bffbb2e704`

## Behavior

Added bounded raw ZIP extra-field structure preflight support. `ZipPackage::extraFieldStructurePolicyPreflight()` scans central-directory and local-header extra-field byte streams before package instantiation, reports truncated extra-field headers and payloads, and returns issue-entry summaries for package import review. `ZipPackage::rawStrictImportPreflight()` now includes this summary under `extraFieldStructure` and adds `extra-field-structure-issues` plus concrete structural diagnostics when malformed package metadata blocks bounded native readers.

The focused fixture covers:

- Central-directory extra-field payload declared longer than available bytes.
- Local-header extra-field data too short to contain a field header.
- Raw strict preflight surfacing both issues even though `ZipPackage::fromString()` cannot instantiate the package.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php` - passed.
- `php -l lanes/pandoc/tests/ZipPackageTest.php` - passed.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` - passed.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - passed, `1 test files, 2648 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` - passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new external support component is needed. This slice reuses native PHP EOCD, central-directory, and local-header readers and does not invoke Pandoc, Cabal/Haskell runners, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, TeX/PDF engines, browser renderers, external validators, online services, or live provider tests.

## Non-Overlap

This does not repeat recent ZIP slices for platform metadata sidecars, Unicode path/comment policy, ZIP64 extra-field semantics, comments, archive extra-data records, data descriptors, or central-directory repair. The new policy is structural only: it records malformed extra-field framing before semantic extra-field parsers run.

## Next

Possible follow-up: feed `extraFieldStructure` issue entries into DOCX/EPUB/ODT importer reports, or add a separate raw strict duplicate-extra-field policy that mirrors the existing instance-level extra-field duplicate checks without requiring package instantiation.
