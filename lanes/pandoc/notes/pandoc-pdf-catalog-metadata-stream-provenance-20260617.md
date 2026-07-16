# Pandoc PDF Catalog Metadata Stream Provenance

- Bead: `plib-n0cto`
- Scope: PDF/Typst boundary provenance in `PdfEngineHandoff`; no non-PDF/non-Typst formats.
- Base: `origin/main` at `0e23183990`

## Slice

This slice preserves catalog-level `/Metadata` stream provenance as `pdfCatalogMetadata` and sequence-level `finalPdfCatalogMetadata`, separate from semantic `pdfXmpMetadata` and page-level `pdfPageMetadata`.

The recovered review surface records the catalog metadata stream source, object reference, packet byte count, packet SHA-256, bounded XMP labels, decoded `FlateDecode` provenance, and unsupported-filter skip diagnostics.

## Accounting

- `mappedPdfCatalogMetadataProvenanceCases`: `0 -> 1`
- `pdfCatalogMetadataProvenanceAssertions`: `0 -> 10`
- `phpPass`: `17001 -> 17002`
- Upstream manifest mapped cases: `16587 -> 16588`
- Root mapped inventory: `16556 -> 16557`
- Benchmark denominator mapped cases: `3725 -> 3726`

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Expected post-rebase coverage:

- Focused `PdfEngineHandoffTest.php`: 1 file, 2981 assertions, 0 failures.
- Full `lanes/pandoc/tests`: 258 files, 175370 assertions, 0 failures.
