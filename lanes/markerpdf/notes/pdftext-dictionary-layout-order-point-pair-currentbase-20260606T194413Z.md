# markerPDF pdftext dictionary layout/order point-pair boundary

- Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T194413Z`
- Base accepted HEAD: `480dfafaed3237c669efe5b3c7297199c7dcf83c`
- Scope: native no-GPU markerPDF supplied pdftext dictionary layout/order behavior.

## Source Truth

Upstream markerPDF trims pdftext dictionary pages to the selected page range, then zips selected pages with layout/order model outputs before reading-order assignment. This patch keeps that contract for supplied artifacts whose bbox geometry is serialized as point-pair dictionaries such as `top_left`/`bottom_right` or `tl`/`br`. The native PHP path now normalizes those point-pair aliases before assigning order positions, matching the existing layout/table geometry boundary and keeping private adapter payloads out of WordPress output.

## Evidence

Red-first focused run after adding the point-pair cases and before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`

Result: `1 test files, 594 assertions, 2 failures`

Failures:

- `uses point-pair order bboxes before selected pdftext layout assignment`
- `uses point-pair layout and order bboxes for WordPress supplied imports`

After the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`

Result: `1 test files, 607 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-point-pair-currentbase.php`

Result: passed; output reports `point_pair_order_applied=true`, `private_payloads_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Added 2 focused PASS cases to the existing pdftext dictionary layout/order boundary file.
- Added 28 focused assertions over the previously accepted focused file count (`579 -> 607`).
- Added 1 WordPress scenario smoke.
- No new mapped manifest denominator row is claimed.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP supplied artifact selector, layout annotator, orderer, and document converter. It does not execute Python, CUDA, OCR/model workers, pypdfium, PIL, external PDF tools, shell commands, or live services.

## Non-Overlap

This is not another normalized bbox, polygon, named-object bbox, keyed page marker, wrapper-list, JSON-decoded artifact, or dictionary-output envelope case. It only adds point-pair bbox alias normalization for order geometry, complementing the layout/table geometry support already present.
