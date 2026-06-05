# markerPDF CCITT Fax malformed owner boundary

Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T074627Z`

## Source truth

- Upstream `sddai/markerPDF` pinned in the lane manifest keeps searchable text extraction on `marker/pdf/extract_text.py::get_text_blocks()` / `naive_get_text()` and image rendering on `marker/pdf/images.py::render_image()` / `render_bbox_image()` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Official source checked for this slice:
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- Under the current no-GPU lane scope, CCITT Fax image bytes remain raster payload, not visible WordPress paragraph text. Native PHP review may use PDF CCITT terminal markers to keep stream ownership closed, but still must not claim raster decode.

## Behavior

Malformed CCITT `/DecodeParms` already fail closed in review metadata. This slice fixes the adjacent stream-owner boundary: if malformed CCITT parameters such as `/K /Bad` prevent trusted coding-mode selection, the parser now uses conservative CCITT terminal-marker candidates (`EOFB` and `RTC`) to keep fake `endstream` / `obj` tokens inside the image stream owner.

The focused fixture makes page `/Contents` reference fake object `9 0 R`, where object `9 0 obj` exists only inside a malformed CCITT image payload after a stale fake `endstream`. Before the fix, text extraction imported `Fake invalid owner CCITT leak` and image review reported `raw_length=2`. After the fix, only the real before/after page text is imported, the image review keeps `raw_length` equal to the full fax payload, and `/DecodeParms` remains `invalid_ccitt_decodeparms_fail_closed`.

## Evidence

Red-first probe before the patch:

```bash
php -r 'require "tools/bootstrap.php"; /* builds malformed /CCITTFaxDecode /DecodeParms << /K /Bad ... >> fixture with page /Contents [4 0 R 9 0 R 6 0 R] */'
```

Observed output before the patch included:

```text
Before invalid owner CCITT
Fake invalid owner CCITT leak
After invalid owner CCITT
raw_length=2 expected 140
```

Focused test after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 266 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `xobject_invalid_owner_boundary_repaired=true`, `xobject_invalid_owner_decode_parms_review=invalid_ccitt_decodeparms_fail_closed`, `xobject_invalid_owner_payload_excluded_from_text=true`, `xobject_invalid_owner_payload_excluded_from_review=true`, and only the expected WordPress paragraphs `CCITT Boundary` and `Native Import`.

## Non-overlap

This does not repeat accepted CCITT image-only stream exclusion, inline/XObject DecodeParms extraction, malformed/unresolved DecodeParms metadata, escaped DecodeParms keys, null-filter DecodeParms alignment, identity `/Crypt` prefix recovery, direct EOFB/RTC ownership with valid DecodeParms, coding-mode metadata, nested mask/alternate image review, ImageMask polarity, DCT/JPX/JBIG2 preview filters, or generic inline image payload exclusion.

The new behavior is specifically malformed CCITT DecodeParms stream-owner recovery that prevents nested fake objects inside image bytes from becoming page content.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, stream filter resolver, CCITT DecodeParms parser, terminal-marker boundary helpers, image XObject review path, and WordPress smoke renderer. Full CCITT raster decoding remains intentionally out of scope for this no-GPU markerPDF lane and would require a future native raster backend or explicitly authorized PDFium/PIL-style rendering dependency.
