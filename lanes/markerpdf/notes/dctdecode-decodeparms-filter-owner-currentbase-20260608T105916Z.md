# DCTDecode DecodeParms Filter Owner Boundary - 2026-06-08

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from image rendering: image stream bytes are rendered/reviewed through the image path, while text operators feed document text. For DCT/JPEG image color planning, `/DecodeParms /ColorTransform` belongs to the `/DCTDecode` filter slot, not to unrelated image filters that happen to carry a dictionary with the same key.

This no-GPU PHP slice preserves that filter-boundary ownership without rasterizing JPEGs or launching model/OCR dependencies.

## Implementation

- `PdfImageRenderer::dctDecodeImageColorPlan()` now checks that the resolved image filter stack contains `/DCTDecode` or `/DCT` before reading DCT-specific DecodeParms metadata.
- DCT DecodeParms operand failure and duplicate ColorTransform checks now return neutral values when no DCT owner exists.
- Existing DCT and `/DCT` alias stacks still apply valid ColorTransform metadata.
- Non-DCT, `null`, and omitted filter stacks no longer inherit YCCK preview decisions from stale `/DecodeParms << /ColorTransform 1 >>`.

## Red-First Evidence

Before the source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsFilterOwnerCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL does not apply DCTDecode ColorTransform DecodeParms when the image filter stack has no DCT owner (lanes/markerpdf/tests/PdfDctDecodeDecodeParmsFilterOwnerCurrentBaseTest.php)
Values are not identical
Expected: NULL
Actual: 1

1 test files, 2 assertions, 1 failures
```

## Focused Verification

After the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsFilterOwnerCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS does not apply DCTDecode ColorTransform DecodeParms when the image filter stack has no DCT owner

1 test files, 29 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-filter-owner-currentbase.php
```

The smoke exits 0 and emits metadata with `image_filters=["FlateDecode"]`, `dct_decodeparms_color_transform=null`, `dct_effective_color_transform=0`, `dct_uses_ycck_transform=false`, and `sample_rgb_after_owner_guard={"red":255,"green":0,"blue":0}`. It also preserves the surrounding WordPress paragraphs and records `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Additional focused family verification:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsDeclarationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeAliasFilterReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeMissingFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
```

Result:

```text
Focused test run: 8 selected test files (root lock skipped)
8 test files, 1487 assertions, 0 failures
```

Syntax and lane checks:

```bash
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodeDecodeParmsFilterOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-filter-owner-currentbase.php
php -r '$data=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

All syntax checks reported no errors, `lane-status.json` decoded successfully, and `git diff --check -- lanes/markerpdf` produced no output.

## Non-Overlap

This does not repeat accepted DCT filter aliases, escaped DCT names, compact/null filter arrays, direct DCT ColorTransform planning, duplicate or malformed DCT DecodeParms operands, extra/missing DecodeParms slots, indirect filter owners, post-EOI stream clipping, missing-filter raw JPEG boundary inference, prefix filter ownership, inline DCT tokenization, CCITT/JPX/JBIG2 preview-only filter metadata, CMap behavior, xref repair, OCR/model execution, or raster parity.

The bounded behavior here is specifically DCT DecodeParms ownership: a non-DCT image filter stack must not consume a DCT-only `/ColorTransform` key.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF dictionary parser, image filter resolver, DecodeParms review helpers, DCT color planning, focused PHP tests, and WordPress smoke path. Full live raster parity through PDFium/PIL and all OCR/model execution remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next Task

Continue with non-overlapping native markerPDF parser/filter behavior around xref repair, object-stream filter metadata, fonts, CMaps, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table or equation handoffs.
