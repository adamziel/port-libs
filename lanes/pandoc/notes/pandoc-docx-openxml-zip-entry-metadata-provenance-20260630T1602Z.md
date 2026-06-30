# DOCX OpenXML ZIP Entry Metadata Provenance

Slice: `plib-ff9zx`, DOCX/OpenXML package ingestion.

## Scope

`DocxOpenXmlReader` now carries instantiated `ZipPackage` metadata policy preflights through DOCX package provenance. The `zipPackage` section includes general-purpose flags, modification times, creator host/version metadata, Unix permissions, DOS attributes, internal attributes, platform sidecar metadata, and Unix owner metadata. Loaded package part inventory entries receive the same metadata-only handoff fields for common review use.

The reader still exposes package bytes only through existing loaded DOCX part parsing. ZIP metadata fields preserve `docx-zip-entry-metadata-only` review semantics and do not enable raw byte exposure.

## Evidence

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 10022 assertions, 0 failures`

## Non-Overlap

This does not add new ZIP parser policy. It reuses existing native PHP `ZipPackage` preflights and wires them into DOCX ingestion provenance, distinct from earlier shared ZIP work for comments, data descriptors, extra fields, compression, name policy, platform attributes, creator hosts, permissions, and raw strict preflight.

Pandoc, office suites, TeX/PDF tools, browsers, Node, `zip`/`unzip`, external validators, online services, live provider tests, and live-service provider tests were not run.
