# font-cmap-usecmap-vertical-width-currentbase-20260602T180302Z

Micro-slice: `font-cmap-usecmap-vertical-width-currentbase-20260602T180302Z`

Base accepted HEAD: `25465d4bad4c4ed7e39379fb65c3e5365a4df98d`

## Source Truth

- Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` is a PDF-to-Markdown pipeline that prioritizes native text extraction before OCR/model fallback for digital PDFs: https://github.com/sddai/markerPDF
- PDF CMap source truth: PDF CMap stream dictionaries carry `/CMapName`, `/WMode`, and optional `/UseCMap`; `/UseCMap` can be a predefined CMap name or a CMap stream used as the base. Adobe PDF Reference 1.2, Table 7.14 and Example 7.6: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.2.pdf
- Adobe CMap/CIDFont source truth: `usecmap` is applied before range operations, and the using file adopts base codespace and mappings unless redefined. `WMode 1` selects vertical writing. Adobe CMap and CIDFont Files Specification, Example 14 and writing-mode sections: https://www.adobe.com/content/dam/acom/en/devnet/font/pdfs/5014.CIDFont_Spec.pdf
- PDF vertical CIDFont source truth: `/DW2` and `/W2` apply to CIDFonts used for vertical writing and define vertical displacement/position vectors for CID ranges; Adobe's Acrobat SDK API reference summarizes these PDF Reference entries for CIDFont vertical metrics: https://opensource.adobe.com/dc-acrobat-sdk-docs/acrobatsdk/apireference/PDFEdit_Layer/PDSysFont.html

## Implemented Behavior

`PdfTextExtractor` now preserves CMap stream dictionary metadata before parsing decoded CMap payloads:

- `/UseCMap /Name` or `/UseCMap <stream-ref>` is converted into a synthetic `/<Name> usecmap` prelude so existing CMap inheritance applies before local mappings.
- `/CMapName /Name` and `/WMode 0|1` from the stream dictionary are converted into ordinary CMap definitions.
- If the derived stream lacks `/WMode` but dictionary `/UseCMap` points at a predefined or named vertical base such as `Identity-V` or `*-V`, inherited writing mode is exposed to the parser.
- Multiple `/WMode` declarations now use the last declaration so local stream data can override the synthetic dictionary prelude.

The focused fixture builds a Type0 font whose derived `/Encoding` CMap stream has dictionary `/UseCMap /WPVerticalBase-V` but no inline `usecmap` or inline `/WMode`; the base CMap stream dictionary carries `/WMode 1`; descendant CIDFont `/W2` widths then group vertical text into `VertImport` and `DataFlow`.

## Red-First Evidence

Before the source change, the focused test failed because dictionary `/UseCMap` and base `/WMode` were discarded with the stream dictionary:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php
1 test files, 1 assertions, 1 failures
Expected: ['VertImport', 'DataFlow']
Actual: ['Vert', 'Import', 'Data', 'Flow']
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
7 test files, 642 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-usecmap-vertical-width-currentbase.php
dictionary_usecmap_applied=true
vertical_wmode_inherited=true
base_cid_widths_selected=true
derived_cid_widths_selected=true
paragraphs: VertImport, DataFlow
```

## Non-Overlap

This does not repeat accepted inline `usecmap`, ToUnicode `usecmap` inheritance/cycle/comment/row-count handling, named Type0 CMap resource resolution, direct or indirect CIDFont `/W`, `/DW`, `/W2`, `/DW2` parsing, predefined `Identity-V`/`-V` writing-mode detection, CIDSet vertical fallback, simple-font width/encoding, Type3 width, or current xref/font-resource boundary work.

The new edge is specifically top-level CMap stream dictionary metadata preservation for `/UseCMap`, `/CMapName`, and `/WMode` before vertical CIDFont `/W2` grouping.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF stream/object parser, CMap parser, named CMap inheritance, Type0 Encoding CMap CID mapping, and CIDFont vertical width machinery. No Python, OCR/model, pdftext, pypdfium, raster, action execution, or external PDF tool path is introduced.

## Follow-Up

Next useful font/CMap work: extend the same dictionary-metadata path to more indirect CMap object inheritance fixtures, including object-valued dictionary `/UseCMap` streams with local `/WMode` override and additional vertical displacement edge cases.
