# markerPDF pdftext dictionary layout order keyed direct payload current base

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF page dictionaries to `pdftext.extraction.dictionary_output(..., page_range=...)` before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` trims selected pages before layout and reading-order handoff, then pairs one layout/order prediction with each selected Marker page. Native PHP supplied-boundary adapters therefore need to preserve selected page identity when cached layout/order payloads are serialized in adapter envelopes.

## Implemented Boundary

- `PdfPageArtifactSelector` now treats a singleton source-page keyed object map containing one direct artifact payload as one artifact list item instead of flattening the map values as independent page artifacts.
- If the outer envelope already carries page identity, `PdfPageArtifactSelector` preserves that outer artifact so trusted selected-page metadata remains authoritative.
- `LayoutAnnotator` and `LayoutOrderer` now unwrap singleton direct payloads stored under `pages`, `dictionary_output`, or `pdftext` object-map envelopes such as `["5311" => ["bboxes" => ...]]`.
- Multi-dictionary object maps remain ambiguous and continue through the existing fail-closed empty-payload path.

## Red-First Evidence

- Before source changes, the new focused cases failed:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 729 assertions, 2 failures`.

## Verification

- Focused direct run:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 749 assertions, 0 failures`.
- Focused layout/order family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php`
  - Result: `5 test files, 1920 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-keyed-envelope-currentbase.php`
  - Result: exits `0`, emits `layout_keyed_payload_unwrapped=true`, `order_keyed_payload_unwrapped=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- PHP lint:
  - `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php`
  - `php -l lanes/markerpdf/src/LayoutAnnotator.php`
  - `php -l lanes/markerpdf/src/LayoutOrderer.php`
  - `php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`
  - `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-keyed-envelope-currentbase.php`
  - Result: no syntax errors.
- JSON status/manifest validation:
  - `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK\n"; }'`
  - Result: both files decode.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP supplied-boundary selector, layout annotator, layout orderer, and converter plumbing. It does not execute Python, pdftext, PDFium, Surya, Texify, Torch, OCR, model workers, raster backends, PDF actions, or external PDF tools.

## Non-Overlap

This does not repeat accepted selected range slicing, sparse keyed artifact matching, wrapper-list handling, direct list envelopes, direct `dictionary_output` payload envelopes, explicit `pdftext` envelopes, typed payload wrappers, stale payload marker sanitation, normalized bbox handling, zero-area/non-finite geometry rejection, row-level marker stripping, JSON object normalization, table/equation supplied-boundary work, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security preflight, OCR, or model parity. The bounded behavior is only singleton keyed direct payload envelopes inside already selected pdftext dictionary layout/order artifacts.
