# markerPDF CCITT Fax DecodeParms Alignment Boundary

## Source truth

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text extraction separate from raster image rendering. CCITT Fax image bytes belong to the image-rendering/PDFium path, not WordPress paragraph text. Under the current no-GPU markerPDF scope, this PHP lane keeps those bytes review-only while recording parser metadata needed by a future raster backend.

PDF stream `/DecodeParms` arrays align with `/Filter` entries. Null entries are explicit default placeholders. A malformed short array such as:

```pdf
/Filter [/ASCIIHexDecode /CCF]
/DecodeParms [<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false >>]
```

does not provide a DecodeParms slot for the CCITT filter. Before this slice, the native image review treated CCITT DecodeParms as absent and silently applied CCITT defaults. That made review metadata report `decode_parms_present=false` and default `columns=1728` even though the file declared non-null DecodeParms.

## Implementation

`PdfImageRenderer` and `PdfTextExtractor` now preserve the DecodeParms slot index separately from the value. When a CCITTFaxDecode/CCF filter has no aligned slot while the declared DecodeParms list contains a non-null operand, the review metadata records:

- `decode_parms_review=unaligned_ccitt_decodeparms_fail_closed`;
- `invalid_decode_parms_fields=["decode_parms_alignment"]`;
- `decode_parms_alignment=missing_filter_slot` or `unapplied_filter_slot`;
- the declared filter and DecodeParms slot counts.

Valid defaults are unchanged for absent DecodeParms, explicit `null` DecodeParms slots, and previously accepted compact arrays that align after null filter entries.

## Red-first evidence

Probe before the parser change:

```bash
php -r 'require "tools/bootstrap.php"; $r=new PortLibs\MarkerPDF\PdfImageRenderer(); $plan=$r->inlineImageReviewPlan("/W 16 /H 1 /IM true /F [/ASCIIHexDecode /CCF] /DP [<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false >>]", "ABCDEF> EI noise"); var_export($plan["ccitt_fax_decode_boundary"]);'
```

The probe reported `decode_parms_present=false`, `invalid_decode_parms=false`, and default effective CCITT `columns=1728`.

## Verification

Focused test after fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
```

Result: `1 test files, 386 assertions, 0 failures`.

The focused file adds two new PASS cases and 25 assertions over the pre-slice run (`22 PASS / 361 assertions` to `24 PASS / 386 assertions`).

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-alignment-currentbase.php
```

The smoke exits 0 and emits `decode_parms_review=unaligned_ccitt_decodeparms_fail_closed`, `payload_excluded_from_visible_text=true`, `decoded_with_current_filters=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted CCITT image-only stream exclusion, raw/effective DecodeParms extraction, malformed/unresolved DecodeParms operands, escaped DecodeParms keys, null filter-slot alignment, direct EOFB/RTC ownership, row EOL ownership, prefix-filter CCITT boundaries, CCF alias metadata, post-CCITT filter review, nested CCITT soft-mask/mask/alternate review, or DCT/JPX/JBIG2 preview-only image filter boundaries. The bounded behavior is specifically malformed DecodeParms array alignment where declared non-null DecodeParms cannot map to the CCITT filter slot.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, filter-stack resolver, DecodeParms alignment logic, image/XObject review metadata, and WordPress smoke pattern. Full CCITT raster parity remains gated on PDFium/PIL or a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, or live-service provider execution was run.
