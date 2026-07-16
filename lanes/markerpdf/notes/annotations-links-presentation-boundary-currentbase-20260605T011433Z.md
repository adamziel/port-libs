# Link Annotation Presentation Boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T011433Z`

Base accepted HEAD: `36037135286fcdbc8bac174ffee0996de01721a0`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The upstream searchable-PDF path delegates page text and geometry to pdftext/PDFium-style readers and does not execute PDF annotation actions during import.
- PDF Link annotations can carry presentation and review fields (`/H`, `/C`, `/BS`, `/Border`, `/Contents`, and `/T`) separately from the URI/destination action. Native no-GPU parity for WordPress import keeps those fields as review metadata, not visible paragraph text or executable action behavior.

## Implementation

- `PdfLinkAnnotationExtractor` now records Link annotation presentation metadata on promoted link rows:
  - `contents` from literal or hex PDF strings
  - `title` from `/T`
  - `border_color` from `/C`, including transparent, DeviceGray, DeviceRGB, DeviceCMYK, and DeviceN-style component review
  - `highlight_mode` and `highlight_mode_label` from `/H`
  - `border` from `/BS` dictionaries or fallback `/Border` arrays, including width, style, dash pattern, and corner radii
- `applyLinksToPages()` propagates those fields to matched supplied marker/pdftext spans under `link_annotation_*` keys.
- Hidden, invisible, and no-view Link annotations still do not promote URI, destination, or presentation metadata.

## Red First

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves Link annotation presentation metadata as review-only WordPress span context
Expected: 'Styled link border review'
Actual: NULL
1 test files, 6 assertions, 1 failures
PHP Warning: Undefined array key "contents" in lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php on line 62
```

## Focused Evidence

Syntax:

```text
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-presentation-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-link-presentation-boundary-currentbase.php
```

Focused assigned gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves Link annotation presentation metadata as review-only WordPress span context
1 test files, 54 assertions, 0 failures
```

Adjacent link/annotation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 797 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-presentation-boundary-currentbase.php
```

The smoke emits `link_count=2`, `first_highlight_mode_label=outline`, `first_border_style=dashed`, `first_border_color_hex=#3366cc`, `second_highlight_mode_label=none`, `second_border_style=none`, `second_border_color_space=transparent`, `hidden_presentation_promoted=false`, `visible_text_excludes_presentation_metadata=true`, and all execution/model/external-tool flags false.

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `1227 -> 1228 pass / 0 fail`.
- WordPress scenarios move `1202 -> 1203`.
- Mapped upstream denominator is unchanged; this deepens the already mapped annotation/link boundary.

## Non-Overlap

This does not repeat accepted generic URI/destination extraction, page-level `/Annots` ownership, escaped page `/Ann#6fts` names, escaped Link dictionary keys, exact-generation link operands, Widget link inheritance, hidden/no-view filtering, rotated/UserUnit link rectangles, Link `/QuadPoints`, text-markup `/QuadPoints`, annotation appearance/popup/sound review, StructParent action context, outline target context, or xref repair boundaries.

The bounded behavior is specifically preserving Link annotation presentation/review fields on promoted WordPress link spans while keeping hidden annotation payloads out of visible Gutenberg paragraph text.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-exact reference resolver, token-aware dictionary reader, array/string/name parsing helpers, supplied marker/pdftext span model, and Markdown span merge path. Full upstream Python/PDFium/model benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
