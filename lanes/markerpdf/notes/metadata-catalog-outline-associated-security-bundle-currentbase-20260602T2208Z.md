# markerPDF Metadata Catalog Outline Associated Security Bundle Current Base

Session: `port-dev-markerpdf-meta69-20260602T215653Z`
Base accepted HEAD: `0059bb644ec3506849ecf93d4f87651501a9af5b`

## Source Truth

- Upstream markerPDF pinned source `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF text extraction in `marker/pdf/extract_text.py`, delegating structured text extraction to `pdftext.extraction.dictionary_output(...)` and fallback text extraction to `pypdfium2` rather than executing PDF actions.
- Upstream `marker/convert.py::convert_single_pdf()` consumes extracted text blocks and metadata before OCR/model/table/rendering paths, so PDF catalog metadata, outline navigation, signatures, and actions are import review metadata, not visible WordPress paragraphs.
- PDF parser boundary for this slice: catalog `/AF` FileSpec rows, page `/AF` FileSpec rows, catalog `/OpenAction`, outline item `/A`, action-chain `/Next`, and signature `/ByteRange` plus `/Reference /DocMDP` are review surfaces. Embedded payload bytes, ICC profile bytes, signature contents, JavaScript, and Launch operands stay non-executing and out of visible text.

Upstream references used:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`

## Behavior

- `PdfOutlineExtractor::getNavigationReviewMetadata()` now applies existing destination-chain target context to catalog OpenAction `/Next` rows. Chained OpenAction rows therefore preserve the same destination page review metadata that outline action chains already carried.
- `PdfSecurityPreflight` now uses the rich navigation review rows for catalog OpenAction and outline actions, then compacts target-page associated FileSpec rows into review-safe action metadata:
  - `destination_action_target_page_associated_file_count`
  - `destination_action_target_page_associated_files`
  - `destination_action_target_page_associated_file_filenames`
  - `destination_action_target_page_associated_file_relationships`
  - `destination_action_target_page_associated_file_checksum_statuses`
- Document action, certifying OpenAction, and outline-action security summaries now expose unique target-page associated-file filenames, relationships, and checksum statuses.
- Associated file rows are sanitized before entering security reports. They preserve filenames, relationships, object numbers, MIME type, hashes, and checksum match state, but never copy `content`.

The focused fixture composes current catalog XMP/PDF-A OutputIntent metadata, catalog `/AF` source FileSpec metadata, an OpenAction GoTo with JavaScript `/Next`, an outline GoTo action with URI and Launch `/Next`, a target page `/AF` FileSpec, and a DocMDP signature. WordPress-visible text remains only the two page body strings.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php`
  - Failed before implementation with missing `destination_action_target_page_associated_file_count`.
- Focused: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php`
  - Passed: `1 test files, 83 assertions, 0 failures`.
- Neighboring gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationTransitionThreadSecurityCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityCertPermissionOpenActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php`
  - Passed: `7 test files, 898 assertions, 0 failures`.
- Final focused gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationTransitionThreadSecurityCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityCertPermissionOpenActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php`
  - Passed: `8 test files, 1197 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-metadata-catalog-outline-associated-security-bundle-currentbase.php`
  - Passed and emitted visible paragraphs `Associated security intro body` and `Associated security target body` plus review-only metadata for `catalog-security-source.xml` and `outline-security-target.xml`.
- PHP lint passed for:
  - `lanes/markerpdf/src/PdfSecurityPreflight.php`
  - `lanes/markerpdf/src/PdfOutlineExtractor.php`
  - `lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php`
  - `lanes/markerpdf/examples/wordpress-pdf-metadata-catalog-outline-associated-security-bundle-currentbase.php`
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json && jq empty lanes/markerpdf/lane-status.json`
  - Passed.
- `git diff --check -- lanes/markerpdf`
  - Passed.

Status delta: markerPDF behavior tests move `880 -> 881 pass / 0 fail`. Mapped semantics move `621 -> 622 / 78`.

## Non-Overlap

This does not repeat accepted catalog `/AF` PDF/A metadata, attachment-local PieceInfo/checksum review, standalone OpenAction `/Next` walking, outline named-destination security rows with transition/thread context, certificate permission OpenAction review, Launch/URI permission review, signature action-chain byte-range container review, page associated-file marked-content review, or page StructTree associated-file propagation. The new behavior is the cross-boundary security summary that carries target page associated FileSpec provenance from catalog OpenAction and outline action chains into document action preflight rows.

## Dependency Closure

No new support component is needed. This reuses native PHP PDF object parsing, metadata extraction, page associated-file review, outline/navigation context, action-chain review, security preflight, signature ByteRange/DocMDP review, and text extraction. Full upstream runner parity remains gated by live Python/pdftext/pypdfium2/Surya/tabled-pdf/Texify/model/app/server workflows; none were run for this bounded PHP slice.
