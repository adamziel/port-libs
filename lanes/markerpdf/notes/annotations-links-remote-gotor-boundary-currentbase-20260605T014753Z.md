# Link Annotation Remote GoToR Boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T014753Z`

Base accepted HEAD: `db339842c41e2a9af27401973ff2846244bb34f6`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The upstream searchable-PDF path delegates page text and geometry to pdftext/PDFium-style readers and does not execute PDF annotation actions during import.
- PDF Link annotations can use `/A << /S /GoToR ... >>` to target a remote PDF file. The remote page number and view operands describe the remote document, not the current WordPress document page.

## Implementation

- `PdfActionReviewExtractor` now preserves explicit remote GoToR destination view operands, including `/FitH` top values and normalized view parameters, instead of collapsing remote actions to file/page-only review rows.
- `PdfLinkAnnotationExtractor::applyLinksToPages()` now maps primary remote GoToR metadata onto matched supplied spans as `link_remote_*` fields:
  - `link_remote_file`
  - `link_remote_destination`
  - `link_remote_destination_page`
  - `link_remote_view_mode`
  - `link_remote_view_position`
  - `link_remote_view_parameters`
  - `link_remote_new_window`
- Remote page numbers are no longer promoted as same-document `link_destination_page` span metadata. Chained `/Next` local GoTo rows remain available in `link_actions_review`.

## Focused Evidence

Syntax:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-remote-gotor-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-link-remote-gotor-boundary-currentbase.php
```

Focused assigned gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps remote GoToR Link annotations as review metadata without local page promotion

1 test files, 44 assertions, 0 failures
```

Adjacent link/action family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 520 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-remote-gotor-boundary-currentbase.php
```

The smoke emits `primary_action_type=GoToR`, `primary_safety=remote-document-review`, `remote_file=remote-appendix.pdf`, `remote_destination_page=3`, `remote_view_mode=FitH`, `remote_new_window=true`, `local_destination_page_promoted=false`, `hidden_remote_promoted=false`, `visible_text_excludes_remote_operands=true`, and all PDF action, JavaScript, Python/model, and external-tool execution flags false.

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `1259 -> 1260 pass / 0 fail`.
- WordPress scenarios move `1226 -> 1227`.
- Mapped upstream denominator is unchanged; this deepens the already mapped annotation/link action boundary.

## Non-Overlap

This does not repeat accepted generic URI extraction, local GoTo destinations, page-level `/Annots` ownership, escaped page `/Ann#6fts` names, escaped Link dictionary keys, exact-generation Link operands, Widget link inheritance, hidden/no-view filtering, rotated/UserUnit Link rectangles, Link `/QuadPoints`, Link presentation metadata, text-markup `/QuadPoints`, annotation appearance/popup/sound review, StructParent action context, outline remote GoToR extraction, or xref repair boundaries.

The bounded behavior is specifically primary page Link annotation `/S /GoToR` review metadata and span propagation without same-document destination promotion.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-exact reference resolver, token-aware action parser, FileSpec string decoder, supplied marker/pdftext span model, and Markdown span merge path. Full upstream Python/PDFium/model benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
