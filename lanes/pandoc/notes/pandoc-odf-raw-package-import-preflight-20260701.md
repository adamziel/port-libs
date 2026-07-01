# ODF/ODT Raw Package Import Preflight 2026-07-01

## Slice

`OpenDocumentPackage::rawImportPreflight()` now reports metadata-only ODF/ODT package import diagnostics before bounded `ZipPackage` construction succeeds.

The slice preserves:

- first local-header `mimetype` evidence for OpenDocument text packages;
- raw ZIP strict-import diagnostics from `ZipPackage::rawStrictImportPreflight()`;
- ZIP64 EOCD accounting, sentinel-field resolution, and locator/record issue codes;
- whether native ZIP package construction and OpenDocument package construction can proceed.

The raw preflight does not expose package payload bytes and does not invoke Pandoc, office tooling, `zip`, `unzip`, Node, browser tooling, or validators.

## Fixture

`OpenDocumentPackageRawImportPreflightTest.php` covers:

- an instantiable ODT package with first local-header mimetype evidence;
- the same ODT package rewritten with ZIP64 EOCD sentinel fields, ZIP64 EOCD record, and locator, where the bounded ZIP reader rejects construction but ODF-facing raw preflight still reports ODT mimetype evidence plus ZIP64 accounting diagnostics.

Direct-format parity accounting moves by 2 mapped ODF raw-package preflight cases and 38 PHP assertions in `UPSTREAM_TEST_MANIFEST.json`.
