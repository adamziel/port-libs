# markerPDF DCTDecode Comment-Reference Boundary

## Scope

Upstream markerPDF separates searchable PDF text extraction from image rendering: text comes from `marker/pdf/extract_text.py` while image payloads are rendered through `marker/pdf/images.py`. Under the current no-GPU lane scope, this slice keeps DCTDecode image streams review-only and fixes the native PHP renderer/parser boundary for PDF comments inside indirect-reference operands.

PDF comments are whitespace. A stream dictionary can legally spell references as:

```pdf
/Filter 10 % filter reference comment
 0 R
/DecodeParms 11 % decodeparms reference comment
 0 R
```

Before this patch, `PdfImageRenderer` used a plain whitespace regex for indirect references, so comment-split DCT `/Filter` and `/DecodeParms` operands were classified as `MalformedFilterOperand`. After this patch, renderer value tokenization and reference resolution use a tokenized indirect-reference reader that treats PDF comments as whitespace, matching the existing `PdfTextExtractor` behavior.

## Behavior

- resolves comment-split `/Filter` references to `/DCTDecode`;
- resolves comment-split `/DecodeParms` references to the DCT ColorTransform dictionary;
- keeps DCT image payload bytes review-only and out of WordPress paragraphs;
- preserves JPEG boundary recovery around fake nested `endstream` and object payload text;
- does not invoke Python, pypdfium/PIL, OCR/model code, or external PDF tools.

## Evidence

Red-first probe before the source edit:

```bash
php -r 'require "tools/bootstrap.php"; ... PdfImageRenderer::imageColorSpaceSoftMaskPlan(...) ...'
```

Observed renderer output:

```php
array (
  0 =>
  array (
    0 => 'MalformedFilterOperand',
  ),
  ...
)
```

Focused test after the patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS resolves comment-split DCTDecode filter references before renderer and WordPress review boundaries

1 test files, 28 assertions, 0 failures
```

Adjacent DCT/image regression run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
```

Result:

```text
4 test files, 938 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-comment-reference-boundary-currentbase.php
```

The smoke emitted `filter_reference_comments_resolved=true`, `decodeparms_reference_comments_resolved=true`, `stream_filters=["DCTDecode"]`, `dctdecode_color_transform=1`, `dctdecode_image_payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax and diff checks:

```bash
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-comment-reference-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed. Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct/indirect DCT filter-name resolution, DCT null-filter slots, trailing null filters, malformed filter operands, Crypt Identity, unsupported prefix filters, Flate/ASCIIHex/RunLength DCT prefix boundary recovery, APP-segment false EOI handling, inline DCT/JPEG boundaries, CMYK/YCCK ColorTransform planning, CCITT DecodeParms alignment, or generic stream-filter comment-reference handling in `PdfTextExtractor`.

The bounded behavior is specifically `PdfImageRenderer` resolving PDF-comment-split indirect `/Filter` and `/DecodeParms` operands before DCTDecode image review metadata and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, image filter metadata planner, DCT JPEG boundary recovery, text extractor, and WordPress smoke renderer. Full raster JPEG decoding, pypdfium/PIL preview parity, OCR/model execution, and exact upstream model benchmarks remain intentionally outside this no-GPU markerPDF slice.
