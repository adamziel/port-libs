# ODF core package handoff URI and media provenance

Slice: `plib-osst3`

ODF/ODT core package handoff now carries manifest URI suffix/query/fragment and
parameterized media-type provenance through the selected-entry preflight rows in
both rich `OdfReader` and compact `OpenDocumentPackage` summaries.

The enriched rows cover the main entry list plus selected source-byte-span,
local-header fixed-field, central-directory fixed-field, and data-descriptor
provenance lists. The handoff remains metadata-only under
`odf-core-package-handoff-metadata-only`; no package payload exposure policy
changes and no external Pandoc, office, ZIP, browser, or validator tools are
used.

Direct-format parity accounting: this is a package-ingestion metadata regression
with no upstream format denominator change.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php`
  - 1 test file, 101 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 2 test files, 2,220 assertions, 0 failures
- `git diff --check`
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
