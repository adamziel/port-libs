# pandoc-shared-zip-package-core-current-base-20260609T044711Z

## Behavior

- Added bounded ZIP central-directory recovery metadata for archives whose EOCD `centralDirectorySize` stops before additional central-directory headers.
- `ZipPackage::centralDirectoryInventoryPreflight()` still reports these archives unsupported, but now exposes the gap signature, a short preview, and `recoverableGapEntries` with names, central indexes, record offsets, and local-header offsets for repair/reviewer handoff.
- EOCD candidate discovery now treats complete central-directory headers in the EOCD gap as a plausible candidate shape so the inventory preflight can classify the corruption instead of failing before diagnostics are available.
- `rawStrictImportPreflight()` carries the new `central-directory-eocd-gap-central-headers` diagnostic while keeping `canInstantiate=false`.

## Verification

- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2477 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2509 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`.
- Syntax checks passed for `lanes/pandoc/src/ZipPackage.php`, `lanes/pandoc/tests/ZipPackageTest.php`, and `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.

## Dependency Closure

No new support dependency is needed. This slice reuses native PHP ZIP byte scanning and raw strict import preflight aggregation; it does not call Pandoc, Cabal/Haskell runners, Word, LibreOffice, `zip`, `unzip`, external archive repair tools, or online services.

## Non-Overlap

This avoids the accepted ZIP64 extra-field, ZIP comment/provenance, platform metadata, and generic central-directory gap/tail slices. The new coverage is specifically recoverable central-directory headers in an EOCD gap caused by an understated central-directory size.

## Next

Useful follow-up: turn recoverable gap entries into a non-instantiating repair-plan summary for DOCX/EPUB/ODT readers, or wire raw strict ZIP diagnostics into those readers without accepting corrupt archives.
