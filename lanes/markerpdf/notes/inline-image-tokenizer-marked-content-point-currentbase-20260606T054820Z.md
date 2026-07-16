# markerPDF inline image tokenizer marked-content point boundary current base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T054820Z`

Base accepted HEAD: `3020a8be3f79d45948a22ca25ae18ab50e5f7727`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through pdftext/PDFium-style content parsing before image, OCR, and model stages. At this native no-GPU boundary, `BI ... ID ... EI` inline image bytes are raster payload and must not leak into WordPress paragraph text.

PDF marked-content point operators `/Tag MP` and `/Tag props DP` are valid non-enclosing content operators. They can appear between an inline image terminator and the next text object without becoming visible text or changing the inline-image boundary.

## Behavior

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now accepts `MP` with one name tag operand and `DP` with a tag plus dictionary/name property operand when validating the safe segment between a preview-only inline-image fallback terminator and a later stray `EI`.

The focused fixture proves both forms:

- `/Artifact MP` before `BT ... (Visible MP Before Stray) ... ET`
- `/Span << /MCID 7 >> DP` before `BT ... (Visible DP Before Stray) ... ET`

Before this patch, the tokenizer kept the preview-only image open until the later stray `EI`, swallowing the text after `MP`/`DP`.

## Red Before Fix

After adding the focused test but before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
FAIL closes preview-only fallback before marked-content point text followed by stray EI operator
Expected lines included Visible MP Before Stray and Visible DP Before Stray.
Actual lines only included the later Visible After MP/DP Stray text.
1 test files, 433 assertions, 1 failures
```

## Verification

Focused test after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 447 assertions, 0 failures
```

Adjacent inline-image/image tokenizer family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
12 test files, 1874 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke metadata includes `preview_only_marked_content_point_stray_ei_text_preserved_after_safe_boundary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and emits Gutenberg paragraphs for `Visible MP Before Stray`, `Visible After MP Stray`, `Visible DP Before Stray`, and `Visible After DP Stray` without `/Artifact MP`, `/MCID 7`, raw image payload, or `rawtail` text.

Attempted broader text extractor family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 626 assertions, 2 failures
```

Those failures are in the unchanged/out-of-slice ToUnicode `usecmap` inheritance expectations (`Import Blocks` and cyclic `Import Blocks! OK`) and are not touched by this inline-image marked-content point patch.

## Non-overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI` sample floors, comments after `ID`, NUL whitespace, nested dictionary/text-object decoys, JBIG2/CCITT/DCT/JPX/unsupported-filter payload closure, slash-delimited `EI`, marked-content `/ActualText`, BMC/BDC wrappers, graphics-state/path/color/XObject/dash/text-state/compatibility stray-`EI` recovery, image preview metadata, stream filters, CMap fixes, xref repair, page geometry, annotations, forms, table/equation handoffs, or OCR/model work.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, preview-only filter fallback, marked-content operand validation, `PdfTextExtractor`, focused lane tests, and the WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
