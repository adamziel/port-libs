# CCITT Fax Extra DecodeParms Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T173010Z`

Base accepted HEAD: `3def3c127d89fb2d9ff534915066695347ee7763`

## Source Truth

- Upstream markerPDF treats CCITT Fax image content as image/filter metadata, not searchable text. This no-GPU port keeps `CCITTFaxDecode`/`CCF` review-only and does not rasterize or OCR fax payloads.
- PDF filter arrays allow `null` filter placeholders, and DecodeParms arrays may align to declared filter slots. A non-null DecodeParms slot beyond the declared filter stack is not applied to a CCITT image stage and must not make the aligned CCITT metadata look valid.
- Existing accepted coverage already handled missing CCITT DecodeParms slots and null-filter placeholder alignment. This slice adds the trailing extra non-null DecodeParms case.

## Red-First Evidence

Before the source edit, both probes reported valid CCITT DecodeParms for `/F /CCF /DP [valid-dict invalid-extra-dict]`:

- `PdfImageRenderer::inlineImageReviewPlan()` returned `k=-1`, `columns=16`, `rows=1`, and no `valid_decode_parms=false`.
- `PdfTextExtractor::extractImageXObjectBoundaryReview()` did the same for an Image XObject while still excluding the payload from text.

The bug was metadata safety: the trailing DecodeParms slot was outside the declared filter stack, so the CCITT review should fail closed with `decode_parms_alignment=unapplied_filter_slot`.

## Implementation

- `PdfImageRenderer` now checks CCITT filters for trailing non-null DecodeParms slots before accepting aligned DecodeParms metadata.
- `PdfTextExtractor` applies the same guard for Image XObject filter reviews.
- Null filter placeholder alignment remains accepted: DecodeParms aligned to declared `null` filter slots are ignored instead of failing the CCITT stage.

## Focused Evidence

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 552 assertions, 0 failures
```

Delta from the same focused file before this patch: `505 -> 552` assertions, with one new PASS case.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

Result: exits `0` and emits `inline_extra_decode_parms_fail_closed=true`, `xobject_extra_decode_parms_fail_closed=true`, `inline_extra_decode_parms_alignment=unapplied_filter_slot`, `xobject_extra_decode_parms_alignment=unapplied_filter_slot`, and payload exclusion flags set to true.

## Status Delta

- `phpPass`: `2110 -> 2111`
- `wordpressScenarios`: `1820 -> 1821`
- Manifest CCITT Fax filter boundary behaviors: `1 -> 2`

## Dependency Closure

No new support component is needed. This reuses the existing native PDF dictionary, filter-array, DecodeParms, and image-boundary review code. GPU/model OCR, Surya/Texify/Torch, external PDF tools, and live-service tests remain intentionally out of scope.

## Non-Overlap

This does not repeat the accepted null-filter DecodeParms alignment slice or the missing-slot unaligned DecodeParms checks. The new behavior is specifically the non-null trailing DecodeParms slot that sits outside the declared filter stack after a CCITT filter has already found an aligned DecodeParms dictionary.

Root harness: not run - isolated micro-slice.
