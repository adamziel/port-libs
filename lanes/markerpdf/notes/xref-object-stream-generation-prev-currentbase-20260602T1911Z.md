# markerPDF xref object-stream generation Prev boundary

Micro-slice: `xref-object-stream-generation-prev-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates low-level parser selection to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates page text extraction to pypdfium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

PDFium object-stream parsing treats type-2 xref rows as object-stream carrier plus member index and parses the selected member from the carrier stream, making carrier generation ownership a parser boundary before markerPDF emits WordPress-visible text. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

This slice adds current-base regression coverage for an incremental PDF where:

- the previous xref stream selects object-stream carrier `6 0` as a direct object;
- the previous xref stream maps page object `4` as a type-2 member of that carrier;
- the current xref stream replaces only the carrier with generation `6 1`;
- the current page tree references a direct page plus stale object `4`.

`PdfTextExtractor` keeps the current direct page authoritative and does not allow the stale `/Prev` type-2 row to bind to the replacement generation carrier. WordPress paragraph extraction emits only:

- `Current carrier generation page`
- `Previous member generation skipped`

The previous compressed page, replacement carrier member, raw object-stream payload, Python workers, pdftext, pypdfium, model execution, raster execution, and external PDF tools remain excluded.

## Evidence

Focused current-base test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips Prev type-2 rows when the current section replaces the selected object-stream carrier generation

1 test files, 10 assertions, 0 failures
```

Adjacent xref/object-stream generation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 101 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-generation-prev-currentbase.php
uses_current_carrier_generation_page=true
skips_prev_type2_member_for_replaced_carrier_generation=true
excludes_replacement_generation_carrier_member=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Changed PHP lint:

```text
php -l lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-generation-prev-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-generation-prev-currentbase.php
```

Whitespace/status checks:

```text
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
passed
git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

This does not repeat previous type-2 rows whose carriers were never selected, carriers that were themselves compressed decoys, same-generation carrier replacement at another offset, current free-entry suppression of stale compressed members, hybrid xref table direct-generation precedence, omitted member-index repair, duplicate zero-width header rejection, or stream-owned xref/object boundaries.

The bounded behavior is specifically a stale `/Prev` type-2 row whose previous chain selected direct carrier generation `0`, while the latest xref section replaces that carrier with direct generation `1`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP direct-object scanner, xref-stream parser, `/Prev` merger, object-stream decoder, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
