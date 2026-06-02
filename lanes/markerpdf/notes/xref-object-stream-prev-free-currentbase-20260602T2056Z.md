# markerPDF xref object-stream Prev free current-base

Micro-slice: `xref-object-stream-prev-free-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level object/xref/page parsing to `pdftext` and pypdfium/PDFium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

PDF 1.5 object streams are direct stream objects, while xref-stream type-2 rows point compressed member objects at that direct carrier. In an incremental `/Prev` chain, a stale previous free row for the carrier object must not suppress a current direct `/ObjStm` base when the latest xref stream has type-2 rows that depend on that carrier.

## Behavior

The red probe built a current xref stream that selects page object `4` as member `0` of current object stream `6`, while the previous xref stream marks carrier `6` free. Before the patch, `PdfTextExtractor` inherited the previous free row, dropped the current carrier, failed to expand page `4`, and fell back to the stale previous page text.

The native parser now skips that previous free carrier row only when:

- the latest xref section has a type-2 row naming the carrier;
- the carrier has a direct `/Type /ObjStm` definition in the current byte range between `/Prev` and the latest xref offset;
- the previous row is type 0 free.

WordPress paragraph extraction now emits only:

- `Current object-stream base page`
- `Prev free carrier ignored`

The stale previous page and null bytes are excluded without Python, pdftext, pypdfium, models, raster engines, or external PDF tools.

## Evidence

Red-first focused run before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps a current object-stream base when Prev marks that carrier free
Expected: Current object-stream base page / Prev free carrier ignored
Actual: Stale Prev free carrier page
```

Final focused xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 127 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-prev-free-currentbase.php
uses_current_object_stream_base_page=true
ignores_prev_free_carrier_row=true
excludes_stale_prev_free_carrier_page=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax and lane checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-prev-free-currentbase.php
php -r 'json validate lane manifest/status'
git diff --check -- lanes/markerpdf
All passed.
```

Status delta: `phpPass` and `wordpressScenarios` move `811 -> 812`; mapped current-base semantics move `570 -> 571 / 78`.

## Non-Overlap

This does not repeat accepted latest xref free rows suppressing previous type-2 members, hybrid table free-entry precedence over companion `/XRefStm`, previous type-2 carrier replacement, previous compressed rows whose carrier was never selected, xref-stream `/Prev` generation repair, type-2 member-index repair, zero-width member-index recovery, or malformed type-2 rows that target the `/ObjStm` carrier itself.

The new behavior is specifically a stale previous type-0 free row for an object-stream carrier that the latest current xref-stream type-2 row needs as its direct current-base `/ObjStm`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, startxref `/Prev` chain parser, xref-stream decoder, object-stream expander, page-tree walker, content-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
