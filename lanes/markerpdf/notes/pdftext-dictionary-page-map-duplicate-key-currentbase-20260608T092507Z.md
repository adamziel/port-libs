# markerPDF pdftext dictionary page-map duplicate key boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T092507Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260608T092507Z`
Base accepted HEAD: `76dc0ae478cf17b9d4471313469197e6c70ed1d9`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()`, which calls `pdftext.extraction.dictionary_output(...)` for a selected page range and then enumerates only the returned page dictionaries into Marker pages: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>.
- Locked `pdftext` dictionary output returns an ordered page list after page/block/line/span normalization, not an object map with duplicate page-key aliases: <https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py>.

## Implemented Behavior

- `PdfTextDocumentExtractor::orderedSuppliedDictionaryPageList()` now rejects page-shaped cache maps whose keys normalize to the same source-page integer, such as `01` and `+1.0`.
- The guard applies before selected-page slicing, so stale adapter `pages` cannot be imported when an explicit `dictionary_output` or `pdftext` cache map is ambiguous.
- Unique integer-valued cache maps still sort and slice as before.
- Added a WordPress smoke proving duplicate normalized keys fail closed while a unique keyed map imports the selected pdftext page without Python, OCR, models, or external PDF tools.

## Red First

Scratch probe before the source change:

```text
array (
  0 => 2,
  1 =>
  array (
    0 => 101,
    1 => 1,
  ),
)
```

That showed equivalent keys such as `01` and `1` being accepted as two source pages.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryPageMapDuplicateKeyBoundaryCurrentBaseTest.php
=> 1 test files, 2 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryPageMapDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryJsonStringEnvelopeBoundaryCurrentBaseTest.php
=> 3 test files, 477 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-map-duplicate-key-currentbase.php
=> duplicate_normalized_keys_rejected=true; unique_keyed_map_imported=true; stale_duplicate_adapter_excluded=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

Focused delta: +2 focused PASS cases and +1 WordPress smoke.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, cache-envelope normalizer, selected-page slicing, Markdown block merging, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch/OCR/layout/order/table models, Texify, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat accepted dictionary_output envelope unwrapping, malformed `pdftext` envelope rejection, raw JSON string envelope decoding, direct page wrapping, singleton/nested/list-entry envelope unwrapping, decimal-key ordering for unique maps, link/ref preservation, ref de-duplication, disable-links behavior, keep-chars validation, font flags, character indexes, Unicode repair, normalized bbox scaling, layout/order artifact matching, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations, forms, security preflight, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is ambiguous duplicate normalized source-page keys in native pdftext dictionary cache maps.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter boundaries around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
