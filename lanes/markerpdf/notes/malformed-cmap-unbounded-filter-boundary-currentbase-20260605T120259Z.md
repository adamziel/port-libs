# markerPDF unbounded CMap explicit filter boundary

Session: `port-dev-markerpdf-malformed-cmap-20260605T120259Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T120259Z`

Base accepted HEAD: `a467fce1e67c9dbaeea83429e2d75863f86d2075`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is CMap stream filter trust. A ToUnicode CMap filtered with an explicit EOD filter such as `ASCIIHexDecode` or a stacked inner `ASCII85Decode` must not be accepted when non-whitespace bytes remain after the filter terminator inside the stream payload.

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through pdftext/PDF parser/font machinery before Marker builds page text and Markdown. Under the current no-GPU lane scope, the PHP fallback owns this parser boundary: malformed filtered CMap payloads must fail closed so fallback source text remains visible and decoded CMap operators never become WordPress paragraphs.

Prior accepted work added review metadata for `unbounded_explicit_end_marker`. The missing behavior was making that boundary authoritative for actual ToUnicode CMap decoding.

## Implementation

`PdfTextExtractor::decodeCMapStream()` now calls the existing stream decoder with bounded explicit filter end-marker enforcement enabled. Generic content-stream recovery remains unchanged; only CMap font-program streams use the stricter boundary before ToUnicode replacement, `UseCMap` name discovery, and review metadata decoding.

The focused fixture covers both:

- direct `/Filter /ASCIIHexDecode` CMap streams with a valid `>` marker followed by non-whitespace bytes;
- stacked `/Filter [/FlateDecode /ASCII85Decode]` CMap streams where the decoded inner ASCII85 data has a valid `~>` marker followed by non-whitespace bytes.

Both now preserve fallback text and report `filter_end_marker_policy=reject_malformed_filter_end_markers`.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when explicit CMap filter terminators are followed by non-whitespace stream bytes
Expected: array (
  0 => 'Bounded EOD Safe Import',
)
Actual: array (
  0 => 'Unbounded EOD CMap Leakounded EOD Safe Import',
)

1 test files, 973 assertions, 1 failures
```

## Verification

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1056 assertions, 0 failures
```

Adjacent CMap/filter/text extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 2346 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-unbounded-filter-boundary-currentbase.php
```

The smoke emits `direct_unbounded_asciihex_rejected=true`, `stacked_inner_ascii85_unbounded_rejected=true`, `direct_filter_end_marker_policy=reject_malformed_filter_end_markers`, `stacked_filter_end_marker_policy=reject_malformed_filter_end_markers`, `visible_text_excludes_cmap_program=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders only `Bounded EOD Safe Import` plus `Inner Bounded Safe Import`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-unbounded-filter-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json")); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); }'
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1802 -> 1803`
- `wordpressScenarios`: `1638 -> 1639`
- Focused PASS case delta: `+1`
- Focused assertion delta in the changed focused file: `972 -> 1056`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream dictionary parser, stream filter resolver, DecodeParms alignment logic, CMap stream decoder/parser, CMap review metadata path, and WordPress smoke renderer.

Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted malformed CMap filter operands, direct missing-EOD replacement blocking, current-generation filter owner selection, malformed DecodeParms rejection, indirect DecodeParms null-filter alignment, all-null CMap filter stacks, identity/private Crypt CMap filters, escaped or unsupported CMap filter names, post-`endcmap` operator exclusion, overdeclared literal-row boundaries, stale CMap stream `/Length` recovery, or review-only end-marker reporting.

The new boundary is specifically enforcing previously reviewable `unbounded_explicit_end_marker` problems during actual CMap decoding before ToUnicode replacement.

## Next Task

Continue with non-overlapping native no-GPU markerPDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
