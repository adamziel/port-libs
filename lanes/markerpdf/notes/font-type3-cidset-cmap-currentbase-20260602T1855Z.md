# font-type3-cidset-cmap-currentbase

Session: `port-dev-markerpdf-font40pdf-20260602T1855Z`
Base accepted HEAD: `28240b72b0f77821c5ac2cf978b4d8bf8469270e`

## Source Truth

- Upstream markerPDF at `sddai/markerPDF` keeps low-level PDF text extraction in `marker/pdf/extract_text.py` and hands `dictionary_output` blocks/spans through `pdftext_format_to_blocks()` in `marker/pdf/utils.py`, preserving font span attributes while downstream WordPress-style output uses the extracted text geometry rather than raw PDF payload streams.
- PDF parser behavior from pypdf 5.4.0 `_cmap.py` treats CMap streams as the source-code decoder boundary: CMap code-space ranges and CID mappings are parsed before text strings are converted into Unicode/glyph spans.
- Native port boundary for this slice: Type3 fonts may carry an object-valued `/Encoding` CMap plus a descriptor `/CIDSet`. The CMap source codes must map to the Type3 width CIDs before `/Widths` and `/CIDSet` default-width grouping are applied. Non-CMap Type3 descriptor `/CIDSet` payloads remain review-only and are not used as glyph-width subsets.
- Source links checked: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`, `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py`, and `https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py`.

## Red First

Command before source update:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php`

Result: failed with `Miss Wide` and `ThinJoin` spacing, proving Type3 two-byte CMap source codes were not feeding width/CIDSet CIDs.

## Implementation

- `PdfTextExtractor::fontCidEncodingMap()` now accepts Type3 font dictionaries in addition to Type0 dictionaries when the font uses an Encoding CMap.
- Type3 `/CIDSet` parsing is enabled only when `/Encoding` resolves to a decoded CMap stream, preserving the accepted Type3 CharProc CIDSet descriptor fixture where the CIDSet payload is review-only text.
- The new WordPress smoke builds a Type3 CMap/CIDSet PDF in memory and verifies `MissWide` plus `Thin Join` paragraph spacing with descriptor flags preserved and no external PDF tooling.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` -> no syntax errors
- `php -l lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php` -> no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-type3-cidset-cmap-currentbase.php` -> no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php` -> 4 test files, 36 assertions, 0 failures
- `php lanes/markerpdf/examples/wordpress-pdf-type3-cidset-cmap-currentbase.php` -> emitted `MissWide` and `Thin Join` paragraphs with all JSON smoke booleans true
- `git diff --check -- lanes/markerpdf` -> passed

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, stream decoder, CMap parser, Type3 width metrics, and CIDSet bit parser already present under `lanes/markerpdf/src`.

## Non-Overlap

This does not repeat the accepted predefined vertical CMap writing-mode slice, object-valued Type0 UseCMap base slice, indirect Type0 Encoding name slice, or Type3 CharProc/MissingWidth descriptor slice. The new boundary is specifically Type3 `/Encoding` CMap source-code CIDs plus CMap-gated descriptor `/CIDSet` default-width grouping.
