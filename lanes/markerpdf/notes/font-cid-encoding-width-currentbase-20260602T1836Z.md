# markerPDF font CID encoding width current-base

Session: `port-dev-markerpdf-font39pdf-20260602T1836Z`
Micro-slice: `font-cid-encoding-width-currentbase`
Base accepted HEAD: `c8171d52508caddcd1c671d4d1f28bc5aa6c0960`

## Source Truth

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates native PDF text extraction to `pdftext.extraction.dictionary_output`, so the PHP port needs to preserve low-level PDF font/CMap/width behavior before WordPress paragraph conversion.
- The dependency-side PDF font/CMap behavior in pypdf dereferences descendant font dictionaries and CIDFont `/W` array operands with `get_object()`. This slice ports the bounded boundary where Type0 `/Encoding` CMap source bytes select descendant CIDs before CIDFont `/W` list/range widths whose numeric members are indirect objects.

## Native Behavior

- `PdfTextExtractor::fontWidthMetrics()` now passes the object table into CIDFont `/W` parsing.
- `cidWidthsFromWArray()` now walks top-level PDF array items and resolves direct or indirect integer operands for:
  - first CID values;
  - last CID values in range-width rows;
  - range/list width numeric values.
- The fixture maps source bytes `<01>` through `<09>` to CIDs `40` through `48` and `<14>` through `<1B>` to CIDs `60` through `67`, then uses `/W [40 [7 0 R ...] 60 67 8 0 R]` with objects `7 0 obj 1000` and `8 0 obj 1000`.
- Without resolving those indirect width members, the x gaps split `WideBlock` and `DataFlow`. With the slice, WordPress paragraphs stay grouped as `WideBlock` and `DataFlow`.

## Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-font-cid-encoding-width-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-font-cid-encoding-width-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php`
  - `1 test files, 7 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php`
  - `10 test files, 703 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-font-cid-encoding-width-currentbase.php`
  - emitted `WideBlock` and `DataFlow` Gutenberg paragraphs with `indirect_w_list_widths_resolved` and `indirect_w_range_width_resolved` true.
- `git diff --check -- lanes/markerpdf`
  - passed with no output.

## Status Delta

- Behavior tests move `645 -> 646 pass / 0 fail`.
- Mapped markerPDF semantics move `471 -> 472 / 78`.
- `UPSTREAM_TEST_MANIFEST.json` adds `pdfFontCidEncodingIndirectWidth`.

## Dependency Closure

No new support component is needed. This reuses the existing native object scanner, CMap parser, CIDFont width metrics path, text-position grouping, and WordPress smoke example path. Full upstream runner parity remains gated on live `pdftext`, `pypdfium2`, Surya/Torch/model, Streamlit/FastAPI, and benchmark workflow dependencies.

## Non-overlap

This does not repeat the accepted Type0 CMap resource width slice, indirect `/W` array-object slice, indirect `/DW` default-width slice, vertical `/W2` slice, CIDSet/default-width slice, Type3 CharProc slice, simple-font indirect width slice, FontDescriptor flag slice, or ToUnicode surrogate CID width grouping. The new boundary is resolving indirect numeric members inside CIDFont `/W` list and range forms after Type0 `/Encoding` CMap CID selection.

## Next Task

Continue font import fidelity with another non-overlapping current-base boundary such as composite font descendant metrics through additional CMap indirection, or switch to the next queued markerPDF rework note if one appears for this lane.
