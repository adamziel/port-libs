# markerPDF Inline Image Slash-Delimited EI Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T035109Z`

Base accepted HEAD: `538c88716b104335d6dc0713aa79af39ad7bf148`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through `marker/pdf/extract_text.py` into pdftext/PDFium-backed extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, and parsing must resume at following content-stream tokens.

PDF name operands can legally start immediately after an operator because `/` is a delimiter. This slice maps the current-base case where an inline image terminator is followed by an image/form name operand such as `EI/Decorative Do`.

## Red First

Before the source edit, this probe swallowed text after the inline image:

```text
BT /F1 12 Tf 72 720 Td (Before Slash EI Boundary) Tj ET
BI /W 1 /H 1 /CS /G /BPC 8 ID
x
EI/Decorative Do
BT /F1 12 Tf 72 704 Td (After Slash EI Boundary) Tj ET
```

Observed output:

```text
array (
  0 => 'Before Slash EI Boundary',
)
```

## Implementation

`PdfTextExtractor::inlineImageEndMarkerAt()` now treats `/` as a legal delimiter after `EI` by reusing the existing bare-token delimiter check. That closes the inline image before `/Decorative Do`, preserving the following text object while still excluding the image payload and XObject name from visible WordPress paragraphs.

## Verification

Focused tokenizer test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 135 assertions, 0 failures
```

Adjacent inline-image/text extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1651 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `slash_after_inline_ei_closes_before_name_operand=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, early unfiltered sample-floor `EI` validation, compact slash-delimited inline dictionaries, nested dictionary decoys, ASCII85/Flate/LZW/RunLength DecodeParms boundaries, inline DCT/JPX/JBIG2/CCITT preview-only framing, unsupported `/Crypt` filters, malformed filter operands, named ColorSpace fallback, visible `EI` literal recovery, object-stream inline-image repair, or image XObject payload exclusion.

The bounded behavior is specifically slash as the delimiter after the `EI` inline-image terminator before a following PDF name operand.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline image dictionary parser, PDF token delimiter helpers, `PdfTextExtractor`, focused lane tests, and the existing WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
