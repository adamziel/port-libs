# Classic xref vertical-tab boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T104405Z`

Base accepted HEAD: `53829eeb84ed0d66a52425c8a2b7d09e8158ea35`

## Source truth

markerPDF imports searchable PDF text, document metadata, and attachments through parser-backed PDF extraction before any OCR/model path. In the native no-GPU scope, classic xref rebuild must follow PDF lexical rules: PDF whitespace is NUL, tab, LF, FF, CR, and space. Vertical tab is not PDF whitespace and must not delimit an `xref` keyword or let a decoy table become the current WordPress import root.

## Behavior

The focused fixture appends a valid current classic xref table and then a later top-level `xref` pseudo-table whose keyword is followed by a vertical tab byte. The final `startxref` points at that pseudo-table. Before the fix, the native text extractor already rejected the decoy, but the duplicated metadata, EmbeddedFiles, and attachment parser helpers treated PHP `ctype_space()` as PDF whitespace and selected decoy XMP, Info, and attachment roots.

`PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now use the same strict PDF whitespace set already present in `PdfTextExtractor` and `PdfXrefFreeObjectMap`.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicVerticalTabBoundaryCurrentBaseTest.php
FAIL rejects vertical-tab delimited classic xref decoys before WordPress imports
Expected: 'Current Vertical-Tab XRef Title'
Actual: 'Vertical-Tab Decoy XRef Title'
1 test files, 9 assertions, 1 failures
```

Focused pass after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicVerticalTabBoundaryCurrentBaseTest.php
1 test files, 31 assertions, 0 failures
```

Adjacent classic-xref boundary subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicVerticalTabBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicFormFeedWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicCommentDelimiterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildPrivateTailEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
7 test files, 873 assertions, 0 failures
```

Full classic-xref current-base family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfXrefClassic*CurrentBaseTest.php' | sort)
25 test files, 1464 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-vertical-tab-boundary-currentbase.php
```

The smoke exits 0 and reports `uses_current_page_text=true`, `vertical_tab_xref_decoy_rejected=true`, `metadata_title_current=true`, `info_title_current=true`, `embedded_file_current=true`, `attachment_summary_current=true`, `free_row_current=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted NUL/form-feed PDF whitespace admission, comment-delimited xref keywords, commented startxref tokens, private-tail startxref rejection, post-EOF private-tail bounding, name-token `/xref` offsets, malformed row/header handling, partial trailing subsections, zero-count subsections, stream/composite/literal/hex decoys, missing-final startxref repair, free-object map whitespace rows, xref-stream/object-stream repair, hybrid xref behavior, metadata-only root selection, fonts/CMaps, image/filter metadata, annotations/forms/security preflight, OCR/model work, or supplied-boundary table/equation handoffs.

The bounded behavior is only rejecting vertical-tab-delimited classic xref pseudo-tables consistently across text, metadata, EmbeddedFiles, attachment summary, and free-row review paths.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref table parser, startxref rebuild selection, metadata extractor, text extractor, EmbeddedFiles extractor, attachment preflight summarizer, free-object map, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL rendering, Python workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
