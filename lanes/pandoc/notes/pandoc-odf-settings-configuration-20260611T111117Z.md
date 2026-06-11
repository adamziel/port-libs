# Pandoc ODF Settings Configuration Ingestion

Implemented one bounded native PHP ODF/OpenDocument package-ingestion slice for
manifest-declared `settings.xml` configuration metadata.

## Behavior

- `OpenDocumentPackage` now treats manifest-declared `settings.xml` as an
  optional package part whose absence is diagnosed before package handoff.
- `office:document-settings` is parsed into inert reviewer metadata: config
  item sets, typed scalar config items, and indexed/named map entries.
- Parsed settings are exposed through `settings()`, document attributes, and
  package summaries alongside existing manifest byte provenance.

This slice does not invoke Pandoc, LibreOffice, zip/unzip, browser renderers,
external validators, online services, or live provider tests.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - Result: 1 test file, 200 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 44 test files, 62540 assertions, 0 failures.

Status delta on required base `407d7449945672e0605a25fb4a4b5888a14c2249`:
`phpPass` moves from `3049` to `3050`; focused checks move from `948` to
`949`.
