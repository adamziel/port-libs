# markerPDF malformed CMap direct filter-token boundary current-base

Session: `port-dev-markerpdf-malformed-cmap-20260605T150043Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T150043Z`

Base accepted HEAD: `0707d842b016ee542fe2234818daaef87fcd00c8`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser and
WordPress import path. It does not run OCR, Surya, Texify, Torch, PDFium,
pypdfium, Python helpers, model workers, or external PDF tools.

The bounded behavior is a ToUnicode CMap stream dictionary whose scalar
`/Filter` value is followed by a second known filter-name token:

```text
<< /Type /CMap /Filter /FlateDecode /ASCIIHexDecode /Length ... >>
```

Before this slice, the stream-filter parser consumed only `/FlateDecode`,
decoded the CMap, and let the mapped leak text replace the safe Identity-H
fallback text. The native parser now treats that direct scalar filter operand
as malformed and fails closed before CMap decoding.

## Source Truth

Pinned upstream markerPDF sends searchable PDF text through pdftext/PDFium font
and CMap decoding before Markdown generation. Under the current no-GPU lane
scope, the native PHP fallback owns the parser boundary before WordPress
import: malformed stream filter declarations must not decode ToUnicode CMap
payloads or expose CMap program text as paragraphs.

## Behavior

- `PdfTextExtractor::streamFilters()` now rejects a direct scalar `/Filter`
  name when the next token is another recognized stream-filter name.
- `extractCMapStreamFilterLengthOwnerReview()` reports that same boundary as a
  malformed filter operand with `extra_filter_name_operand=true` and
  `extra_filter_name=ASCIIHexDecode`.
- The named CMap registry now derives CMap names from the same bounded parser
  CMap body that is stored for parsing, preventing post-`endcmap` `/CMapName`
  decoys from activating base maps during `usecmap` lookup.
- WordPress-visible text remains `WP Direct Extra Safe Import`, while the
  decoded CMap leak text, post-CMap program bytes, and CMap names stay out of
  paragraphs.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects scalar CMap Filter followed by an extra filter-name token before current-base text extraction
Values are not identical
Expected: 0
Actual: 1

1 test files, 1181 assertions, 1 failures
```

## Verification

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects scalar CMap Filter followed by an extra filter-name token before current-base text extraction

1 test files, 1210 assertions, 0 failures
```

Adjacent CMap/filter/stream boundary group:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 1705 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-direct-filter-token-boundary-currentbase.php
```

The smoke emits `direct_extra_filter_name_rejected=true`,
`filter_resolution_failed=true`, `visible_text_excludes_cmap_program=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and
renders only `WP Direct Extra Safe Import`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-direct-filter-token-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-direct-filter-token-boundary-currentbase.php

php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` completed with exit code `0`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2014 -> 2015`
- `wordpressScenarios`: `1746 -> 1747`
- Focused PASS case delta: `+1`
- Focused assertion delta over the red-first run: `1181 assertions / 1 failure`
  to `1210 assertions / 0 failures`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object
scanner, stream dictionary parser, stream filter resolver, CMap stream decoder,
CMap registry/parser boundary, ToUnicode fallback path, and WordPress smoke
renderer.

Full upstream model parity remains intentionally out of scope under the current
no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium,
Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model
downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted malformed CMap dictionary/literal/indirect filter
operands, stale-generation filter owner selection, invalid/trailing/null
DecodeParms alignment, escaped or unsupported filter names, explicit filter
end-marker rejection, unbounded explicit filter markers, predictor decode
errors, post-`endcmap` operator exclusion, overdeclared literal-row boundaries,
nested CMap array exclusion, generic content-stream trailing payload rejection,
or Type3 CharProc graphics-state boundary work. The new owned boundary is the
direct scalar `/Filter` token followed by another known filter-name token plus
the bounded CMap-name registry consistency needed by the adjacent CMap family.
