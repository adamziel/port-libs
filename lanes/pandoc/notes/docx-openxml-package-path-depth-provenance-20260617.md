# DOCX OpenXML Package Path Depth Provenance

Bead: `plib-9osm3`

Base: `origin/main` at `1d6eaec9ce`

Slice: `pandoc-docx-openxml-package-path-depth-provenance`

## Scope

- Added DOCX/OpenXML package part path provenance to `DocxOpenXmlReader`.
- Each package inventory entry now records `pathSegments`, `pathSegmentCount`, and `directoryDepth`.
- Package summary now exposes `partPathDepths`, `maxPartPathSegmentCount`, `maxPartDirectoryDepth`, `deepestPartNames`, and `deepestParts`.
- Directory buckets now include `directoryDepth`.

## Fixture

- Added `summarizes docx package part path depths for review handoff` to `DocxOpenXmlReaderTest.php`.
- The fixture covers root package items, deeply nested embedded parts, extensionless missing-content-type parts, and nested relationship sidecars.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 5641 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258 test files, 177409 assertions, 0 failures`

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, office suites, Word, LibreOffice, zip/unzip, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests were invoked.
