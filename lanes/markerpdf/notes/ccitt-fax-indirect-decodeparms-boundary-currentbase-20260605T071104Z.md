# markerPDF CCITT Fax Indirect DecodeParms Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T071104Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T071104Z`
Base accepted HEAD: `d08718c5a8123be98c2fd370339e89d914192c25`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text extraction separate from raster image rendering. CCITT Fax image bytes remain image-rendering payloads, not WordPress paragraph text. Under the current no-GPU lane scope, native PHP parser review must still distinguish missing PDF operands from existing malformed PDF operands before any future raster backend runs.

For image XObject `/DecodeParms 11 0 R`, where object `11 0 obj` exists but resolves to `/NotADictionary`, the operand is malformed, not unresolved. The review metadata should fail closed as `malformed_ccitt_decodeparms_fail_closed`, preserve `decode_parms_operand=malformed_operand`, keep raster decode disabled, and keep CCITT bytes out of visible import text.

## Native Behavior Added

`PdfTextExtractor` now resolves indirect image-XObject `/DecodeParms` operands while classifying CCITT operand failures. Missing references still report `unresolved_ccitt_decodeparms_fail_closed`; existing non-dictionary operands now report `malformed_ccitt_decodeparms_fail_closed`.

The behavior is bounded to CCITT image-XObject review metadata and does not decode, rasterize, or promote CCITT payload bytes.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL marks resolved malformed indirect CCITT Fax DecodeParms operands fail closed
Actual decode_parms_review: unresolved_ccitt_decodeparms_fail_closed
Actual decode_parms_operand: unresolved_reference
1 test files, 245 assertions, 1 failures
```

Focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS marks resolved malformed indirect CCITT Fax DecodeParms operands fail closed
1 test files, 252 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-failclosed-currentbase.php
```

The smoke emits `indirect_ccitt_decode_parms_review=malformed_ccitt_decodeparms_fail_closed`, `indirect_ccitt_decode_parms_operand=malformed_operand`, `indirect_payload_in_visible_text=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1560 -> 1561`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `241 -> 252`.
- WordPress scenarios: `1449 -> 1450`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, malformed direct DecodeParms fail-closed metadata, unresolved reference fail-closed metadata, effective DecodeParms geometry metadata, escaped DecodeParms key lookup, inline CCITT review-only notes, inline invalid DecodeParms review, null filter-array alignment, Flate/Crypt prefix CCITT boundary recovery, direct EOFB/RTC ownership, nested CCITT soft-mask review, DCT/JPX/JBIG2 preview-only image filters, or generic inline image payload exclusion.

The bounded behavior here is specifically resolved-but-malformed indirect CCITT `/DecodeParms` operand classification in image-XObject review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF object resolver, image-XObject filter review, CCITT DecodeParms review metadata, and WordPress smoke renderer. Full CCITT raster parity remains gated on PDFium/PIL or a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, or live-service provider execution was run.
