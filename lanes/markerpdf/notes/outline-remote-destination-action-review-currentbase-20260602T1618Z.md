# Outline Remote Destination Action Review

Micro-slice: `outline-remote-destination-action-review-currentbase-20260602T1618Z`

Base accepted HEAD: `9192be14c831cb84a6d124eb0733f7e677891025`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates PDF outline resolution to `doc.get_toc(max_depth=...)` and projects only title, level, and page-index metadata.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` keeps page text extraction separate from TOC metadata through `pdftext.extraction.dictionary_output(...)`.
- At the native parser boundary, an outline `/Dest` name may resolve to an action dictionary. When that action is `/S /GoToR`, its `/D` array page number is a remote-document target, not a local page object. WordPress imports should expose it as review-only navigation metadata, never as a same-document TOC paragraph target.

Upstream references used:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`

## Implementation

- `PdfOutlineExtractor::getRemoteGoToActions()` now resolves outline `/Dest` values through catalog destination name trees before classifying remote `/S /GoToR` action dictionaries.
- Local TOC destination resolution now follows `/D` dictionaries only when the dictionary is a plain destination dictionary or a local `/S /GoTo` action. Remote `/S /GoToR` dictionaries are excluded from same-document TOC rows.
- `getNavigationReviewMetadata()` now surfaces remote destination-action rows through `outline_action_review_actions`, including bounded `/Next` URI, JavaScript, and local GoTo followups, all with `executes_on_import=false`.
- Added `wordpress-pdf-outline-remote-destination-action-currentbase.php` to prove the WordPress path keeps page paragraphs clean while exposing remote targets in review metadata.

## Evidence

- Red-first focused gate before the extractor fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php`
  failed with `1 test files, 3 assertions, 2 failures`. The named remote destination action was emitted as a local TOC row and remote actions were not returned by `getRemoteGoToActions()`.
- Focused gate after the fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php`
  passed with `1 test files, 32 assertions, 0 failures`.
- Adjacent outline gate:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`
  passed with `3 test files, 338 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-remote-destination-action-currentbase.php`
  emitted `remote_action_files=[external-guide.pdf,appendix.pdf]`, `local_toc_titles=[Local Appendix]`, `outline_action_count=5`, `all_outline_actions_review_only=true`, and `visible_text_excludes_remote_action_operands=true`.
- PHP lint passed for:
  `lanes/markerpdf/src/PdfOutlineExtractor.php`,
  `lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php`, and
  `lanes/markerpdf/examples/wordpress-pdf-outline-remote-destination-action-currentbase.php`.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- PHP behavior tests move `544 -> 546` because the focused file adds two TestRunner PASS cases.
- Mapped markerPDF/PDF semantics move `391 -> 392 / 78`.

## Non-Overlap

This does not repeat accepted named-destination outline resolution, direct outline `/A /GoToR` extraction, catalog OpenAction remote GoToR review, local outline destination action-transition review, outline `/A` action-chain review, page `/Dur` `/Trans` `/AA` metadata, link/text-markup annotation action review, AcroForm action review, rich-media GoToE/GoToR review, or visible text extraction. The new behavior is specifically remote GoToR action dictionaries reached through outline `/Dest` values.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, outline walker, destination/name-tree resolver, remote GoTo action classifier, bounded `/Next` action-review walker, page-label parser, and visible text extractor boundaries. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
