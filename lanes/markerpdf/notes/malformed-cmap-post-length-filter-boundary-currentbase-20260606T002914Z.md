# malformed CMap post-Length filter boundary current-base

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T002914Z`
- Accepted base: `a0f9a4e8486a1870b3b58c910a9dc3a6b97dbb35`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to PDF parser behavior that must honor stream dictionaries and CMap filters before converting text. In the native PHP port, a scalar stream `/Filter` is valid only as one operand; any additional decoder-name, dictionary, literal, or other operand after that scalar filter is malformed and must not be used to decode a ToUnicode CMap payload.

## Behavior

Before this patch, `<< /Filter /FlateDecode /Length N /ASCIIHexDecode >>` was treated as a resolved scalar Flate CMap filter because the extra-operand scanner stopped when it reached `/Length`. The compressed malicious CMap decoded and replaced current-base fallback text with its mapped leak.

This patch keeps scanning after ordinary key/value pairs such as `/Length N`, and rejects dangling post-Length extra operands after scalar `/Filter` declarations:

- `/ASCIIHexDecode` decoder-name smuggling
- unkeyed dictionary operands
- unkeyed literal operands

The failure is exposed in `extractCMapStreamFilterLengthOwnerReview()` as `filter_resolution_failed=true`, `filter_operand_policy=reject_malformed_filter_operands`, and `decoded_cmap_count=0`, while WordPress-visible fallback text remains available from the searchable content stream.

## Evidence

Red-first probe before source edit:

- Fixture: `/Filter /FlateDecode /Length N /ASCIIHexDecode`
- Observed text: leaked CMap payload
- Observed review: `decoded_cmap_count=1`, `filter_operand_policy=filters_resolved`, `filters=["FlateDecode"]`

Focused verification after source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php
```

Result: `5 test files, 1935 assertions, 0 failures`.

Direct focused file delta:

- Before: `PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php` passed with `1389` assertions.
- After: direct file passed with `1523` assertions.
- New focused PASS case: `rejects scalar CMap Filter followed by post-Length extra operands before current-base text extraction`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-post-length-filter-boundary-currentbase.php
```

Result: emitted `markerpdf-malformed-cmap-post-length-filter-boundary-currentbase-smoke` with safe text preserved, CMap payload excluded, `decoded_cmap_count=0`, and `reject_malformed_filter_operands`.

## Non-Overlap

This slice does not touch OCR, Surya/Texify/Torch, model execution, xref repair, font-width metrics, CMap end-marker decoding, duplicate filter declarations, null-filter DecodeParms alignment, or object owner selection. It only closes the post-`/Length` scalar CMap `/Filter` extra-operand boundary.

## Dependency Closure

No new dependency or support component is needed. The patch reuses the native PHP stream dictionary scanner, CMap review surface, and zlib fixture compression already present in the markerPDF lane. No GPU/model execution, external PDF tools, Python subprocesses, or live services were used.

## Exclusion

`PdfTextExtractorTest.php` still has two baseline UseCMap inheritance failures in this worktree; an accepted-HEAD extractor replay reproduced the same outputs, so they are not caused by this slice and were not counted as focused verification for this malformed filter boundary handoff.
