# XMP Entity Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T032755Z`

Accepted base: `b6d80ef86c77afda76f2318400f9167f2fb82004`

## Source truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF document metadata through native PDF/PDFium-style metadata extraction before later layout/OCR/model stages.
- Under the current no-GPU markerPDF scope, this slice owns the native PDF parser boundary for catalog `/Metadata` XMP streams. XMP metadata is review metadata for WordPress imports and must not execute general XML features or synthesize values from DTD/entity declarations.

## Red-first evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpEntityBoundaryCurrentBaseTest.php
```

Before the source change, the focused test failed because a typed catalog `/Metadata` XML stream with internal `<!ENTITY>` declarations was promoted as `["xmp","info"]` metadata. The entity-expanded title became the document title, and rejected XML stream review had no DTD/entity safety summary.

## Implementation

- `PdfMetadataExtractor::parseXmpPacket()` now skips XMP XML candidates containing token-level DTD or entity declarations before `DOMDocument::loadXML()` can expose entity-expanded `textContent`.
- `PdfMetadataExtractor::catalogMetadataStreamBoundaryReview()` now emits redacted review metadata for typed document XMP streams rejected for DTD/entity declarations:
  - `status=rejected_unsafe_document_xmp_stream`
  - `xmp_summary.status=rejected_dtd_or_entity_declaration`
  - `unsafe_markup=["DOCTYPE","ENTITY"]`
  - `payload_included=false`
- Existing packet-boundary behavior is preserved: safe current XMP roots after comment/DOCTYPE decoys still parse when the bounded root candidate excludes the decoy declaration.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpEntityBoundaryCurrentBaseTest.php
```

Result: `1 test files, 44 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php
```

Result: `1 test files, 42 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-xmp-entity-boundary-currentbase.php
```

Result: emitted `source=["info","catalog"]`, `xmp_promoted=false`, `metadata_review_status="rejected_unsafe_document_xmp_stream"`, `xmp_summary_status="rejected_dtd_or_entity_declaration"`, `unsafe_markup=["DOCTYPE","ENTITY"]`, `entity_title_excluded=true`, `entity_description_excluded=true`, `visible_text_excludes_xmp_entities=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted XMP packet padding, UTF-16 decoding, CDATA/comment root-token boundaries, qualified `rdf:value` extraction, nested qualifier exclusion, PieceInfo private metadata separation, XMP associated schema correlation, encrypted metadata source policy, current trailer/root xref selection, or outline metadata boundaries. The bounded behavior is specifically fail-closed DTD/entity declaration handling before XMP metadata promotion and rejected-stream review summaries.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, stream decoder, XML packet candidate bounding, XMP summary extractor, Info fallback metadata, and WordPress smoke renderer. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
