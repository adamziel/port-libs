# markerpdf-xmp-metadata-boundary-current-base-20260607T132906Z

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF text extraction and document metadata ahead of model-only OCR/layout work; this no-GPU slice stays on the native searchable-PDF parser boundary.
- PDF dictionary duplicate keys have undefined value selection in Adobe/PDF reference behavior. Catalog `/Metadata` stream role keys are therefore a trust boundary: duplicate `/Type` or `/Subtype` keys must not be resolved by last-key-wins promotion.

## Behavior

`PdfMetadataExtractor::metadataStreamDictionaryTypeBoundaryReview()` now counts all top-level `/Type` and `/Subtype` entries, not just name-valued entries, before deciding whether a catalog metadata stream can be promoted as document XMP.

This closes a boundary where:

- `/Type (EmbeddedFile literal decoy) /Type /Metadata`;
- `/Subtype (text/xml literal decoy) /Subtype /XML`;
- a valid XMP packet in the stream payload

previously promoted document XMP because the duplicate detector only saw the final name values. The stream is now recorded as `rejected_duplicate_metadata_stream_type_keys`, the XMP packet is summarized with redacted field names and date counts, Info metadata remains the WordPress title fallback, and XMP payload text stays out of visible paragraphs.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpRawDuplicateTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects raw duplicate Type and Subtype keys before XMP promotion
Expected: ['info', 'catalog']
Actual: ['xmp', 'info']
1 test files, 1 assertions, 1 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpRawDuplicateTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects raw duplicate Type and Subtype keys before XMP promotion
1 test files, 29 assertions, 0 failures
```

XMP metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
Focused test run: 51 selected test files (root lock skipped)
51 test files, 3173 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-raw-duplicate-type-boundary-currentbase.php
```

The smoke exits 0 and emits `duplicate_stream_role_rejected=true`, `duplicate_keys=["Type","Subtype"]`, `info_fallback_preserved=true`, `xmp_payload_redacted=true`, `summary_redacts_text_values=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted XMP packet begin/end, UTF-16, simple text subject, compact RDF attributes, XML namespace, empty-root, duplicate name-valued `/Type`/`/Subtype`, malformed catalog `/Metadata` operand, rejected non-metadata XML stream, metadata filter/DecodeParms operand, encrypted metadata, XMP resource reference, Dublin Core review, media-management, PDF/A schema, or PageLabels slices. The bounded behavior is specifically raw duplicate stream role keys whose duplicate values include non-name PDF tokens.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, stream decoder, XMP packet parser, metadata review summary path, text extractor, and WordPress smoke pattern. Full live OCR, Surya/Texify/Torch execution, PDFium raster rendering, password decryption, and external PDF tools remain intentionally out of scope for markerPDF under the current no-GPU direction.
