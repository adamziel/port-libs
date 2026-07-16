# markerPDF inline image tokenizer clipping-path boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T140007Z`

Base accepted HEAD: `6c7fbdbc9a9ca213f1352fe9f3decddcfc22e1de`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed page text extraction before image/OCR/model stages. At that boundary, inline image bytes from `BI ... ID ... EI` are raster payload and must not become WordPress paragraph text.

PDF content streams can resume visible text after an inline image with non-text graphics operators such as `q`, rectangle path construction, clipping (`W`/`W*`), and path clearing (`n`) before `BT`. The tokenizer fallback for open-ended preview-only inline images must recognize that bounded path setup as safe visible-content context when a later stray `EI` operator appears.

## Red First

Before the source edit, this probe dropped clipped visible text because the preview-only fallback waited until the later stray `EI`:

```text
array (
  0 => 'Before Clip Stray',
  1 => 'Visible After Clip Stray',
)
```

The fixture shape was:

```text
BI /W 128 /H 1 /IM true /F /JBIG2Decode ID
... EI BT ... (payload noise) ... ET rawtail
EI
q
60 680 260 60 re W n
BT ... (Visible Clip Path Before Stray) Tj ET
Q
EI
BT ... (Visible After Clip Path Stray) Tj ET
```

## Implementation

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now accepts a bounded set of PDF path/clipping operators before a closed text object when deciding whether an earlier preview-only inline-image fallback terminator is safe:

- path construction operands for `m`, `l`, `re`, `c`, `v`, and `y`;
- no-operand path state operators `h`, `W`, `W*`, and path-clearing/painting operators already recognized by the extractor.

The change is tokenizer-only. It does not execute images, rasterize pages, run OCR/models, or promote inline image payload text.

## Verification

Red probe before source edit:

```text
array (
  0 => 'Before Clip Stray',
  1 => 'Visible After Clip Stray',
)
```

Focused tokenizer test before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 277 assertions, 0 failures
```

Focused tokenizer test after source edit and new assertions:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 287 assertions, 0 failures
```

Adjacent inline-image/text extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 2042 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `preview_only_clip_path_stray_ei_text_preserved_after_safe_boundary=true`, keeps `Visible Clip Path Before Stray` and `Visible After Clip Path Stray`, excludes `Clip Path Payload Noise`, and reports `executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, unfiltered sample floors, tight `ID`/`EI`, comment/NUL separators, slash-delimited dictionaries, nested dictionary decoys, text-object `BI`, DCT/JPX/JBIG2/CCITT preview-only framing, unsupported filters, filter chains, post-EOD surplus handling, same-line/line-separated stray `EI`, marked-content ActualText, graphics-state `q/Q`, or `cm`-wrapped stray `EI` cases. The new behavior is specifically path/clipping operators between the real inline-image terminator and visible text before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, preview-only filter boundary logic, existing path operator knowledge in `PdfTextExtractor`, focused lane tests, and the WordPress smoke. Live OCR, Surya/Texify/Torch, PDFium rendering, external raster/OCR helpers, and exact upstream model benchmark parity remain intentionally outside this no-GPU markerPDF scope.
