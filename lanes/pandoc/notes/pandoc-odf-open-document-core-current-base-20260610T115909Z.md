# ODF/OpenDocument Undeclared Package Entry Review

Slice: `pandoc-odf-open-document-core-current-base-20260610T115909Z`

## What Changed

Native `OdfReader` package ingestion now inventories non-directory ZIP payload
entries that are omitted from `META-INF/manifest.xml`. These are exposed as
`odf-manifest-undeclared-package-entry` diagnostics under
`importReport['manifest']['undeclaredEntries']` with package-local part names,
byte lengths, compressed byte lengths, compression methods, and CRC32 values.

The importer keeps rendering declared ODT content and keeps undeclared payloads
out of the declared media handoff. This gives WordPress/package review queues a
bounded package-integrity signal without trusting or exposing files that the ODF
manifest did not declare.

This is a bounded ODF/ODT package-ingestion slice. It does not invoke Pandoc,
office suites, zip/unzip, browser renderers, external validators, online
services, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- Red-first focused run before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: expected failure for missing `undeclaredEntries` /
    `undeclaredEntryCount` report fields.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 3491 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 59862 assertions, 0 failures`

## Accounting

- `phpPass`: `2955 -> 2956`
- `phpFail`: `0`
- ODF/OpenDocument mapped cases: `14 -> 15`
- ODF/OpenDocument focused assertions: `300 -> 310`
- Focused ODF reader coverage adds one PASS case for manifest-vs-ZIP
  undeclared package entry metadata.

## Scope Notes

This does not repeat the accepted ODF manifest root validity, duplicate path,
missing declared media, encrypted-resource, RDF sidecar, preferred-view-mode, or
declared-size mismatch slices. It only reports ZIP entries that exist in the
package but are not declared by the ODF manifest.
