# markerpdf outline lightweight named destination current-base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260607T123236Z`

Accepted base: `e57c0bcf9b6e3ffa5b25f24a078d7756e1f0a24a`

## Source truth

- Upstream markerPDF extracts lightweight `pdf_toc` through `marker.cleaners.toc.get_pdf_toc()`, which receives title, level, and page data from pypdfium's document TOC adapter before model/OCR execution.
- Native markerPDF already had a richer `PdfOutlineExtractor::getPdfToc()` boundary that resolves catalog name-tree destinations and action destination maps. This slice wires the lightweight `PdfTextExtractor::extractOutlineMetadata()` path through that native resolver before falling back to the older object-only parser.

## Behavior

- Outline items using `/Dest /Name` and `/Dest (Name)` now appear in lightweight `pdf_toc` rows when the catalog `/Names /Dests` tree maps those names to page destinations.
- The legacy `title` / `level` / `page` row shape is preserved for the upstream-style metadata payload.
- Outline item metadata streams remain review-only. The synthetic XMP payload is not emitted in `pdf_toc`, document metadata JSON, navigation JSON, or visible WordPress paragraph text.

## Red-first evidence

Command before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightNamedDestinationBoundaryCurrentBaseTest.php`

Result before implementation:

`1 test files, 12 assertions, 1 failures`

Failing assertion: lightweight `pdf_toc` was `[]` for named outline destinations while rich outline metadata resolved the same two destinations.

## Verification

Focused after-fix command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightNamedDestinationBoundaryCurrentBaseTest.php`

Result: `1 test files, 16 assertions, 0 failures`

Related outline metadata regression command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightNamedDestinationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightCountOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataCatalogOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataActionDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataObjectValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php`

Result: `12 test files, 529 assertions, 0 failures`

Root/stream outline regression command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataIndirectRootTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootZeroCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php`

Result: `7 test files, 271 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-lightweight-named-destination-currentbase.php`

Result: exits 0 with `toc_pages=[0,1]`, `resolved_destination_count=2`, and `outline_metadata_payload_hidden=true`.

## Non-overlap

This does not touch live OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, raster rendering, inline image parsing, xref repair, annotations, forms, attachments, page labels, or the existing rich outline named-destination review rows. The implementation is limited to the lightweight upstream-shaped `extractOutlineMetadata()['pdf_toc']` boundary.

## Dependency closure

No new support component is needed. The slice reuses the existing bounded native PHP outline parser and name-tree destination resolver. GPU/model execution and external PDF tooling remain intentionally out of scope for markerPDF under the current no-GPU lane rule.
