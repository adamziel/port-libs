# markerPDF classic xref malformed startxref boundary current-base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T133653Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T133653Z`

Base accepted HEAD: `a8df5428bf451d79b18474c26da6e8142a8af837`

## Source truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains searchable PDF text from `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` uses PDFium text extraction. The PHP lane owns the native PDF parser boundary where xref-selected page, metadata, Info, and EmbeddedFiles objects are recovered before WordPress import.

Relevant parser behavior for this slice: a damaged producer can append a current classic xref table and trailer, then write a final top-level `startxref` keyword with a malformed non-numeric operand. The final token is still the current repair boundary; the parser should rebuild to the latest valid top-level classic xref table before that token instead of falling back to an earlier numeric `startxref` token and stale trailer root.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now scan for top-level `startxref` keywords first, then parse an optional signed integer operand. Missing or malformed operands keep offset `0` as the repair trigger while preserving the token byte offset as the rebuild boundary.

`PdfMetadataExtractor::bytesThroughCurrentEof()` now uses the same token-aware startxref entry before trimming, so current incremental bytes before a malformed final `startxref` are retained while unrelated post-EOF garbage without a selected token remains excluded.

## Evidence

Red-first failure before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php
FAIL repairs malformed classic startxref operands to the current table boundary before WordPress imports
Expected current text lines; actual stale earlier numeric startxref page.
1 test files, 3 assertions, 1 failures
```

Focused pass after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php
PASS repairs malformed classic startxref operands to the current table boundary before WordPress imports
1 test files, 30 assertions, 0 failures
```

Adjacent classic-xref family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicSignedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicGenerationOffsetBoundaryCurrentBaseTest.php
5 test files, 635 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-malformed-startxref-currentbase.php
```

Emits current paragraphs, `metadata_title=Current Malformed Startxref Import`, `embedded_file=current-malformed-startxref.xml`, `current_classic_xref_import_kept=true`, `stale_startxref_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted damaged out-of-file numeric `startxref`, signed negative `startxref`, stale pointer to an older valid table, post-EOF xref garbage, commented `xref`/`startxref`, literal/composite/name-contained tokens, name-offset repair, zero-count xref subsections, malformed xref rows, generation-offset repair, `/Prev` repair, hybrid/xref-stream repair, object-stream repair, metadata trailer-root selection, or EmbeddedFiles xref selection. The bounded behavior is only malformed or missing final `startxref` operands still acting as the current classic rebuild boundary.

## Dependency closure

No new support component is needed. This slice reuses the native direct-object scanner, classic xref table parser, token boundary scanner, trailer dictionary parser, metadata extractor, text extractor, EmbeddedFiles extractor, attachment preflight, and WordPress smoke path. Full upstream model/OCR parity remains intentionally out of no-GPU scope.
