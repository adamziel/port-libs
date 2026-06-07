# DCTDecode scalar /Filter operand boundary current base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260607T061401Z`

Base accepted HEAD: `0b156309dc95b4072c2ccb7cc4b489a6967b1646`

## Source-truth scope

MarkerPDF is handled here as a native no-GPU searchable-PDF parser/converter.
The upstream-aligned behavior is review-only Image XObject handling for DCT/JPEG
streams: image bytes must not become visible document text, malformed filter
operands must fail closed, and DCT metadata can still be surfaced for no-GPU
review and stream-boundary recovery.

This slice covers a scalar `/Filter /DCTDecode` value followed by an extra
top-level operand before `/Length`, for example:

```pdf
<< /Type /XObject /Subtype /Image /Filter /DCTDecode /Crypt null /Length 36 >>
```

## Red-first evidence

Before the source change, the focused test showed the missing boundary:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeScalarFilterOperandBoundaryCurrentBaseTest.php
```

Result before fix: `1 test files / 8 assertions / 1 failure`.
The failing assertion expected `["MalformedFilterOperand","DCTDecode"]` but the
existing extractor reported only `["DCTDecode"]`.

## Implementation

- `PdfTextExtractor` now detects extra direct scalar, array, null, or reference
  `/Filter` operands and prepends a synthetic `MalformedFilterOperand` sentinel
  while preserving the recognized DCT filter.
- `PdfImageRenderer` now mirrors that filter-boundary metadata for Image XObject
  review, color-space/soft-mask plans, and ICC preview rows.
- Both extractor and renderer keep raw DCT preview boundary recovery active when
  `MalformedFilterOperand` precedes `DCTDecode`, so a stale declared `/Length`
  and fake `endstream` inside JPEG bytes cannot truncate the review payload.
- Existing DCT DecodeParms ordering is preserved so non-boundary DCT review
  behavior remains stable.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeScalarFilterOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
```

Result after fix: `2 test files / 753 assertions / 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-scalar-filter-operand-currentbase.php
```

Result after fix: exits `0` with
`scalar_filter_extra_operand_rejected=true`,
`dct_review_preserved_after_reject=true`,
`raw_dct_preview_boundary=true`,
`stale_length_fake_endstream_rejected=true`,
`dctdecode_image_payload_excluded_from_text=true`, and
`renderer_boundary_matches_xobject=true`.

```sh
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodeScalarFilterOperandBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-scalar-filter-operand-currentbase.php
```

Result after fix: all report `No syntax errors detected`.

```sh
git diff --check -- lanes/markerpdf
```

Result after fix: exits `0` with no whitespace errors.

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP
PDF tokenizer/filter stack and DCT/JPEG marker-boundary recovery. It does not
invoke Python, OCR/model workers, GPU libraries, pypdfium, PIL, external PDF
tools, or live services.

## Non-overlap

This does not repeat the accepted DCT prefix, null filter, duplicate filter,
escaped name, post-filter operand, malformed stream-stack, or Image XObject
BBox operand-tail slices. The owned behavior is specifically scalar
`/Filter /DCTDecode` followed by extra top-level operands before `/Length`.
