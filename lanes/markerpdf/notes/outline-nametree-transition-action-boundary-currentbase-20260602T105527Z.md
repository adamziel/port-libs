# Outline NameTree Transition Action Boundary

Slice: `outline-nametree-transition-action-boundary-currentbase-20260602T105527Z`

Base accepted HEAD: `32c83110c4ddf570f9851fa840f0f100432adb83`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates bookmark resolution to the PDF engine via `doc.get_toc(max_depth=...)`, preserving resolved title, level, and page index as document metadata.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` iterates bounded PDF pages and returns page blocks plus TOC metadata. This keeps navigation metadata separate from visible page text.
- PDF outline `/Dest` and outline `/A /GoTo /D` values can point through catalog `/Names /Dests` name trees. Target pages can carry `/Dur`, `/Trans`, and `/AA` page actions. WordPress imports should surface that as review metadata only, without executing actions or leaking URI/script operands into visible text.

## Implementation

- `PdfOutlineExtractor::getNavigationReviewMetadata()` now indexes page presentation rows by page index and attaches target-page presentation metadata to outline rows when their resolved destination page has `/Dur`, `/Trans`, or `/AA`.
- The lower-level `getPdfToc()` and `getPdfTocWithDestinationViews()` output shapes remain stable.
- OpenAction destination rows reuse the same helper and now also include target-page action rows when present.
- Added `wordpress-pdf-outline-nametree-transition-actions.php` as a WordPress smoke for outline name-tree destinations targeting a transition/action page.

## Evidence

- Red-first focused gate before the extractor change:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`
  failed with `1 test files, 200 assertions, 1 failures`.
- Focused gate after the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`
  passed with `1 test files, 234 assertions, 0 failures`.
- Adjacent gate:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
  passed with `3 test files, 975 assertions, 0 failures`.
- Full markerPDF lane gate:
  `php tools/run-tests.php lanes/markerpdf/tests`
  passed with `62 test files, 3421 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-nametree-transition-actions.php`
  emitted `outline_target_labels=["Deck 5","Deck 5"]`, `outline_target_transitions=["Fly","Fly"]`, `outline_target_action_safeties=["review-uri","blocked-unsafe-uri","review-uri","blocked-unsafe-uri"]`, `all_outline_target_actions_review_only=true`, `visible_text_excludes_action_targets=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- PHP lint passed for:
  `lanes/markerpdf/src/PdfOutlineExtractor.php`,
  `lanes/markerpdf/tests/PdfOutlineExtractorTest.php`, and
  `lanes/markerpdf/examples/wordpress-pdf-outline-nametree-transition-actions.php`.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `475 -> 476`.
- Mapped markerPDF/PDF semantics move `325 -> 326 / 78`.

## Non-Overlap

This does not repeat standalone named-destination outline resolution, indirect name-tree destination dictionaries, indirect destination-view operands, page-label/viewer-preference composition, catalog OpenAction `/Next` review, page `/Dur` `/Trans` `/AA` extraction, link annotation action review, or rich-media action target boundaries. This slice only annotates composite navigation outline rows with target page transition/action metadata after the outline destination resolves through a name tree.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, destination/name-tree resolver, page-label parser, page transition/action review parser, and visible text extractor boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
