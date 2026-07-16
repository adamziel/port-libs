# Outline OpenAction Next Chain Boundaries

Slice: `outline-thread-destination-openaction-boundaries-currentbase-20260602T0916Z`

Source truth:
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates PDF bookmark and destination extraction to the PDF engine and preserves review metadata rather than executing PDF actions.
- PDF catalog `/OpenAction` entries may be action dictionaries, and PDF action dictionaries may carry `/Next` actions. WordPress imports need those follow-up actions surfaced as review-only metadata, not executed or hidden behind the first action.

Implementation:
- `PdfOutlineExtractor::getOpenActionReviewActions()` now routes action-dictionary `/OpenAction` values through the existing bounded action-chain walker used for page additional actions.
- Destination-array and named-destination `/OpenAction` values still use the existing single local-destination review path, preserving destination metadata behavior.
- `examples/wordpress-pdf-openaction-safety-review.php` now includes a chained OpenAction document and emits chained review flags.

Red-first evidence:
- Before the parser change, the focused chained fixture returned only the first `URI` row and missed the chained `Launch` and local `GoTo` followups.

Verification:
- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-openaction-safety-review.php` passed.
- `php -r 'foreach (["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json", "lanes/markerpdf/lane-status.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . " valid\n"; }'` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: 1 test file, 133 assertions, 0 failures. Previous focused count was 124 assertions.
- `php lanes/markerpdf/examples/wordpress-pdf-openaction-safety-review.php` passed and emitted `open_action_count=6`, `chained_action_count=2`, and `all_review_only=true`.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:
- MarkerPDF behavior tests move 448 -> 449.
- Mapped source/dependency semantics move 300 -> 301 / 78 with `pdfCatalogOpenActionNextChainBehaviors`.

Dependency closure:
- No new support component is needed. The slice reuses the native PDF object parser, destination/name-tree resolver, action classifier, and bounded `/Next` cycle/depth guard without Python, pdftext, pypdfium, Poppler, Ghostscript, JavaScript execution, or model downloads.

Non-overlap:
- This does not repeat accepted page `/AA` action metadata, JavaScript action-chain inventory, rich-media chained actions, indirect destination operands, indirect name-tree destinations, catalog `/Threads` bead ordering, or the base OpenAction URI/Launch/GoToR review slice. It only exposes chained `/Next` followups hanging off catalog `/OpenAction`.

Next task:
- Continue markerPDF parser edge work around non-overlapping outline/action, xref/object stream, annotation, form, font, image, metadata, and security review boundaries that can ship with focused PHP evidence.
