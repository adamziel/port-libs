# markerPDF malformed CMap indirect scalar filter boundary current-base

Session: `port-dev-markerpdf-malformed-cmap-20260605T161727Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T161727Z`

Base accepted HEAD: `6c78b780d3e7e0af428581dfeac8da16a36ff6cc`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser and
WordPress import path. It does not run OCR, Surya, Texify, Torch, PDFium,
pypdfium, Python helpers, model workers, or external PDF tools.

The bounded behavior is a ToUnicode CMap stream whose `/Filter` entry resolves
through xref to an indirect scalar helper object with a trailing decoder-name
token:

```text
6 0 obj
<< /Type /CMap /Filter 7 0 R /Length ... >>
stream
...
endstream
endobj
7 0 obj
/FlateDecode /ASCIIHexDecode
endobj
```

The parser already failed closed before applying that CMap. This slice closes
the remaining review boundary by classifying the selected indirect helper with
the same `extra_filter_operand` metadata that direct scalar `/Filter` operands
already report.

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through pdftext/PDFium font
and CMap decoding before Markdown generation. Under the current no-GPU PHP
lane, malformed stream filter declarations are a native parser dependency
boundary: xref-selected indirect filter helper objects must be resolved,
validated, and rejected before ToUnicode CMap payloads can replace fallback
visible text for WordPress import.

## Behavior

- `PdfTextExtractor::xrefStreamIndirectOperandReview()` now reuses the scalar
  filter extra-operand classifier when a resolved indirect `/Filter` helper body
  begins with a name token.
- Xref-selected helper bodies such as `/FlateDecode /ASCIIHexDecode` remain
  `valid_filter_operand=false` and now also report
  `extra_filter_operand=true`, `extra_filter_operand_type=name`,
  `extra_filter_operand_preview=/ASCIIHexDecode`,
  `extra_filter_name_operand=true`, and
  `extra_filter_name=ASCIIHexDecode`.
- ToUnicode CMap decoding remains fail-closed, so WordPress-visible text falls
  back to the safe Identity-H source text and excludes the leaking CMap mapping
  plus CMap names.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL classifies xref selected indirect scalar CMap Filter helper with an extra decoder-name token before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: true
Actual: NULL

1 test files, 58 assertions, 1 failures
```

## Verification

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS classifies xref selected indirect scalar CMap Filter helper with an extra decoder-name token before current-base text extraction

1 test files, 62 assertions, 0 failures
```

Adjacent malformed CMap filter boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
29 PASS cases

1 test files, 1342 assertions, 0 failures
```

Adjacent CMap stream owner/EOD gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
3 PASS cases

2 test files, 122 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-indirect-scalar-filter-boundary-currentbase.php
```

The smoke emits `decoded_cmap_count=0`, `invalid_filter_operand_count=1`,
`filter_operand_policy=reject_malformed_filter_operands`,
`owner_policy=xref_selected_indirect_operands`,
`extra_filter_name=ASCIIHexDecode`, `leaking_cmap_text_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and
renders only `Indirect Scalar Safe Import`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-indirect-scalar-filter-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-indirect-scalar-filter-boundary-currentbase.php

php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` completed with exit code `0`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2069 -> 2070`
- `wordpressScenarios`: `1785 -> 1786`
- Focused PASS case delta: `+1`
- Focused assertion delta over the red-first run: `58 assertions / 1 failure`
  to `62 assertions / 0 failures`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, xref owner selection, indirect-object resolver, stream dictionary
parser, stream filter resolver, CMap stream review, Identity-H fallback text
path, and WordPress smoke renderer.

Full upstream model parity remains intentionally out of scope under the current
no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium,
Surya/Torch, Texify, tabled-pdf, Streamlit/FastAPI runtime paths,
benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted direct scalar malformed CMap `/Filter`
extra-token rejection, direct scalar non-name operands, dictionary or literal
filter operands, stale-generation filter owner selection, DecodeParms
alignment, decode-error handling, escaped or unsupported filter names,
explicit filter end-marker rejection, unbounded explicit filter markers,
post-`endcmap` operator exclusion, nested CMap arrays, or general stream
trailing payload rejection. The owned boundary is the xref-selected indirect
scalar filter helper body that begins as a valid decoder name but contains a
trailing decoder-name token.
