# markerPDF inline DCTDecode filter-tail DecodeParms boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T062355Z`

Accepted base: `84117a5c5c86d914c94d81dd6883757bcb9f37e0`

## Source Truth

Upstream markerPDF keeps searchable text extraction (`marker/pdf/extract_text.py`) separate from image rendering (`marker/pdf/images.py`). Under the current no-GPU markerPDF scope, the native PHP port must keep DCTDecode/JPEG payloads review-only while preserving the metadata a later image handoff would need.

This slice covers a PDF inline-image dictionary boundary:

```pdf
BI /W 1 /H 1 /CS /RGB /BPC 8 /F [/DCT] /Crypt /DP << /ColorTransform 1 >> ID ...
```

The `/Crypt` token after the `/F` array is a malformed top-level filter-tail operand, but the following abbreviated `/DP` key is still a real inline image `/DecodeParms` declaration. The native canonicalizer now carries the malformed filter tail as part of the filter operand, then resumes at `/DP` so DCTDecode review metadata preserves `/ColorTransform 1`.

## Implementation

- `PdfImageRenderer::canonicalInlineImageDictionary()` now applies the existing direct filter-tail scanner to inline `/F`/`/Filter` values.
- Malformed inline filter tails continue to emit `MalformedFilterOperand`, `reject_malformed_filter_operands`, and `inline_image_dictionary_operand_review_only`.
- The following `/DP` dictionary remains attached to the DCTDecode filter detail instead of being swallowed as a stray operand value.

## Verification

Focused run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterTailDecodeParmsCurrentBaseTest.php
```

Result: `1 test files, 25 assertions, 0 failures`.

Adjacent DCT run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterTailDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeScalarFilterOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeIndirectFilterArrayTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
```

Result: `5 test files, 829 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-dctdecode-filter-tail-decodeparms-currentbase.php
```

Result: exits 0 and reports `inline_filter_tail_rejected=true`, `decodeparms_preserved_after_filter_tail=true`, `native_raster_decode=false`, `dctdecode_image_payload_excluded_from_text=true`, and no Python/models/external PDF tooling.

## Non-Overlap

This does not repeat accepted DCT scalar filter-tail operands, indirect filter-array tails, duplicate filters, escaped filter names, DCT aliases, DCT DecodeParms declaration/operand failures, CCITT DecodeParms dictionary-tail handling, DCT stream EOI/segment/prefix recovery, or generic stream-filter stack handling. The bounded behavior is only inline-image canonicalization after a malformed DCT `/F` array tail before a following `/DP` key.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF value scanner, inline-image abbreviation canonicalizer, DCT image filter review planner, inline image tokenizer, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL raster decoding, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
