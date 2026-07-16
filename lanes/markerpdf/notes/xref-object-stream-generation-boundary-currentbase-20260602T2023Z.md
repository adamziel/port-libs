# markerPDF xref object-stream generation boundary current-base

Micro-slice: `xref-objectstream-generation-boundary-currentbase`
Session: `port-dev-markerpdf-xref45-20260602T2023Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()`, delegating low-level parser/object selection to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates page text extraction to pypdfium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

The parser dependency boundary is PDF object streams: PDFium validates `/Type /ObjStm`, `/N`, and `/First`, then parses member objects from the selected carrier by member offset. Object-stream members are generation-zero objects, so a page-tree reference such as `4 1 R` must not resolve to a compressed `4 0` member only because the object number matches. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

`PdfTextExtractor` now walks page-tree `/Kids` references with generation-aware indirect resolution. A type-2 xref-stream row still expands its selected `/ObjStm` member for generation-zero references, but a nonzero `/Kids` reference whose direct generation is absent no longer binds to that compressed generation-zero member.

`extractXrefObjectStreamIndexReview()` also exposes the boundary as review metadata:

- `compressed_member_generation=0`
- `selected_object_generation`
- `nonzero_referenced_generations`
- `generation_boundary_policy=compressed_generation_zero_not_selected_for_nonzero_reference`

The focused fixture builds a current xref stream where object `4` is a type-2 member of carrier `6`, while `/Pages /Kids` references `4 1 R` plus a valid current direct page. WordPress paragraph extraction emits only:

- `Current nonzero generation boundary page`
- `Compressed member generation zero skipped`

The stale compressed `4 0` page text, object-stream member notes, Python workers, pdftext, pypdfium, model execution, raster execution, and external PDF tools remain excluded.

## Red First

Before the page-tree fix, the focused test failed because `/Kids [4 1 R]` resolved by object number only:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps object-stream generation-zero members from satisfying nonzero page references
Expected: array (
  0 => 'Current nonzero generation boundary page',
  1 => 'Compressed member generation zero skipped',
)
Actual: array (
  0 => 'Stale generation zero compressed page',
  1 => 'Current nonzero generation boundary page',
  2 => 'Compressed member generation zero skipped',
)
```

## Evidence

Focused current-base test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps object-stream generation-zero members from satisfying nonzero page references

1 test files, 21 assertions, 0 failures
```

Adjacent xref/object-stream generation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 179 assertions, 0 failures
```

Page-tree regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 597 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-generation-boundary-currentbase.php
```

The smoke emits `uses_current_nonzero_generation_boundary_page=true`, `skips_generation_zero_compressed_member=true`, `generation_boundary_policy=compressed_generation_zero_not_selected_for_nonzero_reference`, `nonzero_referenced_generations=[1]`, `compressed_generation_zero_boundary_count=1`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-generation-boundary-currentbase.php
```

All reported no syntax errors.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted `/Prev` type-2 carrier replacement, same-offset sparse `/Index` generation-noise preservation, hybrid xref direct-generation repair, zero-width object-stream member-index recovery, duplicate zero-width member rejection, xref free-entry suppression, direct `/ObjStm` base preservation, direct `/XRef` owner-cycle rejection, or stream-owned `startxref`/xref-table rejection.

The bounded behavior is specifically page-tree generation resolution for `/Kids` references when the selected xref row for the same object number is a generation-zero object-stream member.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
