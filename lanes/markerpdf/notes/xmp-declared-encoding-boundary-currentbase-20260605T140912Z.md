# markerpdf-xmp-metadata-boundary-current-base-20260605T140912Z

## Scope

- Lane: `markerpdf`
- Base accepted HEAD: `52394894fe770269b8e2ae4edf4a1b9535bc8e02`
- Behavior cluster: native no-GPU XMP metadata parsing for declared non-UTF XML encodings inside active xpacket bodies.

## Source Truth

- Upstream markerPDF keeps document conversion output separate from metadata/review artifacts; searchable PDF text comes from PDF text extraction while document metadata is a separate output concern.
- PDF catalog `/Metadata` can point at a `/Type /Metadata /Subtype /XML` stream containing an XMP packet.
- XMP packets may wrap the XML root with `<?xpacket begin ...?>` / `<?xpacket end ...?>`. When the active packet body contains an XML declaration such as `encoding="Windows-1252"`, that declaration should drive decoding before any undeclared legacy encoding fallback is used.

## Patch

- `PdfMetadataExtractor::xmpXmlCandidates()` now inspects complete active xpacket bodies for non-UTF XML declarations before the generic raw/legacy fallback path.
- Added `addXmpPacketXmlCandidate()` so decoded packet-body candidates and their bounded XML roots keep `packet_boundary_applied=true` while preserving `encoding_fallback=false`.
- Added focused accepted/rejected metadata-stream tests for Windows-1252 XMP packets with high-bit text.
- Added a WordPress smoke proving decoded title/description/author metadata, packet-boundary selection, trailing packet exclusion, visible-text isolation, and no Python/model/external PDF tooling.

## Evidence

Red-first focused run before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDeclaredEncodingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL honors declared non UTF XMP encoding inside active packet before fallback decoding
Expected: false
Actual: true
FAIL summarizes rejected declared non UTF XMP packets without marking fallback decoding
Expected: false
Actual: true
1 test files, 33 assertions, 2 failures
```

Focused run after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDeclaredEncodingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS honors declared non UTF XMP encoding inside active packet before fallback decoding
PASS summarizes rejected declared non UTF XMP packets without marking fallback decoding
1 test files, 46 assertions, 0 failures
```

Adjacent XMP metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
Focused test run: 27 selected test files (root lock skipped)
27 test files, 1981 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-declared-encoding-boundary-currentbase.php
```

Emits `packet_encoding="Windows-1252"`, `decoded_to_utf8=true`, `encoding_fallback_used=false`, `packet_boundary_applied=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted trailing-packet padding, xpacket begin/end priority, complete empty packet fallback, compact RDF attribute parsing, simple text subject splitting, UTF-16 XMP sniffing, BOM handling, undeclared Windows-1252/ISO-8859-1 fallback, DTD/entity rejection, namespace-root skipping, nested qualifier handling, lang-alt selection, metadata stream-object tail validation, FileSpec/PieceInfo XMP review, or encrypted metadata source-priority slices. The new behavior is only declared non-UTF XML decoding inside the active xpacket body before fallback decoding.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet boundary scanner, DOM-based XMP extractor, text extractor, and WordPress smoke path. Full OCR/model execution, Surya/Texify/Torch, PDFium rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.

## Root Harness

Not run - isolated micro-slice.
