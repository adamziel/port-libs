# markerpdf outline unselected catalog operand current-base

Date: 2026-06-08 UTC

Slice: `markerpdf-outline-metadata-boundary-current-base-20260608T120307Z`

Source-truth boundary:

- PDF catalog `/Outlines` is a single selected top-level catalog value. Existing native markerPDF behavior already supports duplicate catalog `/Outlines` keys by selecting the final top-level entry and keeping unselected roots review-only.
- This slice closes the adjacent boundary where an unselected duplicate `/Outlines` entry has extra operands. The malformed unselected operand must not suppress the final selected outline root; the selected entry still fails closed when it has trailing operands.
- Upstream markerPDF receives outline/TOC metadata from PDF parser layers and does not execute PDF actions. This patch keeps that no-GPU/no-model boundary: selected outline titles/actions become review metadata, while unselected stale outline/action operands and payloads stay out of visible WordPress text.

Verification plan:

- Red-first probe on accepted base `5f425da1740b76fd38a51b6ce59a09edd9c388d7` showed `has_outline => false`, empty TOC/navigation titles, and `document_outline_boundary_review.status => rejected_malformed_catalog_outlines_operand` when the first duplicate `/Outlines` value had a trailing reference but the final selected `/Outlines` root was valid.
- Focused test: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUnselectedCatalogOperandBoundaryCurrentBaseTest.php`.
- Regression family: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCatalogOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataCatalogDuplicateRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedDuplicateOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataUnselectedCatalogOperandBoundaryCurrentBaseTest.php`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-outline-unselected-catalog-operand-currentbase.php`.

Dependency closure:

- No new support component is needed. The patch reuses the existing native PHP PDF token/dictionary parser, outline extractor, metadata extractor, and WordPress smoke harness.
- No Python, OCR, Surya/Texify/Torch, pypdfium raster execution, JavaScript/PDF action execution, or external PDF tools are run.

Next task:

- Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
