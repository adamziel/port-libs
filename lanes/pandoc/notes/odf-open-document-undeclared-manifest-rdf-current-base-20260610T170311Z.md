# ODF/OpenDocument Undeclared Manifest RDF Current-Base Slice

## Scope

- Bead: `plib-h34f`
- Date: 2026-06-10 UTC
- Area: Pandoc ODF/ODT OpenDocument package ingestion

This slice discovers undeclared root `manifest.rdf` package sidecars by ZIP package path and routes them through the existing RDF metadata reviewer handoff. Parsed RDF sidecars remain inert package-review metadata, use bounded package reads, and stay out of media byte handoff.

The undeclared ZIP entry inventory is preserved: an undeclared `manifest.rdf` is still reported through `importReport.manifest.undeclaredEntries`, and arbitrary undeclared `.rdf` filenames are not promoted to RDF metadata without manifest evidence.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 3619 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60831 assertions, 0 failures

No Pandoc, Cabal/Haskell runner, office suite, `zip`/`unzip`, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.
