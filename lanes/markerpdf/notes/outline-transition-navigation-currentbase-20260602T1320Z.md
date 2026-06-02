# Outline Transition Navigation Current Base

Slice: `outline-transition-navigation-currentbase-20260602T1320Z`

Base accepted HEAD: `8222e6d278bf50a168a1fbef8aa9e27f100cc5f3`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates outline resolution to the PDF engine via `doc.get_toc(max_depth=...)`, preserving each item title, level, and zero-based page index as document metadata.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` returns page blocks plus TOC metadata, so navigation/action metadata remains separate from visible page text.
- PDF outline items may use `/A` action dictionaries instead of plain `/Dest`. Clicking an outline item may navigate locally or trigger URI, JavaScript, launch, remote-GoTo, or chained `/Next` followups. WordPress imports must surface these as review-only navigation metadata without executing actions or leaking operands into visible paragraphs.

## Implementation

- `PdfOutlineExtractor::getNavigationReviewMetadata()` now includes an `outline_action_review_actions` payload and `outline_actions` source marker when outline items carry chained or non-local action dictionaries.
- Plain local `/A << /S /GoTo ... >>` outline actions are still represented by the stable outline destination rows and are not duplicated as action rows unless they contain chained `/Next` followups.
- Outline action rows reuse the existing bounded action classifier, inherit outline title/level/object metadata, attach page labels and target-page transition/action context for local targets, and keep all actions `executes_on_import=false`.
- Added `wordpress-pdf-outline-transition-navigation-currentbase.php` to prove the WordPress import path keeps chained URI, JavaScript, and unsafe URI operands out of visible paragraph text.

## Evidence

- Red-first focused gate before the extractor change:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`
  failed with `1 test files, 234 assertions, 2 failures`.
- Focused gate after the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`
  passed with `1 test files, 263 assertions, 0 failures`.
- Adjacent gate:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
  passed with `3 test files, 1081 assertions, 0 failures`.
- Full markerPDF lane gate:
  `php tools/run-tests.php lanes/markerpdf/tests`
  passed with `66 test files, 4173 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-transition-navigation-currentbase.php`
  emitted `outline_action_count=4`, `outline_action_safeties=[local-destination,review-uri,blocked-javascript,blocked-unsafe-uri]`, `outline_action_chained_count=2`, `local_action_target_label=Deck 3`, `local_action_target_transition=Push`, `all_outline_actions_review_only=true`, `visible_text_excludes_outline_actions=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- PHP lint passed for:
  `lanes/markerpdf/src/PdfOutlineExtractor.php`,
  `lanes/markerpdf/tests/PdfOutlineExtractorTest.php`, and
  `lanes/markerpdf/examples/wordpress-pdf-outline-transition-navigation-currentbase.php`.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `507 -> 508`.
- Mapped markerPDF/PDF semantics move `355 -> 356 / 78`.

## Non-Overlap

This does not repeat accepted outline named-destination resolution, indirect destination view operands, indirect name-tree destination dictionaries, page `/Dur` `/Trans` `/AA` extraction, target-page transition annotation for name-tree outline rows, catalog OpenAction `/Next` review, link/text-markup annotation action review, AcroForm action review, JavaScript catalog action review, or rich-media action target boundaries. This slice is limited to outline item `/A` action dictionaries inside composite navigation review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, outline walker, destination/name-tree resolver, page-label parser, page transition/action review parser, bounded `/Next` cycle/depth guard, and visible text extractor boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
