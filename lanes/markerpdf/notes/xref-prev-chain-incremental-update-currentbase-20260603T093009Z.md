# markerPDF xref Prev chain incremental update current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260603T093009Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text and metadata extraction through `marker/pdf/extract_text.py` into pdftext/PDFium-backed parsing. The native PHP lane therefore owns the parser dependency boundary where xref `/Prev` chains select current catalog, Info, metadata, and page objects before WordPress import.

Incremental PDF updates can store current trailer `/Root` and `/Info` references in the latest xref stream while earlier xref sections remain available through `/Prev`. If the latest in-use xref rows have damaged explicit offsets, current trailer generations are still the current-base objects when their direct generation bodies are present in the update.

## Behavior

`PdfMetadataExtractor` now repairs latest trailer-named `/Root` and `/Info` nonzero direct generation objects when the current xref chain selects in-use rows whose explicit offsets do not resolve. After that, it follows graph-referenced nonzero generation objects from the repaired catalog so current XMP, page-tree, content, and review metadata can be selected without falling back to stale `/Prev` metadata.

The focused fixture builds stale generation-zero catalog, page text, XMP, and Info metadata in a previous xref table, then appends generation-one replacements. The latest xref stream uses `/Prev`, `/Root 1 1 R`, and `/Info 6 1 R`, but gives damaged explicit offset `0` for the current generation-one rows. WordPress import now selects `Current Prev Chain XMP Title`, current Info fields, `en-US` catalog language, and current page paragraphs while excluding stale `/Prev` metadata and text.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs current metadata generation objects through damaged xref Prev chain offsets (lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'xmp',
  1 => 'info',
  2 => 'catalog',
)
Actual: array (
)

1 test files, 3 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs current metadata generation objects through damaged xref Prev chain offsets

1 test files, 13 assertions, 0 failures
```

Adjacent metadata/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 928 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
current_xmp_title_selected=true
current_info_title_selected=true
current_catalog_language_selected=true
current_page_text_selected=true
stale_prev_metadata_excluded=true
stale_prev_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted text-side current-base repair for invalid direct-generation offsets, xref-stream duplicate sparse `/Index` precedence, trailer `/Encrypt` and `/ID` current precedence, xref-stream `/Prev` generation metadata rows, hybrid `/XRefStm` free-entry precedence, object-stream carrier generation recovery, object-stream `/Prev` free-carrier repair, or stream-filter boundary work.

The bounded behavior here is specifically metadata-side current generation repair for latest trailer `/Root` and `/Info` references plus graph-referenced direct generation objects when the latest xref-stream `/Prev` chain has damaged explicit in-use offsets.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table and xref-stream `/Prev` chain merger, Flate stream decoder, XMP/Info/catalog metadata extraction, text extractor current-generation repair, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
