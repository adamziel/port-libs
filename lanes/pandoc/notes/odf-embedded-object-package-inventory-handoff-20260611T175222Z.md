# ODF embedded object package inventory handoff

Bead: `plib-85vo0`
Base: `0b4dca730`
Date: 2026-06-11 UTC

## Change

`OdfReader` now exposes package-level embedded object inventory metadata in `embeddedObjects`, document manifest attrs, document metadata as `odfEmbeddedObjects`, and the manifest import report.

The inventory covers manifest-declared ODF object roots, content-referenced object placeholders, and undeclared `Object*` package folders. Each item records referenced/unreferenced state, declaration and existence state, encrypted/missing/undeclared/unreferenced diagnostics, contained package parts, byte/compression/CRC provenance, media-type parameter provenance, and a metadata-only review policy that does not expose embedded object bytes.

The focused fixture covers:

- declared referenced chart object with MIME parameters;
- encrypted spreadsheet/OLE object folder;
- referenced missing object root;
- undeclared referenced object folder;
- declared unreferenced graphics object root.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed: 1 test file, 3926 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 64676 assertions, 0 failures.

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
