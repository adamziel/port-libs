# markerPDF Outline Navigation EOF Metadata Boundary Current Base

## Source Truth

Upstream `marker/cleaners/toc.py` delegates PDF TOC extraction to the loaded PDF document via `doc.get_toc(max_depth=max_depth)` and returns `title`, `level`, and `page` rows. Source checked: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py

The native no-GPU boundary for this slice is the PDF parser/trailer boundary: outline/TOC metadata must come from the current document bytes through the EOF marker selected by the latest `startxref`. Object-like trailing bytes after that EOF are not current catalog `/Outlines` and must not create WordPress navigation rows or action-review metadata.

## Red Before Fix

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNavigationEofMetadataBoundaryCurrentBaseTest.php
```

Result before implementation:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the current EOF-bounded outline tree for WordPress navigation metadata
Expected: ['Current EOF Outline Start', 'Current EOF Action Target']
Actual: ['Stale EOF Outline Should Not Import']
FAIL keeps trailing stale outline operands out of visible WordPress text
Expected: ['Current EOF Outline Start', 'Current EOF Action Target']
Actual: ['Stale EOF Outline Should Not Import']

1 test files, 3 assertions, 2 failures
```

`PdfOutlineExtractor` parsed every later `obj` block and let a trailing stale catalog and outline tree after `%%EOF` replace the current TOC/navigation review metadata.

## Implementation

`PdfOutlineExtractor::parsedObjectValues()` now bounds direct-object scanning to:

- the first `%%EOF` after the latest `startxref`, when present;
- otherwise the last `%%EOF` for simple test PDFs without xref tables;
- otherwise the full byte string for malformed/minimal fixtures without EOF.

This preserves current incremental-update fixtures while excluding trailing stale outline, action, destination, and page-content objects that are not part of the current PDF revision.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNavigationEofMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutline*CurrentBaseTest.php
```

Result:

```text
24 test files, 1657 assertions, 0 failures
```

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-boundary-currentbase.php > /tmp/markerpdf-outline-eof-smoke.html
```

Result: passed. The smoke reports `navigation_titles=["Import Runbook","Collapsed Review Child","Media Appendix"]`, `toc_titles=["Import Runbook","Collapsed Review Child","Media Appendix"]`, `stale_navigation_excluded=true`, and `visible_text_excludes_outline_metadata=true`.

## Non-Overlap

This does not repeat escaped page `/Ann#6fts` annotation promotion, document-level metadata xref repair, outline color metadata, name-tree Limits, outline action-chain context, remote GoTo/GoToE review, page transition metadata, or structure-element outline metadata. The bounded behavior is specifically EOF-bounded object scanning for `PdfOutlineExtractor` TOC/navigation review so trailing stale outline objects after the current EOF cannot override current WordPress navigation metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF outline parser, action review extractor paths inside `PdfOutlineExtractor`, current text/metadata extractors, and existing WordPress smoke fixtures. No GPU/model/OCR/PDFium/PIL/external PDF tools were run. Full xref-stream object-stream parity for outline-only extraction remains a future native parser consolidation target, but this slice removes the current EOF/trailing-object boundary blocker without adding a dependency.
