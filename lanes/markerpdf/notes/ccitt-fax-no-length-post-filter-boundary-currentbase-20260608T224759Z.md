# CCITT Fax No-Length Post-Filter Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T224759Z`
Base accepted HEAD: `c992bb947324f7207d596c6abc6496ba6a35dd32`

## Behavior

Native PDF stream parsing now applies the existing post-CCITT native-filter
owner-boundary recovery to streams without `/Length`. This covers
`/Filter [/CCITTFaxDecode /ASCIIHexDecode]` image streams where the fax bytes
reach the CCITT boundary before a stale `endstream` marker, but a later
`endstream/endobj` pair is the true owner boundary for the Image XObject.

Before the patch, a red-first probe showed a no-`/Length` CCITT Image XObject
with a fake `9 0 obj` after a stale marker leaked `Fake no length post CCITT
leak` into page text. After the patch, page text contains only the surrounding
content streams and image review records the full raw payload while marking
post-CCITT native filters as review-only.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxNoLengthPostFilterBoundaryCurrentBaseTest.php`
  - `1 test files, 47 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-no-length-post-filter-currentbase.php`
  - exits 0
  - reports `image_xobject_count=1`, `length_operand_present=false`,
    `post_ccitt_filters_block_native_decode=true`, `raw_length=136`,
    `payload_in_visible_text=false`, and `stale_object_text_excluded=true`

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
CCITT Fax boundary helpers, stream-filter decoding helpers, direct-object owner
scanner, and Image XObject review metadata. No Python, OCR/model, PDFium/PIL,
external PDF tools, GPU execution, or live service calls were used.

## Non-Overlap

This does not repeat accepted declared-`/Length` CCITT stale-boundary coverage,
CCITT/DCT image filter exclusion, inline-image CCITT boundaries, pre-CCITT
native-prefix decoding, or generic stale stream-length recovery. The new
coverage is specifically the no-`/Length` post-CCITT native-filter owner
boundary in both stream payload extraction and direct object end-offset
scanning.

## Next Task

Continue with non-overlapping native stream-filter and object-owner boundaries,
especially image/filter metadata cases that affect searchable-PDF import
fidelity without invoking model/OCR execution.
