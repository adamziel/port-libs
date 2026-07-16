# DCTDecode DecodeParms Operand Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T120857Z`

Accepted base: `320834e3f5a84eac0ff7175f22518e217bb5a209`

## Source Truth

- PDF image streams may declare `/DCTDecode` with `/DecodeParms` as a dictionary, `null`, or an array aligned to the `/Filter` array.
- Upstream markerPDF/PDFium treats JPEG bytes as image payload, not searchable text. This native no-GPU port keeps that boundary and reports review metadata when a JPEG stream cannot be safely decoded natively.
- This slice stays in the markerPDF no-GPU scope: it does not run OCR, Surya, Texify, Torch, PDFium, PIL, Python workers, or external PDF tools.

## Implementation

- `PdfImageRenderer` now fails closed when a DCT `/DecodeParms` operand is unresolved or resolves to a non-dictionary operand.
- DCT filter details now report `decode_parms_review` as `unresolved_dctdecode_decodeparms_fail_closed` or `malformed_dctdecode_decodeparms_fail_closed`, with `invalid_decode_parms_fields=["decode_parms_operand"]`.
- `dctDecodeImageColorPlan()` now treats those operands as invalid before applying `/ColorTransform`, keeping CMYK JPEG preview planning at the safe RGB fallback.
- `PdfTextExtractor` now exposes the same DCT operand review in Image XObject boundary metadata, matching the renderer path while preserving existing CCITT operand review behavior.
- The adjacent DCT boundary expectation now reflects the existing richer Flate predictor DecodeParms metadata on the extra-slot fixture.

## Evidence

Red-first source probe before implementation:

`php -r 'require "tools/bootstrap.php"; $r=new \PortLibs\MarkerPDF\PdfImageRenderer(); $dict="<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms 99 0 R >>"; var_export([$r->imageColorSpaceSoftMaskPlan($dict)["image_filter_details"][0]["decode_parms"] ?? null, $r->dctDecodeImageColorPlan($dict, "\xff\xd8\xff\xd9")["decode_parms_color_transform_valid"] ?? null]);'`

Result before the patch: `decode_parms` was `null` and `decode_parms_color_transform_valid` was `true`, so malformed DCT DecodeParms operands were indistinguishable from absent operands.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsOperandBoundaryCurrentBaseTest.php`

Result: `1 test files / 52 assertions / 0 failures`.

Adjacent DCT DecodeParms commands:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`

Result: `1 test files / 661 assertions / 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsDeclarationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php`

Result: `2 test files / 52 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-dctdecode-decodeparms-operand-currentbase.php`

Result: exits 0 with `decode_parms_review=unresolved_dctdecode_decodeparms_fail_closed`, `decode_parms_operand=unresolved_reference`, `payload_excluded_from_paragraphs=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

- `php -l lanes/markerpdf/src/PdfImageRenderer.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfDctDecodeDecodeParmsOperandBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-decodeparms-operand-currentbase.php` => no syntax errors.
- `git diff --check -- lanes/markerpdf` => clean.

## Non-Overlap

This does not repeat existing DCT coverage for filter aliases, compact filter arrays, duplicate DecodeParms declarations, extra/missing DecodeParms slots, duplicate `/ColorTransform`, invalid `/ColorTransform`, prefix filters, post-EOI surplus, APP/SOS segment parsing, inline images, or malformed `/Filter` operands. This slice covers unresolved and malformed `/DecodeParms` operands specifically.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF tokenizer, Image XObject review metadata, DCT preview planning, and WordPress paragraph smoke paths. Live OCR, model execution, pypdfium/PIL rasterization, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Next Task

Continue with non-overlapping native markerPDF parser/filter behavior, especially stream-filter metadata and image DecodeParms boundaries where malformed operands should be review-only without leaking raster payload bytes into searchable text.
