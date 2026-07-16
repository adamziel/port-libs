# CCITT Fax indirect DecodeParms array-tail boundary, 2026-06-08

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T024947Z`

Accepted base: `02ca21f0a770f96178de4e85f83f87d2bf977c2c`

## Source Truth

The in-scope native PDF behavior is CCITTFaxDecode image review metadata. A `/DecodeParms` operand that resolves through an indirect object to an array with trailing top-level bytes is not a valid single PDF operand for the image filter. The no-GPU markerPDF port must not silently treat that malformed operand as absent and apply default fax dimensions; it should preserve the malformed boundary as review-only metadata, keep native raster decode disabled, and keep raster payload bytes out of imported WordPress text.

This follows the same fail-closed direction already used by the XObject extractor for malformed indirect DecodeParms operands while avoiding OCR, Surya/Texify/Torch, pypdfium, PIL, or external PDF tools.

## Implementation

- `PdfImageRenderer::imageDecodeParmsValues()` now checks balanced array-form DecodeParms after resolving indirect operands. If the array is malformed or has trailing resolved bytes, the original operand is kept as the filter DecodeParms value so CCITT review returns `malformed_ccitt_decodeparms_fail_closed` instead of disappearing as absent DecodeParms.
- `PdfCcittFaxFilterBoundaryCurrentBaseTest.php` adds one focused case covering renderer review and XObject extraction for indirect array-tail CCITT DecodeParms.
- `wordpress-pdf-ccitt-fax-indirect-decodeparms-array-tail-currentbase.php` adds a WordPress smoke where only before/after searchable text is emitted and raster payload noise is excluded.

## Evidence

- Before probe on accepted base: renderer review treated `/DecodeParms 30 0 R` resolving to `[<<...>>] <<...>>` as absent DecodeParms and applied defaults.
- Focused after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` passed with `1 test files, 1176 assertions, 0 failures`.
- Focused delta for this file: `57` to `58` PASS cases, `1157` to `1176` assertions.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-decodeparms-array-tail-currentbase.php` exited 0 with `renderer_decode_parms_review=malformed_ccitt_decodeparms_fail_closed`, `xobject_decode_parms_review=malformed_ccitt_decodeparms_fail_closed`, `payload_excluded_from_text=true`, `payload_excluded_from_review=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF operand parser, image filter boundary review, and WordPress smoke harness. Remaining CCITT raster decoding is intentionally out of scope under the current no-GPU/no-model markerPDF lane contract; this patch improves review and import boundaries only.
