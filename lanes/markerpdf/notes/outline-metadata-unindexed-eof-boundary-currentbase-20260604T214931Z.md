# markerPDF outline metadata unindexed EOF boundary

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260604T214931Z`
Base accepted HEAD: `42dddc08604dab6783842b91ae410655f23b3754`

## Source Truth

- Upstream `marker.cleaners.toc.get_pdf_toc()` delegates outline/bookmark rows to the PDF engine via `doc.get_toc(max_depth=...)`, then returns `title`, `level`, and `page` metadata: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py`.
- Upstream `marker.pdf.extract_text.get_text_blocks()` keeps that TOC metadata separate from `pdftext.extraction.dictionary_output(...)` page text blocks: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.
- Native PHP therefore needs the same boundary: catalog `/Outlines` may be repaired for review metadata, but outline/title operands and stale trailing objects must not become visible WordPress page text or override the selected PDF revision.

## Implemented Boundary

`PdfMetadataExtractor::extractDocumentMetadata()` now truncates the parsed byte range to the `%%EOF` paired with the latest `startxref` before building the document metadata object table.

The focused fixture keeps the current catalog, Info dictionary, page tree, and text stream in a classic xref table, while the current `/Outlines` subtree is deliberately omitted from the damaged xref table but present before the selected EOF. A stale duplicate outline root and item are appended after `%%EOF`.

Before the fix, the metadata repair path used the latest direct definition for xref-omitted object `40`, so the stale post-EOF outline item object `42` replaced the current pre-EOF item object `41`. After the fix, repair still finds the current pre-EOF unindexed outline subtree and excludes the stale appended duplicate.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUnindexedEofBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses pre-eof unindexed outline objects before stale post-eof duplicates in metadata (lanes/markerpdf/tests/PdfOutlineMetadataUnindexedEofBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 41
Actual: 42

1 test files, 7 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUnindexedEofBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses pre-eof unindexed outline objects before stale post-eof duplicates in metadata

1 test files, 24 assertions, 0 failures
```

Metadata/outline adjacent gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUnindexedEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNavigationEofMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 996 assertions, 0 failures
```

Outline-family gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUnindexedEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNavigationEofMetadataBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
...
5 test files, 206 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-unindexed-eof-boundary-currentbase.php
```

The smoke emits `outline_titles=["Current WordPress Outline Chapter"]`, `stale_post_eof_outline_excluded=true`, `visible_text_excludes_outline_metadata=true`, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat the accepted named-destination EOF boundary, outline `/Last` terminal traversal, outline parent-boundary traversal, xref-selected current catalog metadata, or EOF-bounded navigation traversal. The new behavior is specifically damaged-xref repair for catalog outline metadata when the current unindexed outline subtree appears before the selected EOF and stale duplicate outline objects appear after it.

## Dependency Closure

No new support component is needed. This reuses the native PHP metadata extractor, direct object scanner, xref/trailer reader, outline metadata summarizer, page text extractor, and WordPress smoke path. Full upstream markerPDF parity remains intentionally gated by the no-GPU scope: live OCR, Surya/Texify/Torch execution, PDFium rendering/model benchmarks, Streamlit/FastAPI model workers, and exact upstream model-runner parity were not run.
