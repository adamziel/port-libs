# markerPDF DCTDecode filter DecodeParms alignment boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T112236Z`

Base accepted HEAD: `e4da9ea12fd685abfa3a5046c9f4283f3dcf1004`

## Source Truth

Upstream markerPDF routes image extraction through `marker.pdf.images.render_image`, which renders PDF image regions to RGB through pypdfium/PIL after PDF stream filtering. Under the current no-GPU/no-external-renderer lane scope, the native PHP port keeps DCTDecode/JPEG raster bytes review-only while preserving enough filter, DecodeParms, and RGB-preview metadata for WordPress import decisions.

PDF stream filter arrays align `/DecodeParms` entries with the corresponding `/Filter` entries. A DCT image dictionary such as:

```pdf
<<
  /Filter [/FlateDecode /DCTDecode]
  /DecodeParms [<< /Predictor 12 /Columns 16 >> << /ColorTransform 1 >>]
>>
```

must therefore apply `/ColorTransform 1` to the DCTDecode stage, not the native Flate prefix stage.

## Implementation

`PdfImageRenderer::dctDecodeImageColorPlan()` now locates the DCTDecode/DCT filter slot in the image filter stack and resolves the aligned DecodeParms value through the existing filter-index alignment helper. This preserves:

- prefix-filter DecodeParms for native decoders such as Flate predictors;
- compact DecodeParms arrays when null filter slots precede `/DCT`;
- DCT `/ColorTransform` metadata before RGB preview planning;
- review-only DCT raster behavior without pypdfium, PIL, Python models, or external PDF tools.

The pre-edit probe showed the bug on the accepted base: a filter stack beginning with `/FlateDecode` reported `filter=FlateDecode` and `decode_parms_color_transform=null` even though the aligned DCT DecodeParms dictionary contained `/ColorTransform`.

## Verification

Focused DCT filter run:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
```

Result: `1 test files, 379 assertions, 0 failures`.

Adjacent renderer/DCT run:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php
```

Result: `4 test files, 930 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-filter-import.php
```

Result: emits `dct_filter_stack_decodeparms_aligned=true`, `dct_color_plan_filter="DCTDecode"`, `dct_color_plan_color_transform=1`, `dct_color_plan_uses_ycck_transform=true`, imports only `DCT PDF Import` and `Clean Paragraphs`, and records all Python/model/PDFium/PIL/external-tool execution flags as false.

## Non-Overlap

This does not repeat accepted DCT stream SOI/EOI terminator recovery, stale/missing `/Length` fake `endstream` rejection, Flate-prefix payload boundary recovery, null-filter slot boundary recovery, trailing null filter handling, ASCIIHex early-EOD DCT recovery, unsupported-prefix DCT fail-closed behavior, Crypt Identity DCT boundaries, APP-segment false EOI handling, inline DCT tokenizer boundaries, DCT CMYK `/Decode` sample mapping, or generic image-filter review-only classification.

The bounded new behavior is specifically renderer-side DCTDecode `/DecodeParms /ColorTransform` alignment when native prefix filters or null filter slots appear before the DCT image stage.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF dictionary parser, image filter-stack resolver, DecodeParms alignment helper, DCT color preview planner, and WordPress smoke pattern. Full DCT/JPEG raster decoding, pypdfium/PIL rendering, OCR, Surya/Texify/Torch model execution, and exact upstream image benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
