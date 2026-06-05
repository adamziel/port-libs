# markerPDF malformed CMap second-program filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T030843Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, with low-level ToUnicode CMap stream decoding delegated to pdftext/PDFium before WordPress-visible Markdown is assembled.

The native no-GPU PHP lane owns the parser boundary before that model-free text import path. A decoded ToUnicode CMap stream can contain ordinary resource cleanup after `endcmap`, but a second complete CMap program in the same decoded stream must not override the first stream program's mappings.

## Behavior

`PdfTextExtractor` now separates raw decoded CMap review from parser-bound CMap mapping:

- raw decoded CMap bytes remain available for review metadata;
- named CMap discovery still uses the raw decoded body so valid `/CMapName` declarations after `endcmap` continue to support `/UseCMap` inheritance;
- text mapping uses a per-stream parser body bounded at the first real `endcmap` for that decoded stream object;
- composed `/UseCMap` inheritance remains valid because inherited CMap objects are bounded individually before composition.

`extractCMapStreamFilterLengthOwnerReview()` now exposes parser-boundary metadata:

- `parser_bounded_cmap_length`
- `parser_excluded_cmap_byte_count`
- `parser_bounded_cmap_bytes_excluded`

The focused fixture decodes a valid Flate ToUnicode CMap mapping `<0001>` to `SecondProgram Safe Import`, then appends a second complete decoy CMap program mapping the same source to `Second Program CMap Leak`. Before the fix, the decoy mapping won. After the fix, WordPress-visible text remains `SecondProgram Safe Import`, while review metadata reports `parser_bounded_cmap_length=338` and `parser_excluded_cmap_byte_count=400`.

## Evidence

Red-first focused test on accepted base `db2d08f63faa9634247619afb0e5a9a0669c2586`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL ignores complete decoded CMap programs after the first stream endcmap before current-base text extraction
Expected: ['SecondProgram Safe Import']
Actual: ['Second Program CMap Leak']
1 test files, 454 assertions, 1 failures
```

Focused green after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction
PASS rejects current-generation indirect CMap Filter dictionaries instead of stale valid filters
PASS rejects current-generation malformed CMap DecodeParms parameters before ToUnicode decoding
PASS rejects trailing malformed CMap DecodeParms array entries before ToUnicode decoding
PASS reviews malformed inherited UseCMap DecodeParms before current-base text extraction
PASS classifies stale-generation CMap Filter references by the current xref-selected malformed owner
PASS ignores decoded CMap operators after endcmap before current-base text extraction
PASS ignores complete decoded CMap programs after the first stream endcmap before current-base text extraction

1 test files, 476 assertions, 0 failures
```

Adjacent CMap/filter/DecodeParms/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/Pdf*CMap*Test.php lanes/markerpdf/tests/PdfParser*DecodeParms*Test.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 23 selected test files (root lock skipped)
...
23 test files, 1457 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

The smoke emits `fallback_text="Safe Import | Literal Safe Import | Indirect Literal Safe Import | Indirect Array Safe Import | Generation Safe Import | DecodeParms Safe Import | Trailing DecodeParms Safe Import | Stale Reference Safe Import | PostEnd Safe Import | SecondProgram Safe Import"`, `second_program_parser_bytes_excluded=true`, `second_program_parser_excluded_byte_count=400`, `second_program_payload_excluded=true`, `leaking_cmap_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/status checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat malformed CMap `/Filter` dictionary/literal operand rejection, selected indirect filter operand review, current-generation filter selection, stale-generation filter reference classification, malformed DecodeParms parameter rejection, trailing DecodeParms fail-closed behavior, inherited `/UseCMap` DecodeParms review, CMap `/Filter` and `/Length` owner review, xref-stream filter review, generic stream DecodeParms owner repair, width grouping, simple-font encoding, encrypted-PDF preflight, or the previous post-`endcmap` single-operator exclusion.

The bounded behavior here is specifically complete second CMap-program exclusion inside one decoded ToUnicode stream while preserving valid named `/UseCMap` inheritance.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref owner selection, stream filter resolver, DecodeParms validator, ToUnicode CMap parser, `/UseCMap` named-CMap registry, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
