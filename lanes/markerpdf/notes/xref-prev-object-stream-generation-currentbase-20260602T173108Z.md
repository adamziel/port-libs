# markerPDF xref Prev object-stream generation boundary

Micro-slice: `xref-prev-object-stream-generation-currentbase-20260602T173108Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates parser details to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates page text extraction to pypdfium. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDFium object-stream parsing validates `/Type /ObjStm`, reads `/N` and `/First`, then parses member objects by object-stream offsets. That makes object-stream carrier ownership and generation selection a parser/dependency boundary for this native PHP lane. Source: https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp

## Behavior

`PdfTextExtractor::previousCompressedEntryUsesUpdatedObjectStream()` now treats a previous type-2 compressed-object row as unsafe when that row's object-stream carrier was not selected as a direct object in the previous xref chain. This closes a generation reuse gap where a stale `/Prev` row for page object `4` targeted carrier object `6`, but previous object `6` itself was only a compressed decoy while the latest revision contained an unselected direct generation-1 `/ObjStm` with the same object number.

Before the fix, the stale previous member bound to the newer generation-1 replacement carrier and leaked `Replacement generation object stream leak` into WordPress paragraphs. After the fix, only the current page text is emitted:

- `Current object-stream generation guard`
- `Reused carrier generation ignored`

The stale previous compressed page, compressed carrier decoy, and replacement generation member stay excluded without Python, pdftext, pypdfium, model execution, raster execution, or external PDF tools.

## Evidence

Red focused run before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips Prev type-2 rows when the object-stream carrier is only a compressed previous-generation decoy
Actual: array (
  0 => 'Current object-stream generation guard',
  1 => 'Reused carrier generation ignored',
  2 => 'Replacement generation object stream leak',
)

1 test files, 1 assertions, 1 failures
```

Focused green after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips Prev type-2 rows when the object-stream carrier is only a compressed previous-generation decoy

1 test files, 11 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 100 assertions, 0 failures
```

Main extractor regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 597 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-object-stream-generation-currentbase.php
uses_current_object_stream_generation_guard=true
skips_compressed_prev_carrier_decoy=true
excludes_previous_compressed_generation_page=true
excludes_replacement_generation_object_stream=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-object-stream-generation-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-prev-object-stream-generation-currentbase.php
```

Lane status JSON and whitespace checks:

```text
jq . lanes/markerpdf/lane-status.json >/dev/null
passed
git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

This does not repeat accepted xref-stream `/Prev` exact-offset generation repair, current hybrid table direct-generation precedence over companion `/XRefStm`, latest xref-stream free rows suppressing stale direct or compressed `/Prev` objects, previous type-2 rows whose carriers were absent, current object-stream owner replacement over stale `/Prev` hybrid rows, type-2 direct object-stream base preservation, omitted member-index repair, duplicate `/Index` rows, or unselected object-stream fallback suppression.

The bounded behavior is specifically a stale previous type-2 row whose referenced object-stream carrier object number is present in the previous chain only as another compressed decoy, while the latest revision reuses that carrier object number as a nonzero-generation direct `/ObjStm`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref stream parser, `/Prev` merger, object-stream decoder, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
