# ODF Reader Manifest Path Preflight Parity

Bead: `plib-84rk2`
Date: 2026-06-30 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Scope

`OdfReader` now applies the compact `OpenDocumentPackage` manifest package-path
preflight to `manifest:file-entry manifest:full-path` rows before conversion
handoff. Rich-reader manifest rows reject absolute package paths, dot-segment
paths, malformed percent escapes, decoded ASCII control bytes, and URI-scheme
references before package lookup.

Document and signature package references keep their existing `./` and leading
slash normalization behavior so common ODT content references such as
`./Object 1` and encoded media paths continue to resolve.

## Direct-Format Parity

This closes a rich-reader parity gap with compact OpenDocument ingestion for
manifest `full-path` preflight. The change is bounded to native PHP ODF/ODT
package ingestion and does not expose new package bytes or invoke Pandoc,
office suites, TeX/browser engines, zip/unzip, Jupyter, Node tooling, external
validators, online services, or live providers.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 5072 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 1896 assertions, 0 failures`
