# markerPDF parser xref-stream object owner cycle current base

Micro-slice: `parser-xref-stream-object-owner-cycle-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes structured page text through `marker/pdf/extract_text.py::get_text_blocks()` using `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` delegates page text to pypdfium/PDFium. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

The relevant dependency behavior is PDF parser object-stream ownership: object streams validate `/Type /ObjStm`, `/N`, and `/First`, then parse member objects from that selected carrier. A cross-reference stream selected by `startxref` is itself a file-level indirect stream owner, not a compressed member of another stream. Source: https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp

## Behavior

`PdfTextExtractor::liveDirectObjectDefinition()` now preserves direct `/Type /XRef` stream definitions when a malformed decoded xref row claims that same xref-stream object is type 2 in an object stream. This mirrors the existing direct `/ObjStm` base preservation but applies only to direct xref-stream owners.

`extractXrefObjectStreamIndexReview()` now exposes the boundary with:

- `direct_xref_stream_owner_cycle_count`
- per-entry `direct_xref_stream_owner`
- per-entry `owner_cycle_rejected`
- per-entry `owner_policy=direct_xref_stream_owner_preserved`

The focused fixture builds a current PDF where `startxref` points to direct object `20 0` with `/Type /XRef`, while that xref stream's own decoded rows claim object `20` is a type-2 member of object stream `6`. Object stream `6` contains a fake compressed xref-stream member with text-looking payload. Native extraction keeps current page text, reports the owner cycle as review metadata, and excludes the compressed xref payload from WordPress paragraphs.

## Evidence

Red-first current base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves direct xref stream owners when decoded rows form a compressed owner cycle (lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: NULL

1 test files, 10 assertions, 1 failures
```

Focused green after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves direct xref stream owners when decoded rows form a compressed owner cycle

1 test files, 16 assertions, 0 failures
```

Adjacent parser/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 691 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-object-owner-cycle-currentbase.php
```

The smoke emits `uses_current_xref_owner_cycle_page=true`, `preserves_direct_xref_stream_owner=true`, `rejects_compressed_xref_owner_cycle=true`, `excluded_compressed_xref_owner_cycle_leak=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then two Gutenberg paragraphs for the current page text.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat stream-owned fake xref-stream object headers, stream-owned `startxref` tokens, stream-owned xref table offsets, direct `/ObjStm` base preservation for malformed type-2 rows, explicit member-index repair, zero-width duplicate member rejection, `/Prev` stale carrier generation suppression, xref-stream filter DecodeParms, or repaired object-stream filter operands.

The bounded behavior is specifically a decoded xref-stream row that forms a compressed ownership cycle for the direct xref-stream object selected by `startxref`.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, startxref/xref-stream parser, object-stream decoder, page-tree walker, review metadata surface, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
