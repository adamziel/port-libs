# CCITT Fax null DecodeParms boundary current-base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260607T163050Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260607T163050Z`
Accepted base: `1d69a68f53ce21789449f52c6103c11f01fcd7a9`

## Source Truth

Upstream `sddai/markerPDF` keeps CCITT Fax image payloads out of searchable text extraction and routes raster work through image-rendering review paths. Under the no-GPU PHP lane, `/CCITTFaxDecode` and `/CCF` stay review-only. PDF `/DecodeParms null` is the no-parameter/default boundary, not a one-element DecodeParms array.

## Red-First Boundary

A focused probe against the current base showed a renderer/XObject split for:

`/Filter [/ASCIIHexDecode /CCF] /DecodeParms null`

The XObject review treated the operand as absent, but `PdfImageRenderer` produced `decode_parms_review=unaligned_ccitt_decodeparms_fail_closed` with `decode_parms_alignment=missing_filter_slot`. The same area also needed renderer parity with the extractor for DecodeParms entries aligned to `null` identity filter slots.

## Implementation

- `PdfImageRenderer::imageDecodeParmsValues()` now returns no DecodeParms slots when the scalar operand resolves to `null`, including indirect objects that resolve to `null`.
- `PdfImageRenderer::unappliedNonNullDecodeParmsSlots()` now ignores DecodeParms entries aligned to `null` filter slots, matching the extractor behavior.
- `PdfCcittFaxFilterBoundaryCurrentBaseTest.php` adds direct/indirect renderer coverage plus an XObject fixture that keeps ASCIIHex-wrapped CCITT bytes out of visible text and review JSON.
- `wordpress-pdf-ccitt-fax-filter-import.php` now emits `renderer_explicit_null_decode_parms_present=false`, `renderer_indirect_null_decode_parms_matches_direct=true`, and `xobject_explicit_null_decode_parms_payload_excluded_from_text=true`.

## Focused Evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 1062 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php`

Result: exits 0 and emits the null DecodeParms renderer/XObject smoke fields above while keeping `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, malformed/unresolved DecodeParms fail-closed handling, duplicate DecodeParms rejection, compact/null-filter array alignment after a real DecodeParms dictionary, escaped DecodeParms keys, direct EOFB/RTC ownership, row-EOL ownership, native filter prefixes, post-CCITT filter stacks, ImageMask polarity, nested masks/alternates, Type3 CharProc, or pattern image review. The bounded behavior is scalar and indirect `/DecodeParms null` parity for CCITT image-filter stacks, plus null-filter slot normalization in the renderer.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF dictionary parser, object-value resolver, image filter metadata planner, CCITT DecodeParms defaults, and existing WordPress smoke. Full CCITT raster decoding remains intentionally out of scope for this no-GPU markerPDF lane and would require a future native raster backend or explicitly authorized image/PDF backend evidence.
