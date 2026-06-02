# Outline Named Destination Fit Review Current Base

Slice: `outline-named-destination-fit-review-currentbase-20260602T1608Z`

Base accepted HEAD: `1556f4d4531f91f7e52406c68e4d138258622c73`

## Source Truth

- Upstream `marker/cleaners/toc.py::get_pdf_toc` at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates bookmark resolution to `doc.get_toc(max_depth=...)` and preserves each item title, level, and page index for downstream document navigation metadata: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- PDF destination view arrays have fixed operands by view mode: `/Fit` and `/FitB` have no coordinates, `/FitH` and `/FitBH` carry `top`, `/FitV` and `/FitBV` carry `left`, `/FitR` carries `left bottom right top`, and `/XYZ` carries `left top zoom` with zero zoom treated as null/current zoom. WordPress review metadata should not surface surplus operands from malformed or padded named destinations.

## Red Evidence

Before this parser change, a current-base one-off fixture with named destinations:

- `/FitB 111 222` emitted `view_position=[111,222]`.
- `/FitBH null 999` emitted `view_position=[null,999]`.

That leaked surplus coordinates into outline/navigation review rows even though the fixed PDF view mode does not consume them.

## Implementation

- `PdfOutlineExtractor::explicitDestinationDetails()` now routes destination coordinates through `normalizedViewPosition()`.
- Known modes are trimmed to their fixed operand counts before `view_parameters` are computed.
- Unknown view modes keep their raw numeric review operands, preserving existing fallback behavior.
- The updated WordPress smoke keeps `data-marker-view-mode="FitB"` while omitting the surplus `data-marker-view-position` attribute for named `/FitB` destinations.

## Verification

- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-destination-view-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: `1 test files, 277 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-destination-view-import.php` passed and emitted `named_fit_operands_normalized=true`, `indirect_view_operands_resolved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted named-destination outline resolution, indirect destination-view operand resolution, indirect name-tree destination dictionaries, page-label/viewer-preference composition, catalog OpenAction review, target-page transition/action annotation, outline action-chain review, or table/OCR slices. This slice is limited to fixed operand-count normalization for named destination Fit-family and XYZ view review metadata.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, name-tree destination resolver, outline navigation review metadata path, and existing WordPress smoke path. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
