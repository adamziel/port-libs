# table-ocr-rotated-header-caption-currentbase

## Source Truth

- Upstream markerPDF table conversion depends on tabled-pdf recognition/formatting output and carries table recognition results into Markdown rather than stale page text. The locked tabled-pdf 0.1.4 behavior supplies row/column/cell grid semantics for table regions.
- Existing current-base markerPDF slices already covered rotated header-grid accessibility and caption-bound cellspan review separately. This slice binds those behaviors for forced-OCR rotated header captions in the supplied-document converter.

## Implementation

- `SuppliedDocumentConverter::tableAccessibilityReview()` now exposes rotated table orientation plus physical row/column axes and preserves those axes on data-cell header mappings.
- `SuppliedDocumentConverter::cellspanHeaderGridReview()` and `spanningGridSummary()` now carry the rotated flag and axis metadata into the caption-bound cellspan review path.
- Added a WordPress example smoke for forced-OCR rotated table header captions that verifies caption/section ids, rotated axes, header ids, stale text exclusion, and no Python/model/external PDF tool execution.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed before source patch with `1 test files, 460 assertions, 1 failures` on missing rotated caption-grid metadata.
- Green focused: `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with `1 test files, 471 assertions, 0 failures`.
- Adjacent focused gate: `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with `3 test files, 814 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-table-ocr-rotated-header-caption-currentbase.php` passed and emitted all checks as true while model/external-tool execution flags stayed false.
- Syntax/diff checks: PHP lint passed for changed PHP files; `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests move `921 -> 922` pass / `0` fail.
- Mapped semantics move `648 -> 649 / 78`.
- Non-overlap: this does not repeat the accepted rotated header-grid review or caption cellspan review slices; it only adds the combined forced-OCR rotated-caption metadata path.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP supplied-document converter, table recognizer, table formatter, and locked tabled-pdf-style row/column/cell semantics already present in the lane.
