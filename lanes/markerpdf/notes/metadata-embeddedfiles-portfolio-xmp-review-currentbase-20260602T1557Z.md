# markerPDF metadata/EmbeddedFiles Portfolio XMP review current-base slice

Session: `port-dev-markerpdf-meta26pdf-20260602T1557Z`
Base accepted HEAD: `47657692317361f6d3d564f3ae90eb5c7da42a7e`

## Source truth

Upstream Marker exposes rendered document `metadata` separately from Markdown/images and keeps conversion output structured for review. The PDF source-truth boundary for this slice is FileSpec-local `/Metadata` XMP streams and `/OutputIntents` dictionaries on `/Names /EmbeddedFiles` and catalog `/AF` attachments: these are attachment review metadata, not document-level XMP/PDF-A roots and not visible page text.

## Behavior

`PdfEmbeddedFileExtractor` now carries FileSpec-local `/Metadata` and `/OutputIntents` through ordinary Portfolio attachment rows:

- name-tree `/EmbeddedFiles` FileSpecs get `metadata_review` and `output_intents_review`;
- catalog-associated `/AF` FileSpecs get the same review dictionaries;
- nested XMP stream bytes and ICC profile bytes are represented by stream dictionaries only, so attachment-local metadata is not promoted to document title/PDF-A roots and does not leak into Gutenberg paragraph text;
- existing payload extraction, checksum, Portfolio `/Collection`, `/CI`, and `/PieceInfo` behavior is preserved.

## Evidence

Focused before/after:

- before accepted base: `PdfEmbeddedFileExtractorTest.php` had 13 behavior cases / 274 focused assertions;
- after this slice: `PdfEmbeddedFileExtractorTest.php` has 14 behavior cases / 311 focused assertions;
- delta: +1 behavior PASS case, +37 focused assertions.

Commands:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php` -> `1 test files, 311 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` -> `2 test files, 613 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-portfolio-xmp-review-currentbase.php` -> smoke emitted `attachment_count=2`, `metadata_review_types=[Metadata,Metadata]`, `outputintent_identifiers=[Attachment sRGB,Attachment sRGB]`, `attachment_xmp_not_promoted_to_document_title=true`, `attachment_outputintent_not_promoted_to_pdfa=true`, `xmp_stream_payload_omitted=true`, and `icc_stream_payload_omitted=true`
- `php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php && php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-portfolio-xmp-review-currentbase.php` -> no syntax errors
- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'` -> both JSON files valid
- `git diff --check -- lanes/markerpdf` -> passed

## Non-overlap

This does not repeat the accepted document-level catalog XMP/Info extraction, PDF/A root OutputIntent extraction, OutputIntent-associated FileSpec review, catalog PieceInfo private metadata boundary, or Portfolio `/Collection` field-value slices. It adds the missing ordinary `PdfEmbeddedFileExtractor` FileSpec-local `/Metadata` and `/OutputIntents` review rows for Portfolio/name-tree and catalog-associated attachments.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP PDF object/dictionary/array parser, stream dictionary review logic, and Flate/attachment handling already present under `lanes/markerpdf/src`.
