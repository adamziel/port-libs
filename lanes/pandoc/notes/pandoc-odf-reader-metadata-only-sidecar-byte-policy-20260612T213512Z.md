# pandoc-odf-reader-metadata-only-sidecar-byte-policy-20260612T213512Z

Slice: `pandoc-odf-reader-metadata-only-sidecar-byte-policy`, based on current main `caad74ce0e`.

This slice stays inside `lanes/pandoc` and tightens ODF/ODT package ingestion review policy in the rich `OdfReader` path. Embedded object package payloads and RDF metadata sidecars are now identified during manifest hydration before byte-exposure decisions are made, so package review metadata can keep their provenance without treating those sidecars as ordinary document media bytes.

Changes:
- `OdfReader` now hydrates manifest entries in two passes so encoded embedded object roots are known before contained parts receive `canExposeBytes` and `byteExposurePolicy`.
- Embedded object package parts now carry `embeddedObject*` provenance on manifest and package-inventory entries, are assigned `embedded-object-package-bytes-blocked`, and stay out of `media` handoff.
- RDF sidecars now carry `rdfMetadataPart` provenance and `rdf-metadata-bytes-blocked` in rich package provenance while staying parseable as RDF review metadata.
- Focused ODF tests assert metadata-only behavior for RDF sidecars, object OLE payloads, embedded object preview images, package role counts, and manifest media-type byte buckets.

Verification:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` (1 file, 4351 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 73260 assertions, 0 failures)

No Pandoc, office suites, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
