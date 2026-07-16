# markerPDF classic xref zero-count subsection boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T114751Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T114751Z`

Base accepted HEAD: `4112854bae82101c28da47bd15d98bfeb76014d7`

## Source Truth

Upstream markerPDF delegates searchable-PDF parsing to parser-backed PDF text extraction before OCR/model fallback. In the current no-GPU native PHP scope, classic xref repair is the boundary for page text, catalog/XMP/Info metadata, EmbeddedFiles, and WordPress attachment preflight.

PDF classic xref subsections declare a first object number and an entry count. A subsection with count `0` contains no xref rows and must not be accepted as a rebuilt current xref table/trailer owner.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now reject zero-count classic xref subsections when parsing a table. If a zero-count subsection appears after already completed valid subsections, the completed rows are preserved, matching the existing malformed trailing-subsection boundary.

The focused fixture keeps a valid current classic xref table for page text, XMP/Info metadata, and an EmbeddedFiles source attachment. It appends a later decoy table with subsection header `20 0` and a trailer pointing at decoy page, metadata, and attachment objects. Before the source patch, the rebuild selected `Zero-count decoy xref page` and `Zero-Count XRef Decoy Title`. After the patch, WordPress import stays on `Current zero-count xref page`, `Current Zero-Count XRef Title`, and `current-zero-count-xref.xml`.

## Verification

Red-first focused check after adding the test before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects zero-count classic xref subsections during rebuild before WordPress imports
1 test files, 3 assertions, 1 failures
```

Focused check after the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects zero-count classic xref subsections during rebuild before WordPress imports
1 test files, 29 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-zero-count-subsection-currentbase.php
```

Result: emitted Gutenberg paragraphs for `Current zero-count xref page` and `Zero-count table rejected`; smoke metadata reported `rejects_zero_count_subsection=true`, `current_classic_xref_import_kept=true`, `decoy_import_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Additional adjacent xref/parser family check:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Xref|Parser).*Test\.php|PdfTextExtractorTest\.php|PdfMetadataExtractorTest\.php|PdfEmbeddedFileExtractorTest\.php|PdfAttachmentExtractorTest\.php' | sort)
Focused test run: 110 selected test files (root lock skipped)
110 test files, 6236 assertions, 1 failures
```

Follow-up: `PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php` still leaks `Unlisted replacement object stream leak` from an unselected replacement `/ObjStm` carrier. That object-stream carrier ownership bug is outside this classic zero-count subsection slice; the focused classic rebuild family remains green.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged `startxref` repair, stale valid `startxref` repair, EOF-bounded trailing xref garbage, comment/commented-startxref decoys, array/composite/literal/name-contained xref decoys, row-state punctuation rejection, malformed missing-row rejection, malformed trailing-subsection preservation, stream-owned trailers, forward `/Prev` repair, xref-stream, hybrid, object-stream, free-entry, linearized hint, or generation repair behavior.

The bounded new behavior is only zero-count classic xref subsection rejection during damaged `startxref` rebuild across text, metadata, EmbeddedFiles, and attachment preflight import paths.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref parser, trailer/root selector, page text extractor, XMP/Info metadata extractor, EmbeddedFiles extractor, attachment preflight path, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
