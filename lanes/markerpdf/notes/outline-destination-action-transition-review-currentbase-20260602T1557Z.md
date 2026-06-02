# Outline Destination Action Transition Review

Micro-slice: `outline-destination-action-transition-review-currentbase-20260602T1557Z`

Base accepted HEAD: `47657692317361f6d3d564f3ae90eb5c7da42a7e`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates outline and destination resolution to the PDF engine and treats the result as document/navigation metadata.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` keeps page text blocks separate from TOC/navigation metadata. PDF viewer actions, `/Next` chains, JavaScript, and URI/Launch targets must not execute or leak into visible WordPress text.
- PDF destination name trees can resolve outline `/Dest` values to dictionaries that still contain a local `/D` destination plus action fields such as `/S /GoTo` and `/Next`. This slice maps that boundary without re-running Python, pdftext, pypdfium, OCR, or model workers.

## Implementation

- `PdfOutlineExtractor::getNavigationReviewMetadata()` now surfaces outline `/Dest` values that resolve through `/Names /Dests` or direct destination objects to action dictionaries.
- Destination action dictionaries keep their stable local outline rows, page labels, `/FitH` view parameters, target page `/Dur`, `/Trans`, and page `/AA` action metadata.
- Chained URI and JavaScript followups hanging off the destination action dictionary are added to `outline_action_review_actions` as review-only rows with `executes_on_import=false`.
- Plain destination dictionaries with only `/D` remain represented by existing outline destination rows and are not duplicated as action rows.
- Added `wordpress-pdf-outline-destination-action-transition-currentbase.php` to prove the WordPress path keeps chained action operands out of Gutenberg paragraphs.

## Evidence

- Red-first focused gate before the extractor change:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php`
  failed with missing `outline_actions` source rows: `1 test files, 7 assertions, 1 failures`.
- Focused gate after the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php`
  passed with `1 test files, 43 assertions, 0 failures`.
- Adjacent outline gate:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`
  passed with `2 test files, 306 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-destination-action-transition-currentbase.php`
  emitted `outline_action_count=5`, `outline_action_safeties=[local-destination,review-uri,blocked-javascript,local-destination,blocked-unsafe-uri]`, `outline_action_chained_count=3`, `outline_target_transitions=[Wipe,Wipe]`, `all_outline_actions_review_only=true`, `visible_text_excludes_destination_actions=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- PHP lint passed for:
  `lanes/markerpdf/src/PdfOutlineExtractor.php`,
  `lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php`, and
  `lanes/markerpdf/examples/wordpress-pdf-outline-destination-action-transition-currentbase.php`.
- `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` and `lanes/markerpdf/lane-status.json` decoded as valid JSON.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- PHP behavior tests move `532 -> 534` because the focused file adds two TestRunner PASS cases.
- Mapped markerPDF/PDF semantics move `379 -> 380 / 78`.

## Non-Overlap

This does not repeat accepted named-destination outline resolution, indirect destination-view operands, indirect name-tree destination dictionaries, catalog OpenAction `/Next` review, outline `/A` action-chain review, target-page transition annotation for ordinary name-tree outline rows, page `/Dur` `/Trans` `/AA` extraction, link/text-markup annotation action review, AcroForm action review, JavaScript catalog action review, or rich-media action target boundaries. This slice is limited to action dictionaries reached through outline `/Dest` values.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, outline walker, destination/name-tree resolver, page-label parser, page transition/action review parser, bounded `/Next` cycle/depth guard, and visible text extractor boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
