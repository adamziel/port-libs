# font-type0-cidset-vertical-spacing-currentbase-20260602T223633Z

Micro-slice: `font-type0-cidset-vertical-spacing-currentbase`

Base accepted HEAD: `ba26c84773f1060ee6d968d946c818afcf0a3c26`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes digital PDF text through `pdftext.extraction.dictionary_output` before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The `pdftext` dependency is pypdfium2-backed, so the native PHP fallback has to preserve PDF font code-space and glyph advance boundaries when Python/PDFium/model execution is unavailable: https://github.com/datalab-to/pdftext
- PDF Type0 predefined UCS2 CMaps such as `/UniJIS-UCS2-V` use two-byte source character codes and vertical writing mode; CIDFont `/DW2` plus `/CIDSet` membership then drives vertical glyph displacement before line/word grouping.

## Implemented Behavior

`PdfTextExtractor::namedEncodingMap()` now recognizes predefined UCS2 CMap names shaped like `Uni*-UCS2-*-H` or `Uni*-UCS2-*-V` as two-byte source-code maps. The `-V` variants also carry vertical writing mode.

The focused fixture uses a direct `/Encoding /UniJIS-UCS2-V` Type0 font with no `/ToUnicode`, a descendant CIDFont with `/DW2`, and a compressed `/CIDSet`. Before the fix, the fallback decoded byte-sized source chunks, leaked NUL bytes, and inserted a false vertical gap (`Vert Import`). After the fix, source keys stay two-byte UCS2 CIDs, so CIDSet/default vertical displacement keeps `VertImport` and `DataFlow` grouped for WordPress paragraphs.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses predefined UCS2 vertical Type0 CMap source width before CIDSet spacing on current base
1 test files, 7 assertions, 0 failures
```

Additional focused regression coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS uses predefined UCS2 vertical Type0 CMap source width before CIDSet spacing on current base
PASS inherits predefined Type0 vertical UseCMap codespace before CIDSet width grouping on current base
PASS resolves indirect predefined vertical CMap names before width grouping and font flags
...
4 test files, 620 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-font-type0-cidset-vertical-spacing-currentbase.php
```

The smoke emits Gutenberg paragraph comments for `VertImport` and `DataFlow`, plus review metadata proving no Python/models/external PDF tools execute.

## Non-Overlap

This does not repeat direct `/Identity-V`, `/UseCMap /Identity-V`, `/W2`, explicit `/ToUnicode`, Type3 CMap, descriptor flag, simple-font encoding, or indirect `/Encoding /UniJIS-UCS2-V` with ToUnicode coverage. The new boundary is specifically no-ToUnicode direct predefined UCS2 Type0 CMap source segmentation before CIDSet vertical spacing.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF parser, stream decoder, Type0 font map assembly, CIDSet reader, vertical text grouping, and WordPress paragraph smoke path. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, OCR/PIL raster execution, and external PDF/model tooling.
