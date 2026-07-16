# markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T171801Z

## Source Truth

Upstream markerPDF zips supplied page images, layout predictions, and reading-order predictions after the selected pdftext/PDFium page range is trimmed, so the native adapter receives one layout/order result for each selected page. This no-GPU slice keeps that contract in PHP only: it does not run Surya, Texify, OCR, Python multiprocessing, raster model workers, external PDF tools, or live model benchmarks.

## Implemented Boundary

Typed `layout_result` and `order_result` wrappers may carry trusted outer page metadata while their direct `pages` or `dictionary_output` payload contains a raw JSON list with multiple unmarked prediction dictionaries. The native markerPDF adapter now fails closed for that shape before assigning layout or order geometry, because no single inner payload is tied to the selected pdftext page. Source-keyed direct payload maps remain accepted when exactly one inner candidate matches the selected page identity.

This prevents stale or ambiguous unmarked layout/order payloads from reordering searchable PDF text, promoting headings, or leaking raw payload strings into WordPress import output.

## Verification

- `php -l lanes/markerpdf/src/LayoutOrderer.php`  
  Result: no syntax errors detected.
- `php -l lanes/markerpdf/src/LayoutAnnotator.php`  
  Result: no syntax errors detected.
- `php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedPayloadListBoundaryCurrentBaseTest.php`  
  Result: no syntax errors detected.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-typed-payload-list-currentbase.php`  
  Result: no syntax errors detected.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedPayloadListBoundaryCurrentBaseTest.php`  
  Result: 1 test file, 37 assertions, 0 failures; 2 PASS cases added.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedJsonEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonListEntryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMarkerConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderAmbiguousEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderWrapperGeometryEnvelopeCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedPayloadListBoundaryCurrentBaseTest.php`  
  Result: 8 test files, 1138 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-typed-payload-list-currentbase.php`  
  Result: exits 0 with `layout_assigned_pages_zero=true`, `order_assigned_pages_zero=true`, `source_order_preserved=true`, `heading_not_promoted=true`, `payload_excluded=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted source-keyed typed JSON maps, keyed direct payload envelopes, JSON list-entry artifact decoding, wrapper geometry envelopes, trusted metadata page markers, duplicate direct-key conflicts, nonfinite marker or bbox guards, outline metadata, CMaps, xref repair, encryption preflight, annotations, forms, or image/filter metadata. The new coverage owns only the typed wrapper plus multi-entry unmarked direct JSON-list payload ambiguity boundary.

## Dependency Closure

No new support component is needed. The patch reuses the existing native `PdfPageArtifactSelector`, `LayoutAnnotator`, `LayoutOrderer`, `PdfTextDocumentExtractor`, and `SuppliedDocumentConverter` supplied-boundary pipeline. Remaining out-of-scope model/OCR parity is a no-GPU scope limit, not a blocker for this searchable-PDF/supplied-artifact behavior.

## Next

Continue with non-overlapping native markerPDF parser/converter behavior, especially searchable-PDF fonts/CMaps, stream filters, xref repair, page geometry, annotations/forms, metadata, image/filter metadata, or supplied-boundary table/equation handoffs.
