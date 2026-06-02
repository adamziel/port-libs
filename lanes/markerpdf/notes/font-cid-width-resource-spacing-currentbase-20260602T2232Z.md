# markerPDF Font CID Width Resource Spacing Current Base

Session: `port-dev-markerpdf-font75-20260602T222511Z`

Micro-slice: `font-cid-width-resource-spacing-currentbase`

Base accepted HEAD: `dea63aa7e627de2d478a25a4f111e872b79036af`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF extraction to `pdftext.extraction.dictionary_output` before Marker converts page dictionaries into blocks, lines, and spans.
- The lane manifest and previous font notes capture the relevant PDF parser dependency behavior: Type0 `/Encoding` CMaps map source character codes to descendant CIDs, CIDFont `/W` and `/DW` supply glyph advance metrics keyed by those CIDs, and text-state spacing (`Tw`, `Tc`, `Tz`) is applied before text geometry is handed to downstream paragraph and styled-span conversion.

## Behavior Added

`PdfTextExtractor::textSpanLinesFromContentStream()` now carries horizontal text-state spacing into native styled-span extraction. `appendNativeTextSpan()` accepts the source text operand and computes horizontal span bbox width with the same active CMap, CIDFont `/W`, source word-spacing, and horizontal scaling path already used by text-line grouping.

Marked-content replacement spans still use the previous decoded-text fallback because they do not have a PDF source operand. Vertical styled-span bbox geometry remains a follow-up; this slice is limited to horizontal Type0/CID resource spacing.

## Focused Fixture

`PdfFontCidWidthResourceSpacingCurrentBaseTest.php` builds a native PDF page with:

- a Type0 font resource `/Fcid`;
- named `/Encoding /WPSpanAdvance-H` resolved from an embedded CMap resource;
- descendant CIDFont `/W [32 32 500 40 43 1000 65 66 500]`;
- ToUnicode rows that emit `Wide` and `A\u{2060}B`;
- `18 Tw` so the source key `<20>` mapped to CID 32 contributes word spacing even though the visible Unicode is a word-joiner.

Correct native styled output now keeps visible text as `WideA\u{2060}B` while sizing span boxes as:

- `Wide`: `[0.0, 0.0, 48.0, 12.0]`
- `A\u{2060}B`: `[48.0, 0.0, 84.0, 12.0]`

Without this source change, the styled-span path used decoded string length times the simple 0.5 advance ratio, so those boxes collapsed to simple-font estimates rather than the current CID resource metrics.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-font-cid-width-resource-spacing-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php`
  - `1 test files, 13 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfFont*Test.php' | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/FontStyleCleanerTest.php`
  - `29 test files, 864 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-font-cid-width-resource-spacing-currentbase.php`
  - emitted `cid_width_span_bbox_applied=true`, `word_spacing_span_bbox_applied=true`, `raw_font_resource_text_excluded=true`, and native-only execution flags.

## Status Delta

- `lane-status.json`: `phpPass` `910 -> 911`; `wordpressScenarios` `910 -> 911`.
- Added one focused WordPress smoke scenario for CID width/resource spacing in styled-span geometry.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted named Type0 CMap resource width grouping for plain text lines, Type0 CID 32 word-spacing for visible text grouping, Type3 CMap spacing, direct/indirect CIDFont `/W` parsing, nearest page resource scoping, Form XObject resource aliasing, FontDescriptor styled-span flags, or vertical `/W2` metrics. The new boundary is specifically native styled-span bbox width calculation from the current Type0 CID resource and text-state spacing.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, named CMap inventory, ToUnicode parser, CIDFont width parser, text-state spacing helpers, styled-span extraction path, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers; none were run for this bounded PHP slice.
