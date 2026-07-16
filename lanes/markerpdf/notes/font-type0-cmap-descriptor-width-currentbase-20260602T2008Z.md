# Font Type0 CMap Descriptor Width Current Base

Session: `port-dev-markerpdf-font46-20260602T2008Z`
Base accepted HEAD: `9efdfcaaff05b4be1ca34b399840525efdf84f93`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates native PDF text extraction through `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The pinned upstream dependency stack in `pyproject.toml` includes `pdftext` and PDF parser dependencies used to resolve font resources before text grouping: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml
- Relevant parser dependency behavior resolves page resource font dictionaries and CMaps before text decoding/spacing: https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

## Red-First Boundary

The current base already handled indirect font resource references and indirect `/DW` width operands, but not direct font dictionaries stored inside a page `/Resources /Font` dictionary. A throwaway red check with a direct `/Fthin` Type0 resource decoded the content source bytes as control characters instead of binding `/Fthin` to its direct font dictionary:

```text
array (
  0 => "\x01\x02\x03\x04 \x05\x06\x07\x08",
)
```

That meant `/Encoding` CMap decoding, descendant CIDFont `/DW 250`, and FontDescriptor `/Flags 34` did not reach the current-base text-gap grouping path for direct Type0 resource dictionaries.

## Native Behavior Added

`PdfTextExtractor` now builds font maps from both indirect font objects and direct page resource font dictionaries. Direct `/Fthin << /Type /Font /Subtype /Type0 ... >>` resource entries are bound to their resource names, then reuse the existing CMap parser, CIDFont width metrics, ToUnicode decoding, and FontDescriptor flag review paths. The focused fixture proves `/Fthin` wins over sibling `/Fwide`, decodes `Thin Text`, preserves the word gap from `/DW 250`, excludes raw source control bytes, and reports `DirectThinSerif_serif_non_symbolic`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` - no syntax errors.
- `php -l lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php` - no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-type0-cmap-descriptor-width-currentbase.php` - no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php` - 1 selected test file, 8 assertions, 0 failures.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfFont|FontStyleCleanerTest' | tr '\n' ' ')` - 17 selected font test files, 136 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php` - 6 selected test files, 632 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-type0-cmap-descriptor-width-currentbase.php` - emitted review JSON with `direct_type0_resource_resolved`, `wrong_fallback_font_excluded`, `control_source_bytes_excluded`, and `current_base_gap_preserved` all true, followed by a Gutenberg paragraph containing `Thin Text`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native object scanner, resource parser, CMap parser, CIDFont width metrics, FontDescriptor flag parser, text grouping, and WordPress smoke paths already present in `lanes/markerpdf`. Full upstream runner parity remains blocked by the existing Python/model/runtime dependencies listed in lane status.

## Non-Overlap

This does not repeat accepted indirect font resource mapping, indirect `/DW`, `/W`, `UseCMap`, vertical metrics, CIDSet, descriptor-only default width, or simple-font width slices. The new boundary is direct Type0 font dictionaries embedded in a page resource `/Font` map before current-base WordPress paragraph extraction.
