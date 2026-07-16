# markerPDF hybrid XRefStm whitespace object-stream boundary

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260606T061537Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates low-level PDF parsing to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates to pypdfium/PDFium. This native PHP lane must therefore carry equivalent parser behavior for xref tables, xref streams, and object streams when no GPU/model path is available.

The PDF hybrid-reference form lets a classic xref table trailer carry `/XRefStm` for a companion xref stream. Existing native lookup already normalized offsets that point to ordinary PDF whitespace immediately before an xref-stream object, but hybrid table callers were not passing the PDF bytes into that lookup.

## Behavior

`PdfTextExtractor::xrefStreamEntriesAtOffset()` now accepts the optional PDF byte string and forwards it to `xrefStreamSectionAtOffset()`. All hybrid `/XRefStm` callers pass the current PDF bytes, so a trailer offset that lands on whitespace just before `20 0 obj` still resolves the companion xref stream. This lets type-2 rows in the companion xref stream select object-stream members for the catalog, page tree, and page object before page metadata and text extraction run.

The focused fixture stores objects `1`, `2`, and `4` in object stream `6 0`. The classic table exposes only direct rows for the font/content/object-stream carrier and has `/XRefStm` pointing to whitespace before the xref-stream object. Before the fix, the page graph was not recovered from the compressed rows. After the fix, the native parser extracts:

- `Current whitespace XRefStm object-stream page`
- `Hybrid xref stream rows parsed`

and reports one page, three compressed entries, explicit member indexes, and the selected object-stream carrier policy without Python, models, OCR, raster rendering, or external PDF tools.

## Evidence

Red focused run before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridXrefStmWhitespaceObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes hybrid XRefStm whitespace offsets before object-stream member extraction
Expected: 1
Actual: 0

1 test files, 5 assertions, 1 failures
```

Focused green after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridXrefStmWhitespaceObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes hybrid XRefStm whitespace offsets before object-stream member extraction

1 test files, 18 assertions, 0 failures
```

Adjacent xref/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridXrefStmWhitespaceObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridPrevTrailerSizeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridGenerationRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamHybridGenerationOwnerCurrentBaseTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 198 assertions, 0 failures
```

WordPress smoke marker values:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-xrefstm-whitespace-object-stream-currentbase.php
uses_whitespace_normalized_xrefstm_offset=true
compressed_entry_count=3
page_member_selection_policy=explicit_member_index
page_member_owner_policy=xref_selected_object_stream_carrier
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefHybridXrefStmWhitespaceObjectStreamCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefHybridXrefStmWhitespaceObjectStreamCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-xrefstm-whitespace-object-stream-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-xrefstm-whitespace-object-stream-currentbase.php
```

Lane status JSON and whitespace checks:

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok
git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

This does not repeat accepted zero-width carrier repair, explicit-zero object-stream indexes, missing or negative `/W` and `/Index` repair, duplicate xref-stream rows, `/Prev` generation selection, stale or damaged classic offsets, same-generation damaged offsets, hybrid owner replacement, object-stream carrier repair, inline image filter/tokenizer boundaries, font/CMap behavior, metadata, attachments, annotations, forms, or encrypted security preflight.

The bounded behavior is only hybrid classic-table `/XRefStm` lookup when the trailer offset points to normal PDF whitespace immediately before the xref-stream object and that companion stream supplies type-2 object-stream rows needed for current page extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP xref table parser, xref stream decoder, object stream expander, page tree walker, content stream extractor, and WordPress smoke path. Full live OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, exact upstream model benchmark parity, and external PDF rendering remain intentionally out of scope under the current no-GPU markerPDF direction.
