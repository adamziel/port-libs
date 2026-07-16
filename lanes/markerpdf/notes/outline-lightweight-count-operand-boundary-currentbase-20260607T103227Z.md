# Outline Lightweight Count Operand Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260607T103227Z`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives PDF outline/TOC metadata from parser dependencies before OCR, layout, and model stages. Native PHP markerPDF maps that no-GPU boundary through `PdfTextExtractor::extractOutlineMetadata()` for lightweight `pdf_toc` rows and through the richer outline metadata/navigation review extractors.

PDF outline `/Count` is a signed integer descendant-count field. A malformed decimal or tailed count operand should not be truncated into an authoritative zero that suppresses otherwise valid `/First`/`/Next` outline rows.

## Change

`PdfTextExtractor` now parses lightweight outline `/Count` with a strict single signed-integer helper instead of reusing stream-length parsing. Decimal root or item counts behave as absent for traversal, matching `PdfMetadataExtractor` and `PdfOutlineExtractor` behavior while preserving explicit integer `/Count 0` suppression.

The focused fixture covers:

- root `/Outlines /Count 0.5`, which previously suppressed all lightweight TOC rows;
- item `/Count 0.5`, which previously suppressed a valid child outline row.

Visible WordPress paragraph text remains page-content-only; outline titles and count operands stay review metadata.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightCountOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats malformed root Count decimals as absent in lightweight outline metadata
Expected: ['Malformed Root Count Chapter', 'Malformed Root Count Appendix']
Actual: []
FAIL treats malformed item Count decimals as absent before lightweight child traversal
Expected: ['Malformed Item Count Chapter', 'Malformed Item Count Child', 'Malformed Item Count Appendix']
Actual: ['Malformed Item Count Chapter', 'Malformed Item Count Appendix']
1 test files, 4 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightCountOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats malformed root Count decimals as absent in lightweight outline metadata
PASS treats malformed item Count decimals as absent before lightweight child traversal
1 test files, 21 assertions, 0 failures
```

Additional focused family, lint, smoke, and diff-check results are recorded in the final handoff.

## Non-Overlap

This does not repeat accepted outline `/Last`, `/Prev`, parent, missing-parent, root type, root count zero, item count zero, count mismatch review, duplicate traversal keys, title/titleless boundaries, metadata stream boundaries, trailer-root ownership, xref repair, named destinations, PageLabels, annotation/action review, attachments, fonts/CMaps, image filters, table/equation supplied boundaries, OCR, or model execution. The bounded behavior is only malformed decimal `/Count` parsing in the lightweight upstream-style `pdf_toc` metadata path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary parser, outline metadata extractors, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
