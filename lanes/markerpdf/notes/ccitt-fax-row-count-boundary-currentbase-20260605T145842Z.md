# markerPDF CCITT Fax Row-Count Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T145842Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T145842Z`
Base accepted HEAD: `920335d6a4021eee31c29abfc5414a35655718b0`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text extraction separate from raster image rendering. CCITT Fax bytes remain image payload, not WordPress paragraph text. Under the no-GPU markerPDF scope, the native PHP port does not rasterize CCITT bytes, but it must keep stream and inline-image token boundaries closed until parser-side fax framing is complete.

For CCITT DecodeParms with `/EndOfBlock false` and `/EndOfLine true`, row EOL markers are row separators. A first row EOL is not the complete image boundary when `/Rows 2` declares more rows. A stale `/Length`, fake `endstream/endobj`, or fake inline `EI` after row one must therefore stay inside the fax payload.

## Behavior

`PdfTextExtractor` now routes direct stream and inline-image CCITT completion through a shared boundary check. When DecodeParms expose EOL row framing, the parser requires at least the declared positive `/Rows` count of row EOL markers and a final row EOL before accepting the CCITT image payload as complete. Unknown row count preserves the previous one-EOL boundary, and native prefix-filter recovery keeps its existing fallback for non-empty decoded fax bytes without explicit markers.

This maps:

- XObject `/CCITTFaxDecode` streams with stale `/Length` after row one;
- inline `/CCF` images with a fake `EI` after row one;
- review-only image metadata with `native_raster_decode=false`;
- visible WordPress text isolation without Python, OCR, model, PDFium, PIL, or external PDF tools.

## Red-First Evidence

Before the source change, an ad-hoc current-base probe for a `/Rows 2 /EndOfLine true /EndOfBlock false` XObject returned:

```text
array (
  0 => 'Before multirow CCITT',
  1 => 'Fake multirow CCITT owner leak',
  2 => 'After multirow CCITT',
)
raw_length=5, expected_full_payload_length=139
```

The matching inline probe exposed:

```text
array (
  0 => 'Before inline multirow CCITT',
  1 => 'Inline first row CCITT leak',
  2 => 'After inline multirow CCITT',
)
```

## Verification

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires declared CCITT row count before row EOL stream ownership
PASS requires declared inline CCITT row count before accepting row EOL tokenizer boundaries

1 test files, 454 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-row-count-boundary-currentbase.php
```

The smoke exits 0 and emits `row_count_owned_before_fake_object=true`, `stale_first_row_payload_excluded_from_visible_text=true`, `stale_first_row_payload_excluded_from_review=true`, `inline_first_row_ei_ignored_until_declared_rows=true`, `decoded_with_current_filters=false`, `native_raster_decode=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `2013 -> 2015`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `429 -> 454`.
- WordPress scenarios: `1745 -> 1746`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw/effective DecodeParms extraction, malformed/unresolved DecodeParms operands, escaped DecodeParms keys, null-filter DecodeParms alignment, direct EOFB/RTC ownership, single-row EOL ownership, Flate/Crypt/RunLength native-prefix boundaries, CCF alias metadata, post-CCITT filter review, nested CCITT soft-mask/mask/alternate review, ImageMask polarity, DCT/JPX/JBIG2 preview-only image filter boundaries, or generic inline image payload exclusion. The bounded behavior is specifically declared multi-row CCITT EOL completion before stream and inline-image token ownership.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream dictionary reader, DecodeParms parser, CCITT stream-boundary detector, inline-image tokenizer, image-XObject review metadata path, and WordPress smoke renderer. Full CCITT raster decoding remains intentionally out of scope under the current no-GPU markerPDF directive.
