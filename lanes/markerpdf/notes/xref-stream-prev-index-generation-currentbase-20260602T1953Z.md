# markerPDF xref-stream Prev Index generation current-base

Micro-slice: `xref-stream-prev-index-generation-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates low-level parser selection to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates page text extraction to pypdfium. That makes xref-stream `/Prev`, sparse `/Index`, object-stream carrier ownership, and generation repair native parser boundaries before WordPress-visible text import.

PDF xref-stream type-2 entries name an object-stream carrier plus a member index. Previous type-2 rows can be replayed only when their carrier storage is still selected by the current xref chain. Existing native behavior already treats exact byte offsets as authoritative before generation fallbacks for direct xref rows, so the same storage comparison must honor matching explicit offsets before comparing noisy generation fields.

## Behavior

`PdfTextExtractor::xrefEntriesSelectSameStorage()` now treats two direct xref rows with matching explicit byte offsets as the same selected storage before comparing generation fields. This keeps accepted generation repair semantics consistent when a latest xref stream repeats an object-stream carrier offset through a sparse `/Index` row but carries a malformed or noisy generation byte.

The focused fixture builds:

- a previous xref stream with page object `4` as a type-2 member of carrier `6 0`;
- a current xref stream with `/Prev`, sparse `/Index [1 2 6 1 8 2]`, and `/W [1 4 1]`;
- a current carrier row for object `6` that points at the same explicit offset as `6 0`, but carries generation `1`;
- a current page tree that references a current direct page and previous compressed page `4 0`.

Before the fix, the previous compressed page was dropped because the carrier storage comparison rejected the generation mismatch. After the fix, WordPress paragraph extraction emits the current page text and the preserved previous compressed page text without running Python, models, raster engines, PDF actions, decryption, signature validation, or external PDF tools.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves Prev type-2 rows when current sparse Index carrier row keeps the same offset despite generation noise

1 test files, 8 assertions, 0 failures
```

Adjacent xref/object-stream generation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 80 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-prev-index-generation-currentbase.php
emits current sparse Index page, Same carrier offset preserved, Prev compressed same-offset generation page, page_count=2, executes_python_or_models=false, executes_external_pdf_tools=false
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-prev-index-generation-currentbase.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted xref-stream `/Prev` exact-offset generation repair, duplicate current `/Index` first-row preservation, invalid explicit xref-stream offset rejection, hybrid xref table direct/free precedence, current carrier replacement by a different generation and offset, previous type-2 rows whose carriers were absent or compressed decoys, object-stream omitted member-index repair, duplicate zero-width member guards, latest trailer `/Root` generation recovery, or stream-owned xref/object boundary rejection.

The bounded behavior is specifically same-offset object-stream carrier preservation through a current sparse xref-stream `/Index` row with explicit generation noise.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream parser, `/Prev` chain merger, object-stream decoder, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
