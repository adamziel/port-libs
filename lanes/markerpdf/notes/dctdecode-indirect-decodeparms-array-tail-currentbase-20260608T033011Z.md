# markerPDF DCTDecode indirect DecodeParms array-tail current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF extracts searchable PDF text before OCR/model stages. In the native no-GPU PHP lane, DCTDecode JPEG payloads stay preview/review-only and `/DecodeParms` must be owned by the matching filter slot before any color-transform preview metadata is trusted.
- PDF filter arrays can use `/DecodeParms` arrays aligned to filter slots. If a `/DecodeParms` indirect object resolves to an array followed by extra top-level operands, the operand is malformed and must fail closed instead of silently applying the first dictionary.

## Change

- `PdfImageRenderer` now adds DCT-specific operand detail when `/DecodeParms` resolves to a malformed array with trailing top-level operands:
  - `decode_parms_operand_detail=array_with_trailing_operands`
  - `decode_parms_array_policy=reject_top_level_decodeparms_array_tail`
- `PdfTextExtractor` emits the same detail on Image XObject review rows, so renderer planning and WordPress import review agree.
- Added a focused fixture where `/DecodeParms 20 0 R` resolves to `[<< /ColorTransform 1 >>] << /ColorTransform 0 >>`. The DCT color transform is ignored, the image remains review-only, and embedded JPEG text-looking bytes stay out of visible text and review JSON.
- Added `examples/wordpress-pdf-dctdecode-indirect-decodeparms-array-tail-currentbase.php` to show the WordPress path without Python, OCR, pypdfium/PIL, model workers, raster decoding, or external PDF tools.

## Evidence

Before probe on accepted base `1a3460ad1b2631816d364821ee7b4164fb87413c` showed the renderer and XObject review only reported generic `decode_parms_operand=malformed_operand` for the indirect array-tail object, with no array-tail policy detail.

Focused verification after the change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on unresolved DCTDecode DecodeParms indirect operand before RGB preview planning
PASS fails closed on malformed indirect DCTDecode DecodeParms operand before RGB preview planning
PASS reports indirect DCTDecode DecodeParms arrays with trailing operands as array-tail boundaries

1 test files, 79 assertions, 0 failures
```

The direct focused file moved from 2 PASS cases / 52 assertions to 3 PASS cases / 79 assertions, for +1 PASS case and +27 assertions.

Companion DCT filter boundary check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 706 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-indirect-decodeparms-array-tail-currentbase.php
```

Emits metadata with `renderer_decode_parms_review=malformed_dctdecode_decodeparms_fail_closed`, `xobject_decode_parms_review=malformed_dctdecode_decodeparms_fail_closed`, `decode_parms_operand_detail=array_with_trailing_operands`, `decode_parms_array_policy=reject_top_level_decodeparms_array_tail`, `payload_excluded_from_text=true`, `payload_excluded_from_review=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted DCTDecode direct/indirect filter tail handling, escaped filter names, duplicate filter declarations, unresolved/generic malformed DecodeParms operands, duplicate DecodeParms declarations, duplicate ColorTransform parameters, missing/extra DecodeParms slot alignment, trailing null filter slots, native prefix JPEG boundary recovery, post-DCT filter reachability, inline DCT tokenization, CCITT Fax array-tail handling, JPX/JBIG2 image-filter boundaries, OCR/model work, or raster execution. The bounded behavior is specifically DCTDecode `/DecodeParms` indirect operands that resolve to an array followed by trailing top-level bytes.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, indirect object resolver, image-filter parser, DCTDecode preview-only metadata path, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, JavaScript/PDF action execution, raster decoding, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue no-GPU markerPDF work on non-overlapping native PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
