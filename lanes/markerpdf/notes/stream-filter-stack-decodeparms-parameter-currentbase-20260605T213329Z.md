# Stream Filter Stack DecodeParms Parameter Boundary - 2026-06-05

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T213329Z`
Session: `port-dev-markerpdf-stream-filter-stack-20260605T213329Z`
Base accepted HEAD: `b321f6888e03ba16f542328dfc7cccbdbb2ef4a8`

## Source Truth

Pinned upstream markerPDF source is `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream searchable-PDF extraction reaches stream decoding through pdftext/PDFium before OCR, layout models, or image rendering. Under the no-GPU scope, the PHP parser keeps this boundary conservative: ambiguous stream filter parameters fail closed before WordPress paragraph import.

For this slice, duplicate top-level keys inside a single `/DecodeParms` dictionary are treated as malformed. This includes predictor parameters such as `/Predictor` and `/Columns`, plus `/Crypt` filter `/Name` parameters where `/Identity` and a private crypt filter would otherwise conflict.

## Implementation

- `PdfTextExtractor::canApplyDecodeParms()` now rejects duplicate top-level DecodeParms parameters before predictor, LZW `EarlyChange`, or Crypt `/Name` resolution.
- The guard reuses the existing token-aware top-level dictionary scanner, so nested dictionaries, arrays, comments, and literal strings are not mistaken for duplicate parameter declarations.
- Valid singleton DecodeParms dictionaries, null filter slots, compact DecodeParms arrays, default Identity Crypt parameters, and sibling content streams continue to decode.

## Evidence

Red-first focused run after adding the regression and before the source guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 256 assertions, 1 failures
Actual lines included Duplicate Predictor Parameter Leak, Duplicate Predictor Still Leaks, Duplicate Crypt Name Leak, and Duplicate Crypt Tail Leak.
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 269 assertions, 0 failures
```

Adjacent stream-filter family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilter*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilter*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMap*Filter*CurrentBaseTest.php
17 test files, 2310 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-decodeparms-parameter-currentbase.php
```

The smoke emitted `duplicate_predictor_parameter_rejected=true`, `duplicate_crypt_name_rejected=true`, `visible_fallback_preserved=true`, `predictor_dictionary_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with only `Visible After Duplicate DecodeParms Parameters` rendered as a paragraph.

## Non-Overlap

This does not repeat the accepted stream-filter stack work for duplicate stream-owned `/Filter` or `/DecodeParms` keys, extra DecodeParms array entries, null filter slots, all-null filters, compact DecodeParms alignment, abbreviated filters, missing/stale length recovery, ASCII85/RunLength/LZW EOD boundaries, Identity/default Crypt handling, parser-comment split references, malformed indirect multi-name filters, tiling-pattern filter EOD boundaries, attachment filter stacks, object/xref streams, image metadata, CMaps, AcroForm, annotations, or xref repair.

## Dependency Closure

No new support component is required. The patch reuses the native PHP PDF object scanner, top-level dictionary parser, stream filter resolver, DecodeParms alignment logic, Flate/LZW/Crypt decoders, content-token parser, and WordPress smoke renderer. No Python, pdftext, PDFium, OCR, Surya, Texify, Torch, GPU/model execution, or external PDF tooling was executed.

Root harness: not run - isolated micro-slice.
