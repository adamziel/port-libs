# markerPDF malformed CMap filter end-marker review

Session: `port-dev-markerpdf-malformed-cmap-20260605T112940Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T112940Z`

Base accepted HEAD: `1251bc133ab993e2642f4fd2c957e70cae634c16`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is CMap stream filter end-marker review metadata. ToUnicode CMap streams now report malformed explicit filter terminators, including decoded inner filters after an outer compression stage, before a CMap can replace fallback source text.

## Source Truth

Pinned upstream markerPDF routes searchable-PDF extraction through pdftext/PDF parser/font machinery before Marker builds page text and Markdown. Under the current no-GPU lane scope, the PHP port owns this parser boundary.

PDF filters with explicit end markers, including `ASCIIHexDecode`, `ASCII85Decode`, and `RunLengthDecode`, must have a bounded terminator when they carry CMap font programs. CMap streams are font-program inputs, so a malformed filtered CMap should fail closed and leave fallback text visible. Content-stream recovery stays lenient outside this CMap boundary.

## Implementation

`PdfTextExtractor` now classifies CMap stream filter end-marker problems while reviewing stream filter, DecodeParms, and length ownership. The review walks the filter stack in decode order, resolves per-filter DecodeParms entries, decodes intermediate stages for inspection, and reports:

- `missing_explicit_end_marker` for explicit-EOD filters without their terminator;
- `unbounded_explicit_end_marker` for explicit-EOD filters with trailing non-whitespace after the terminator;
- `missing_bounded_stream_end_marker` when a compressed filter stage cannot prove a bounded stream end before the next stage.

The metadata is exposed through `extractCMapStreamFilterLengthOwnerReview()` as per-entry `filter_end_marker_policy`, `filter_end_marker_problem_count`, and `filter_end_marker_problems`, plus an aggregate `filter_end_marker_problem_count`.

## Red-First Evidence

Before the additive stacked-filter case, the focused accepted-base CMap EOD test passed only the direct ASCIIHex boundary:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 36 assertions, 0 failures
```

The missing case was decoded inner `ASCII85Decode` CMap data after an outer valid `FlateDecode` member. Without this patch the direct CMap extraction still failed closed, but the review metadata did not identify the inner missing `~>` terminator or its filter index.

## Verification

Focused test after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 81 assertions, 0 failures
```

Adjacent CMap/filter and stream-filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1992 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-filter-eod-boundary-currentbase.php
```

The smoke emits `missing_eod_cmap_rejected=true`, `missing_eod_problem_count=1`, `valid_eod_cmap_accepted=true`, `valid_eod_problem_count=0`, `stacked_inner_ascii85_missing_eod_rejected=true`, `stacked_inner_ascii85_problem_count=1`, `stacked_inner_ascii85_filter_index=1`, `stacked_inner_ascii85_valid_eod_accepted=true`, `visible_text_excludes_cmap_program=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-filter-eod-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json")); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); }'
git diff --check -- lanes/markerpdf
```

Result: no syntax errors, valid JSON, and no whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1776 -> 1777`
- `wordpressScenarios`: `1617 -> 1618`
- Focused PASS case delta: `+1`
- Focused assertion delta: `+45`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream dictionary parser, filter resolver, DecodeParms alignment logic, CMap decoder/parser, text fallback decoder, CMap review metadata path, and WordPress smoke renderer.

Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted malformed CMap filter operands, direct ASCIIHex missing-EOD replacement blocking, current-generation filter owner selection, malformed DecodeParms rejection, indirect DecodeParms null-filter alignment, all-null CMap filter stacks, identity/private Crypt CMap filters, escaped or unsupported CMap filter names, post-`endcmap` operator exclusion, overdeclared literal-row boundaries, stale CMap stream `/Length` recovery, generic content-stream filter stack recovery, inline-image tokenizer repair, image-filter exclusion, CMap width grouping, or encrypted-PDF preflight.

The new boundary is specifically CMap review metadata for malformed filter end markers, including missing explicit inner `ASCII85Decode` EOD after an outer decoded `FlateDecode` stage.

## Next Task

Continue with non-overlapping native no-GPU markerPDF parser behavior around font/CMap widths, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
