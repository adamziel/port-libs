# Pandoc ODF OpenDocument Core Slice

## Summary

Implemented a bounded native PHP ODF/OpenDocument package-ingestion slice for
undeclared ZIP directory-entry provenance.

`OdfReader` already reported ZIP file entries omitted from
`META-INF/manifest.xml`, but central-directory directory entries were ignored to
avoid treating them as payload media. This slice keeps that payload accounting
unchanged and adds a separate import-report bucket:

- `importReport.manifest.undeclaredDirectoryEntryCount`
- `importReport.manifest.undeclaredDirectoryEntries`

Each directory entry records the package part, diagnostic code, directory flag,
non-exposure policy, byte/compressed-byte lengths, compression method, and CRC.
Directory entries remain out of media byte handoff.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 3674 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 60931 assertions, 0 failures`

No Pandoc, Cabal/Haskell runner, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service provider
test was executed.

## Accounting

- `phpPass`: `2994 -> 2995`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3151 -> 3152`
- `mappedOdfUndeclaredDirectoryEntryCases`: `1`
- `odfUndeclaredDirectoryEntryAssertions`: `14`

## Non-Overlap

This does not repeat accepted ODF mimetype placement, manifest directory
declarations, manifest media-type buckets, undeclared ZIP file payloads, script
package inventory, RDF sidecars, XML signatures, encrypted media, declared-size
mismatches, style diagnostics, content parsing, or WordPress rendering. It only
surfaces undeclared ZIP directory entries as inert package-structure metadata.
