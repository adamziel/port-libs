# markerPDF malformed CMap post-endcmap filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T023422Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, with low-level ToUnicode CMap stream decoding delegated to pdftext/PDFium before WordPress-visible Markdown is assembled.

The native no-GPU PHP lane owns the parser boundary before that model-free text import path. A valid decoded CMap stream can carry ordinary PostScript cleanup after `endcmap`, but CMap mapping operators after the final real `endcmap` must not change text extraction.

## Behavior

`PdfTextExtractor` now bounds CMap operator parsing at the final real `endcmap` token. This preserves composed `/UseCMap` bodies where a base CMap is prepended before a derived CMap, and it preserves existing CMap name discovery after `endcmap`, while excluding trailing decoded `beginbfchar`, `beginbfrange`, and codespace junk from WordPress text mapping.

`extractCMapStreamFilterLengthOwnerReview()` now exposes review-only boundary metadata:

- `bounded_cmap_length`
- `post_endcmap_byte_count`
- `post_endcmap_bytes_excluded`

The focused fixture decodes a valid Flate ToUnicode CMap that maps `<0001>` to `PostEnd Safe Import`, then appends a post-`endcmap` bfchar decoy that remaps `<0001>` to `PostEnd CMap Leak`. Before the fix, the decoy mapping replaced the safe text. After the fix, WordPress-visible text remains `PostEnd Safe Import`, and the review row reports `post_endcmap_byte_count=193`.

## Evidence

Red-first focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL ignores decoded CMap operators after endcmap before current-base text extraction
Expected: ['PostEnd Safe Import']
Actual: ['PostEnd CMap Leak']
1 test files, 433 assertions, 1 failures
```

Focused test after fix:

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
1 test files, 453 assertions, 0 failures
```

Adjacent CMap/DecodeParms/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/Pdf*CMap*Test.php lanes/markerpdf/tests/PdfParser*DecodeParms*Test.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 23 selected test files (root lock skipped)
...
23 test files, 1424 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

The smoke emits `fallback_text="Safe Import | Literal Safe Import | Indirect Literal Safe Import | Indirect Array Safe Import | Generation Safe Import | DecodeParms Safe Import | Trailing DecodeParms Safe Import | Stale Reference Safe Import | PostEnd Safe Import"`, `post_endcmap_byte_count=193`, `post_endcmap_bytes_excluded=true`, `post_endcmap_operator_payload_excluded=true`, `leaking_cmap_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat malformed CMap `/Filter` dictionary/literal operand rejection, selected indirect filter operand review, current-generation filter selection, malformed DecodeParms parameter rejection, trailing DecodeParms fail-closed behavior, inherited `/UseCMap` DecodeParms review, CMap `/Filter` and `/Length` owner review, xref-stream filter review, generic stream DecodeParms owner repair, width grouping, simple-font encoding, or encrypted-PDF preflight.

The bounded behavior here is specifically decoded CMap operator exclusion after the final real `endcmap`, while preserving legitimate CMap names and composed `/UseCMap` inheritance.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref owner selection, stream filter resolver, DecodeParms validator, ToUnicode CMap parser, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
