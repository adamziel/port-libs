# markerPDF malformed CMap filter EOD boundary

Session: `port-dev-markerpdf-malformed-cmap-20260605T104917Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T104917Z`

Base accepted HEAD: `7b9a9fbd060eac121e12806680e789f70e2f7618`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is explicit CMap stream filter end-of-data validation. A ToUnicode CMap filtered with ASCIIHexDecode must include its `>` EOD marker before the decoded CMap can replace WordPress-visible fallback source text.

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through pdftext/PDF parser/font machinery before Marker builds page text and Markdown. Under the current no-GPU lane scope, the PHP fallback owns the parser boundary: malformed filtered CMap payloads must not become authoritative text maps.

PDF filters with explicit EOD markers, including ASCIIHexDecode, ASCII85Decode, and RunLengthDecode, have a bounded data terminator. Content-stream recovery can stay lenient, but CMap streams are font-program inputs; an unterminated filtered CMap should fail closed so raw source text fallback remains visible and decoded CMap operators stay out of Gutenberg paragraphs.

## Implementation

`PdfTextExtractor` now routes CMap stream decoding through `decodeCMapStream()`. The helper reuses the existing stream decoder and null-filter DecodeParms alignment, but requires explicit filter EOD markers for CMap streams before `decodedCMapBody()`, `decodedCMapBodyForParsing()`, or `cMapNameFromObjectBody()` accept decoded bytes.

Generic content-stream fallback decoding is unchanged, including existing ASCIIHex/RunLength length-boundary recovery.

## Red-First Evidence

Before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php
```

Result:

```text
FAIL requires explicit CMap ASCIIHex EOD before ToUnicode replacement on current base
Expected: array (
  0 => 'Missing EOD Safe Import',
)
Actual: array (
  0 => 'Missing EOD CMap Leakissing EOD Safe Import',
)

1 test files, 1 assertions, 1 failures
```

The malformed ASCIIHex CMap had no `>` EOD marker but still replaced the first source code before the fallback text.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 36 assertions, 0 failures
```

Adjacent CMap/filter and stream-filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result:

```text
6 test files, 1947 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-filter-eod-boundary-currentbase.php
```

The smoke emits `missing_eod_cmap_rejected=true`, `valid_eod_cmap_accepted=true`, `visible_text_excludes_cmap_program=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders `Missing EOD Safe Import` plus `ASCIIHex EOD CMap Import`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-filter-eod-boundary-currentbase.php
```

Result: no syntax errors detected.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1743 -> 1744`
- `wordpressScenarios`: `1589 -> 1590`
- Focused PASS case delta: `+1`
- Focused assertion delta: `+36`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream dictionary parser, filter resolver, ASCIIHex decoder, DecodeParms alignment logic, ToUnicode CMap parser, content-token fallback decoder, and WordPress smoke renderer.

Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted malformed CMap filter operands, current-generation filter owner selection, malformed DecodeParms parameter rejection, indirect DecodeParms null-filter alignment, all-null CMap filter stacks, identity/private Crypt CMap filters, escaped or unsupported CMap filter names, post-`endcmap` operator exclusion, overdeclared literal-row boundaries, stale CMap stream `/Length` recovery, generic stream filter stack recovery, inline-image tokenizer repair, image-filter exclusion, CMap width grouping, or encrypted-PDF preflight.

The new boundary is specifically explicit EOD validation for CMap stream filters before ToUnicode replacement and WordPress paragraph extraction.

## Next Task

Continue with non-overlapping native no-GPU markerPDF parser behavior around font/CMap widths, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
