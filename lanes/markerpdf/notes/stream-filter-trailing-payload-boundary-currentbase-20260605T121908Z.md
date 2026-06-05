# markerPDF stream-filter trailing payload boundary

Session: `port-dev-markerpdf-stream-filter-stack-20260605T121908Z`

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T121908Z`

Base accepted HEAD: `83833276ce29682e35bfb0292b3d0bc70f094d70`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is visible page-content stream filter end-of-data validation. When a page-visible filtered stream includes an explicit ASCIIHex/ASCII85/RunLength EOD or a complete Flate/LZW encoded end, non-whitespace bytes after that filter boundary now make the visible stream fail closed instead of importing the decoded prefix.

Length-bounded ASCIIHex/ASCII85 content that omits optional EOD markers remains accepted, preserving existing PDF compatibility.

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through pdftext/PDF parser/font machinery before Marker builds page text and Markdown. Under the current no-GPU lane scope, the PHP fallback owns the parser boundary for searchable PDFs.

PDF stream filters are applied in order to the bytes of the stream. Explicit filter EOD markers may delimit ASCIIHexDecode, ASCII85Decode, and RunLengthDecode data, but a page-content stream whose declared data continues with non-whitespace bytes before `endstream` is malformed for visible-text import. This slice keeps such malformed decoded prefixes out of WordPress paragraphs while preserving valid bounded stacks.

## Implementation

`PdfTextExtractor::decodeStreamObject()` now accepts a bounded-filter-end flag. Page /Contents streams, Form XObjects, annotation appearance streams, and stream-only fallback text extraction pass that flag so visible content decoding requires whitespace-only bytes after resolved filter boundaries.

The lower-level decoder still allows length-bounded ASCIIHex/ASCII85 streams with omitted optional EOD markers when the caller is not requiring explicit filter terminators. CMap decoding keeps its stricter explicit-EOD policy from the prior slice.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php
```

Result:

```text
FAIL rejects non-whitespace bytes after explicit filter EOD markers before page text import
Expected: array (
  0 => 'Bounded Stack Content Import',
  1 => 'Visible After Trailing Payload Boundary',
)
Actual: array (
  0 => 'Unbounded ASCIIHex Content Leak',
  1 => 'Unbounded ASCII85 Stack Leak',
  2 => 'Bounded Stack Content Import',
  3 => 'Visible After Trailing Payload Boundary',
)

1 test files, 1 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 14 assertions, 0 failures
```

Adjacent stream-filter/CMap/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result:

```text
7 test files, 2090 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-trailing-payload-currentbase.php
```

The smoke emits `direct_asciihex_unbounded_rejected=true`, `stacked_ascii85_unbounded_rejected=true`, `bounded_stack_preserved=true`, `visible_after_preserved=true`, `raw_trailing_decoys_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders `Bounded Stack Content Import` plus `Visible After Trailing Payload Boundary`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-trailing-payload-currentbase.php
```

Result: no syntax errors detected.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1814 -> 1815`
- `wordpressScenarios`: `1649 -> 1650`
- Focused PASS case delta: `+1`
- Focused assertion delta: `+14`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream dictionary parser, filter-stack resolver, ASCIIHex/ASCII85/Flate decoders, visible content-token parser, Form/appearance stream expansion, and WordPress smoke renderer.

Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted CMap explicit-EOD fail-closed behavior, malformed CMap filter operands, malformed DecodeParms parameter rejection, indirect DecodeParms null-filter alignment, extra DecodeParms rejection, all-null filter stacks, identity/private Crypt filter handling, DCT/CCITT/JPX/JBIG2 image-filter exclusion, inline-image tokenizer repair, object-stream/xref-stream filter operand recovery, xref repair, CMap width grouping, or encrypted-PDF preflight.

The new boundary is specifically visible page-content stream rejection when explicit filter EOD or complete encoded stream boundaries are followed by non-whitespace bytes before WordPress paragraph extraction.

## Next Task

Continue with non-overlapping native no-GPU markerPDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
