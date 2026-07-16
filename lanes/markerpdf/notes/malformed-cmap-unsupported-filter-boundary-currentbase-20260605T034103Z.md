# markerPDF malformed CMap unsupported filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T034103Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level stream, font, and ToUnicode CMap decoding to `pdftext`/PDFium before OCR/layout/model stages.

In the native no-GPU PHP path, ToUnicode CMap streams are text decoder programs. Image/preview-only filters such as `/DCTDecode` can be valid PDF stream filter names, but they are unsupported for native CMap text decoding and must remain fail-closed before WordPress paragraph extraction.

## Behavior

`PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()` now records `unsupported_filter_count` for CMap stream filters that resolve syntactically but have no native text-stream decoder. Such streams report `filter_operand_policy=reject_unsupported_filter_names` instead of `filters_resolved`.

The focused fixture stores a ToUnicode CMap stream with `/Filter /DCTDecode` and a payload mapping `<0001>` to `Unsupported Filter CMap Leak`. The CMap does not decode, visible text falls back through `/Encoding /Identity-H` to `Unsupported Filter Safe Import`, and review metadata keeps the filter name visible only as metadata.

## Evidence

Red probe on the accepted base:

```text
php -r '... /Filter /DCTDecode CMap fixture ...'
array (
  0 => array (0 => 'Unsupported Safe Import'),
  1 => 0,
  2 => 0,
  3 => 'filters_resolved',
  4 => array (0 => 'DCTDecode'),
)
```

Focused test after the patch:

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
PASS classifies unsupported CMap Filter names as fail-closed before current-base text extraction

1 test files, 514 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

The smoke exits 0 and emits `Unsupported Filter Safe Import`, `unsupported_filter_count=1`, `unsupported_filter_names=["DCTDecode"]`, `unsupported_filter_operand_policy="reject_unsupported_filter_names"`, `unsupported_filter_payload_excluded=true`, `leaking_cmap_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
jq empty lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed.

Status delta: focused markerPDF tests `1366 -> 1367` pass / `0` fail. WordPress scenarios `1308 -> 1309`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat malformed CMap dictionary/literal operand rejection, selected indirect filter operand review, current-generation filter selection, stale-generation filter reference classification, malformed DecodeParms parameter rejection, trailing DecodeParms fail-closed behavior, inherited `/UseCMap` DecodeParms review, post-`endcmap` operator exclusion, second CMap program exclusion, CMap `/Filter` and `/Length` owner review, xref-stream filter review, generic stream DecodeParms owner repair, width grouping, simple-font encoding, encrypted-PDF preflight, or image XObject DCT preview metadata.

The bounded behavior here is specifically unsupported but syntactically valid ToUnicode CMap stream filter names before native CMap text decoding and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, CMap stream dictionary/payload reader, stream filter resolver, fail-closed text-stream decoder, ToUnicode fallback path, and WordPress smoke renderer.

Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers. Those remain out of scope under the current no-GPU markerPDF direction.
