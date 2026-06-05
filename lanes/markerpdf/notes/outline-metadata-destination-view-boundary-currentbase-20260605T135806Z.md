# markerpdf outline metadata destination view boundary current base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T135806Z`
Base accepted HEAD: `7c27a6118223c3a795b10dae9f12e2e6310f566a`

## Source truth

- Upstream `marker/cleaners/toc.py` at the pinned markerPDF manifest keeps the
  TOC row contract to `title`, `level`, and `page`; this slice preserves that
  shape for basic TOC output.
- The existing PHP lane already validates PDF destination view names in
  `PdfNamedDestinationExtractor`, so this slice applies the same fail-closed
  PDF explicit destination boundary to outline metadata and outline navigation
  review.
- Valid explicit destination view names remain `Fit`, `FitB`, `FitBH`,
  `FitBV`, `FitH`, `FitR`, `FitV`, and `XYZ`.

## Behavior

- `PdfMetadataExtractor` now rejects outline destinations whose explicit
  destination view-name operand is unknown or resolves indirectly to an
  unknown name such as `/Launch` or `/RichMedia`.
- `PdfOutlineExtractor` applies the same boundary before returning page indexes,
  destination-view rows, or navigation review metadata.
- Valid destination operands are normalized to the PDF view arity before being
  exposed: `FitH` and related single-coordinate modes keep one coordinate,
  `FitB`/`Fit` keep none, `FitR` keeps four, and `XYZ` keeps left/top/zoom
  while normalizing zero zoom to `null`.

## Evidence

- Red-first focused run before the source change:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationViewBoundaryCurrentBaseTest.php`
  => `1 test files, 7 assertions, 2 failures`.
- Focused run after the source change:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationViewBoundaryCurrentBaseTest.php`
  => `1 test files, 60 assertions, 0 failures`.
- Broader outline family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*CurrentBaseTest.php`
  => `51 test files, 2378 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-destination-view-boundary-currentbase.php`
  verifies `metadata_resolved_destination_count=3`,
  `metadata_unresolved_destination_count=2`, `toc_view_modes=[FitH,FitB,XYZ]`,
  and `invalid_view_operands_excluded=true`.

## Dependency closure

No new support component is needed. The slice reuses the native PHP PDF object
resolver, outline extractor, metadata extractor, and existing no-GPU parser
fixtures. It does not run OCR, Surya, Texify, Torch, model workers, external PDF
tools, or online services.

## Non-overlap

This does not repeat the recent attachment annotation review-state slice,
annotation link destination-generation slice, named destination metadata slice,
action-chain review slice, parent-boundary traversal slice, Last terminal
traversal slice, or EOF-bounded outline slice. The owned boundary is explicit
destination view-name validation and operand normalization for outline metadata
and navigation review.
