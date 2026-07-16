# markerPDF CMap filter decode-error boundary current-base

Session: `port-dev-markerpdf-malformed-cmap-20260605T142521Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T142521Z`

Base accepted HEAD: `6c126186066ceb7460fca9cb3fcff42503b6c891`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser and
WordPress import review path. It does not run OCR, Surya, Texify, Torch,
PDFium, pypdfium, Python helpers, model workers, or external PDF tools.

The bounded behavior is a CMap stream with a valid filter operand, syntactically
valid DecodeParms, and a resolved filter stream boundary, but a decoder failure
after the boundary check. In this case the PHP fallback must keep source text,
exclude decoded CMap payload text, and expose explicit review metadata for the
filter decode failure rather than reporting only that no CMap decoded.

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through pdftext/PDFium font
and CMap decoding before Markdown generation. Under the current no-GPU lane
scope, the native PHP fallback owns the parser boundary before WordPress import:
malformed filtered CMap payloads must fail closed and must remain reviewable
without exposing raw CMap program bytes as paragraphs.

## Behavior

`PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()` now reports CMap
filter decode failures that happen after filter support, DecodeParms validation,
and stream-boundary checks have all passed:

- aggregate `filter_decode_error_count`
- per-entry `filter_decode_error_count`
- per-entry `filter_decode_policy`
- per-entry `filter_decode_errors`

The focused fixture uses `/Filter /FlateDecode` with `/DecodeParms << /Predictor
12 /Columns 127 /Colors 1 /BitsPerComponent 8 >>`. The Flate byte stream is
complete, so `filter_end_marker_policy=filter_end_markers_resolved`; the
DecodeParms values are syntactically valid, so
`invalid_decodeparms_parameter_count=0`; but the inflated CMap bytes are not a
valid PNG predictor row stream, so decoding fails after the resolved boundary.

WordPress-visible text remains `Predictor Decode Safe Import`, while
`Predictor Decode CMap Leak` and the CMap name stay out of paragraphs.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reports predictor CMap filter decode errors after resolved filter boundaries
Values are not identical
Expected: 1
Actual: NULL

1 test files, 1139 assertions, 1 failures
```

## Verification

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reports predictor CMap filter decode errors after resolved filter boundaries

1 test files, 1165 assertions, 0 failures
```

Adjacent CMap/DecodeParms review family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1430 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-predictor-decode-boundary-currentbase.php
```

The smoke emits `safe_text_imported=true`, `cmap_leak_excluded=true`,
`filter_end_marker_policy=filter_end_markers_resolved`,
`filter_decode_policy=reject_filter_decode_errors`,
`filter_decode_error_count=1`, `review_filter_decode_error_count=1`,
`invalid_decodeparms_parameter_count=0`, `executes_python_or_models=false`,
and `executes_external_pdf_tools=false`, and renders only
`Predictor Decode Safe Import`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-predictor-decode-boundary-currentbase.php
php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

All completed with exit code `0`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1992 -> 1993`
- `wordpressScenarios`: `1726 -> 1727`
- Focused PASS case delta: `+1`
- Focused assertion delta over the red-first run: `1139 assertions / 1 failure`
  to `1165 assertions / 0 failures`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object
scanner, xref-selected stream owner review, CMap stream filter resolver,
DecodeParms validator, filter end-boundary scanner, stream decoder, ToUnicode
CMap fallback path, and WordPress smoke renderer.

Full upstream model parity remains intentionally out of scope under the current
no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium,
Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads,
and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted malformed CMap filter operands, escaped or
unsupported filter names, current-generation stale filter owner selection,
invalid DecodeParms parameter rejection, null-filter DecodeParms alignment,
explicit CMap filter end-marker surplus rejection, unbounded explicit filter
markers, post-`endcmap` operator exclusion, overdeclared literal-row
boundaries, nested CMap array exclusion, generic content-stream filter error
boundaries, xref-stream filter errors, or encrypted-PDF preflight.

The new boundary is specifically reviewable decoder failure after a valid CMap
filter operand, valid DecodeParms parameters, and a resolved bounded filter
stream.

## Next Task

Continue with non-overlapping native no-GPU markerPDF parser behavior around
fonts, CMaps, stream filters, xref repair, metadata, annotations/forms, page
geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
