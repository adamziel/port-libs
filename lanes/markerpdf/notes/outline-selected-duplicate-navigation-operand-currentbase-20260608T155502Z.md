# markerPDF outline selected duplicate navigation operand boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260608T155502Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` treats PDF bookmarks/TOC as document navigation metadata through `marker/cleaners/toc.py::get_pdf_toc`; visible text extraction stays separate in `marker/pdf/extract_text.py::get_text_blocks`.
- PDF dictionaries select the last top-level entry for duplicate keys. Native outline metadata review should therefore let a later clean `/Dest` replace a stale malformed earlier `/Dest`, while still recording duplicate-key review metadata.
- Native no-GPU boundary: the stale malformed operand and its decoy GoToR target remain unpromoted review input. They must not become TOC rows, remote action rows, or WordPress-visible text.

## Red-First Evidence

Before the source patch, the new focused test failed on accepted base `86df6fefba691ff921a8e11a304488be957a19c7`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedDuplicateNavigationOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses selected duplicate outline Dest after stale malformed operand in document metadata
Condition is not true
PASS keeps selected duplicate outline Dest in TOC navigation without promoting stale remote action
1 test files, 40 assertions, 1 failures
```

The failure showed that navigation already selected the later clean duplicate `/Dest`, but document metadata still preserved a stale `destination_operand_boundary_review` from the earlier malformed `/Dest /LegacyTarget 12 0 R`.

## Implementation

- `PdfMetadataExtractor::documentOutlineDestinationActionOperandBoundaryReview()` now applies the same last-top-level-entry policy already used by selected outline `/Metadata` review.
- A malformed `/Dest`, `/A`, or GoTo action `/D` review is kept only when the selected duplicate entry is malformed. A later clean entry clears stale malformed review while duplicate-key metadata remains available.
- The focused fixture proves a later `/Dest /AppendixTarget` resolves to the current named destination after a stale tailed `/Dest`, and the decoy remote action object stays out of document metadata, TOC/navigation review, lightweight outline metadata, remote action review, and visible text.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedDuplicateNavigationOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses selected duplicate outline Dest after stale malformed operand in document metadata
PASS keeps selected duplicate outline Dest in TOC navigation without promoting stale remote action
1 test files, 42 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-selected-duplicate-navigation-operand-currentbase.php
```

The smoke emits `selected_appendix_destination=AppendixTarget`, `duplicate_dest_review_preserved=true`, `stale_dest_boundary_cleared=true`, `remote_actions_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted catalog `/Outlines` operand, root `/First`/`Last`/`Count`, item `/Next`, `/Parent`, `/Prev`, `/Title`, `/Type`, `/Metadata`, `/SE`, `/F`, `/C`, simple tailed `/Dest`/`A`, action `/D`, action `/Next`, remote action stack, named destination, xref repair, image/filter, font/CMap, annotation/form, encrypted preflight, or inline image decode boundary slices. The bounded behavior is only selected duplicate outline navigation operands after a stale malformed earlier operand.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, outline metadata extractor, name-tree destination resolver, navigation review metadata, lightweight outline extraction, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
