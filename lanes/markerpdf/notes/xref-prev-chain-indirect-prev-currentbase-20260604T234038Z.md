# markerPDF xref Prev chain indirect Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260604T234038Z`
Base: `12497e5fdb80be5eaa15ccf8ea2eee0aeb6b8e50`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and metadata through pdftext/PDFium-backed object loading before WordPress-facing conversion. The native PHP lane owns the equivalent parser boundary for xref `/Prev` chain selection, catalog metadata, Info dictionaries, page text, and EmbeddedFiles name trees without running Python, OCR, models, or external PDF tools.

PDF incremental updates can carry the previous xref offset as an indirect numeric helper. The text parser already had object-map resolution for some `/Prev` walks, but the lightweight metadata and embedded-file parsers still treated `/Prev` as a direct integer only.

## Behavior

`PdfMetadataExtractor` and `PdfEmbeddedFileExtractor` now resolve safe numeric `/Prev` operands through their existing object maps when walking xref tables/streams and when repairing damaged current update rows.

The focused fixture appends a current classic xref table whose `/Prev` value is `30 0 R`, where object `30` contains the previous xref byte offset. The current table also carries damaged explicit offsets for current same-generation catalog, page, XMP, Info, name-tree, Filespec, and EmbeddedFile objects. Before the patch, metadata extraction did not follow the indirect `/Prev` helper, so current metadata sources were empty. After the patch, WordPress import selects current XMP, current Info, current catalog language, current page text, and the current attachment while excluding stale previous-section metadata, attachment, and text.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs classic xref-table current update rows when Prev is an indirect numeric helper
Expected: array (
  0 => 'xmp',
  1 => 'info',
  2 => 'catalog',
)
Actual: array (
)

1 test files, 91 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
7 PASS cases
1 test files, 105 assertions, 0 failures
```

Adjacent xref/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 866 assertions, 0 failures
```

Metadata/attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1394 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-indirect-prev-currentbase.php
current_xmp_title_selected=true
current_info_title_selected=true
current_catalog_language_selected=true
current_page_text_selected=true
current_attachment_selected=true
indirect_prev_helper_used=true
stale_prev_metadata_excluded=true
stale_prev_attachment_excluded=true
stale_prev_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted xref-stream indirect `/Prev` compressed-helper parser slice, damaged latest xref-stream offsets, same-generation xref-stream/table offset repair with direct `/Prev`, hybrid `/XRefStm` free-entry precedence, object-stream carrier generation recovery, object-stream `/Prev` free-carrier repair, stream-filter operand owner boundaries, or live OCR/model work.

The bounded new behavior is specifically classic xref-table `/Prev` values stored as indirect numeric helpers in the metadata and embedded-file xref walkers, combined with current update row repair for damaged explicit offsets.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, safe indirect value resolution, xref table/stream `/Prev` chain walking, current update row repair, XMP/Info/catalog metadata extraction, EmbeddedFiles name-tree extraction, and WordPress smoke rendering. Full upstream parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
