# markerPDF Link Annotation Escaped Dictionary Boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260604T142655Z`

Accepted base: `911a9092c8f2b1ae83f3675566b637bee6b26f04`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The upstream searchable-PDF path delegates page text/geometry to pdftext/PDFium-style readers and does not execute PDF annotation actions during import.
- PDF names can encode bytes with `#xx` escapes, so a Link annotation dictionary can spell `/Subtype`, `/Rect`, `/A`, and `/F` as `/Sub#74ype`, `/Re#63t`, `/#41`, and `/#46`.
- Link promotion must decode those current top-level annotation keys while keeping hidden/invisible/no-view links out of WordPress spans.

## Implementation

- `PdfLinkAnnotationExtractor` now reads annotation dictionary values through a token-aware top-level dictionary scanner that decodes PDF name escapes.
- The scanner skips complete array/dictionary/string/name values before checking the next top-level key, so nested action dictionaries do not become the annotation-level dictionary owner.
- Link subtype, rectangle, and flags now resolve through the decoded scanner; escaped `/A` action keys continue to be handled by the structured `PdfActionReviewExtractor`.
- `PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php` covers a visible escaped-key Link and a hidden escaped-key decoy that appears first in `/Annots`.
- `wordpress-pdf-link-annotation-escaped-dictionary-currentbase.php` renders the visible WordPress link and proves the hidden stale URI is excluded.

## Red First

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes escaped Link annotation keys before WordPress span promotion
Expected: 1
Actual: 0
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes escaped Link annotation keys before WordPress span promotion
1 test files, 20 assertions, 0 failures
```

Adjacent annotation/link gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
7 test files, 591 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-escaped-dictionary-currentbase.php
```

The smoke emitted `link_annotation_object=7`, `link_uri=https://example.com/escaped-annotation-keys`, `hidden_escaped_flag_excluded=true`, `escaped_subtype_resolved=true`, `escaped_rect_resolved=true`, and all execution/model/external-tool flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `1062 -> 1063 pass / 0 fail`.
- WordPress scenarios move `1062 -> 1063`.
- Mapped upstream denominator is unchanged; this is a deeper native PDF annotation dictionary name-decoding boundary under the already mapped annotation/link behavior.

## Non-Overlap

This does not repeat accepted page-level `/Annots` top-level ownership, escaped page `/Ann#6fts` lookup, generic Link URI extraction, widget-link promotion, rotated/UserUnit link rectangles, text-markup QuadPoints, annotation action review, or annotation appearance/popup/sound review. The new behavior is specifically decoding escaped top-level keys inside each current Link annotation dictionary before WordPress link promotion and hidden-flag exclusion.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, annotation traversal, action review parser, bbox intersection, supplied marker/pdftext span model, and Markdown span merge. Full live OCR/model/PDFium/PIL/Torch parity remains out of scope under the current no-GPU markerPDF directive and was not run.
