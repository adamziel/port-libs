# markerPDF xref object-stream hybrid generation owner current-base

Micro-slice: `xref-object-stream-hybrid-generation-owner-currentbase`
Session: `port-dev-markerpdf-xref74-20260602T222228Z`
Base accepted HEAD: `5eb7c8f9b2d7a9a15b9d174ca06467c45dce2fca`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF page text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level object/xref/page parsing to `pdftext` and pypdfium/PDFium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

Relevant parser behavior is PDFium-style xref and object-stream ownership: start at `startxref`, preserve current hybrid xref table rows over same-revision companion `/XRefStm` conflicts, and expand type-2 compressed members from the selected object-stream carrier. Sources: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_parser.cpp> and <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

The focused fixture builds a PDF 1.5 hybrid xref section where:

- object stream `6 0` is a stale carrier with stale page text;
- object stream `6 1` is the current carrier with the live page member;
- the current hybrid xref table selects carrier `6 1`;
- the companion `/XRefStm` contributes the type-2 row for page object `4`.

Visible text extraction already emitted the current carrier page. The missing current-base signal was review metadata: `extractXrefObjectStreamIndexReview()` did not expose which carrier generation and xref row owned the type-2 member expansion.

`PdfTextExtractor` now reports per compressed-entry object-stream carrier owner fields:

- `object_stream_selected_generation`
- `object_stream_selected_offset`
- `object_stream_xref_entry_type`
- `object_stream_xref_generation`
- `object_stream_xref_offset`
- `object_stream_owner_policy`

For this hybrid fixture the owner policy is `xref_selected_object_stream_carrier`, selected carrier generation is `1`, and stale carrier-generation-zero text stays excluded from WordPress paragraphs.

## Evidence

Red-first run before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamHybridGenerationOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the hybrid xref table carrier generation owner before expanding companion object-stream members
Values are not identical
Expected: 1
Actual: NULL

1 test files, 17 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamHybridGenerationOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses the hybrid xref table carrier generation owner before expanding companion object-stream members

1 test files, 23 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamHybridGenerationOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefHybridGenerationRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridGenerationRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 183 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-hybrid-generation-owner-currentbase.php
```

The smoke emits `uses_current_hybrid_carrier_generation_page=true`, `reports_object_stream_selected_generation=true`, `reports_object_stream_owner_policy=true`, `excludes_stale_carrier_generation_zero_page=true`, `excludes_object_stream_payload_text=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for only the current text.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamHybridGenerationOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-hybrid-generation-owner-currentbase.php
```

All three reported no syntax errors.

Status delta: `phpPass` / `wordpressScenarios` move `907 -> 908`; mapped markerPDF semantics move `638 -> 639 / 78` with `pdfXrefObjectStreamHybridGenerationOwnerCurrentBase`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted hybrid table free-owner precedence, current hybrid table direct-generation precedence for the page object, generation-one page repair over a companion stale member, previous object-stream carrier replacement, previous free-entry carrier rebuilds, linearized hint exclusions, zero-width member-index recovery, object-stream header comment ownership, xref-stream owner cycles, or xref-stream `/Prev` generation recovery.

The bounded behavior here is specifically review-visible carrier generation ownership for a companion `/XRefStm` type-2 member whose object-stream carrier is selected by the current hybrid xref table at generation `1`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream parser, hybrid `/XRefStm` merger, stream decoder, object-stream expander, review payload path, page-tree walker, content-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
