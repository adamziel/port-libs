# markerPDF XMP role operand boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T090351Z`

Base accepted HEAD: `b3a6a457e189843a6281cb033c461a4ee4341587`

## Source Truth

Upstream `sddai/markerPDF` routes searchable-PDF metadata and page text through PDF parser/PDFium/pdftext boundaries before any OCR/model fallback. Under the current no-GPU markerPDF scope, native PHP must therefore enforce PDF Catalog `/Metadata` trust boundaries before WordPress document metadata is promoted.

This slice covers one bounded PDF syntax edge: Catalog `/Metadata` may reference a document XMP stream only when the stream dictionary role operands are unambiguous single PDF names. `/Type /Metadata` and `/Subtype /XML` may be direct or indirect, but a helper object such as `/XML /EmbeddedFile` or a direct `/Type /Metadata EmbeddedFile` carries a tailed operand and is not a single stream role name. The XMP payload is still summarized for review, but it must not become document-level metadata.

## Behavior

`PdfMetadataExtractor::metadataStreamDictionaryTypeBoundaryReview()` now rejects tailed `/Type` and `/Subtype` name operands with `rejected_tailed_metadata_stream_role_operand`. The review row records the affected role key, direct/indirect kind, helper object number when present, the accepted-looking first name, and the hidden trailing operand preview. Document metadata falls back to trailer `/Info`, XMP text values stay redacted, and visible WordPress paragraphs remain free of XMP payload text.

## Red-First Evidence

Before the source change, a scratch probe with:

- Catalog `/Metadata 5 0 R`
- Metadata stream dictionary `/Type /Metadata /Subtype 7 0 R`
- Helper object `7 0 obj /XML /EmbeddedFile endobj`

returned `source=["xmp","info"]` and promoted `Hidden Tailed Subtype XMP Title` as the document title. That was the unsafe boundary.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpRoleOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 45 assertions, 0 failures`.

Adjacent XMP stream-role family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpRoleOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMissingTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStreamDictionaryDuplicateTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpRawDuplicateTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStreamObjectBoundaryCurrentBaseTest.php`

Result: `6 test files, 376 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-role-operand-boundary-currentbase.php`

Result: exits 0 with `review_status="rejected_tailed_metadata_stream_role_operand"`, `info_fallback_title_selected=true`, `xmp_payload_redacted=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/non-stream handling, duplicate `/Metadata` entries, duplicate `/Type` or `/Subtype` keys, missing/non-name `/Type`, malformed packet begin/end boundaries, internal xpacket marker handling, unreadable stream filters, stream object tail rejection, XMP parsing, Info fallback parsing, encrypted metadata source policy, OCR/model execution, or external PDF tooling. The bounded behavior is only tailed single-name role operands for document XMP metadata stream dictionaries.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/value reader, indirect object resolver, XMP packet parser, metadata review summarizer, Info fallback path, and WordPress smoke renderer. Full upstream model parity remains outside the current no-GPU markerPDF scope: live OCR, Surya/Texify/Torch model execution, PDFium/PIL raster rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark runs were not launched.
