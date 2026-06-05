# markerPDF malformed CMap Crypt filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T044943Z`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py`, delegating low-level font, ToUnicode CMap, and stream decoding to pdftext/PDFium before Markdown/WordPress assembly.

In the native no-GPU PHP lane, CMap stream filter review must mirror the native stream decoder: `/Crypt` with `/DecodeParms << /Name /Identity >>` is a pass-through filter stage, while a named/private crypt filter requires decryption support and must stay fail-closed.

## Behavior

`PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()` now classifies unsupported CMap filters with the resolved DecodeParms for each filter slot. Identity `/Crypt` CMap streams decode and report `filter_operand_policy=filters_resolved`; non-identity `/Crypt` CMap streams remain undecoded with `filter_operand_policy=reject_unsupported_filter_names`.

The focused fixtures prove:

- `/Filter /Crypt /DecodeParms << /Name /Identity >>` maps `<0001>` to `Identity Crypt CMap Import` and reports `unsupported_filter_count=0`.
- `/Filter /Crypt /DecodeParms << /Name /PrivateCF >>` does not decode the CMap payload, falls back to Identity-H text `Private Crypt Safe Import`, and reports `unsupported_filter_count=1`.

## Evidence

Red probe on accepted base `85a8ec3ff89faa51eab494d54b6f8b2309e6ac3a` before the source fix:

```text
Identity Crypt CMap text imported, but review metadata reported:
unsupported_filter_count=1
filter_operand_policy=reject_unsupported_filter_names
```

Focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS treats identity Crypt CMap filters as pass-through while rejecting named crypt filters
PASS classifies unsupported CMap Filter names as fail-closed before current-base text extraction

1 test files, 639 assertions, 0 failures
```

Adjacent CMap/filter/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
...
6 test files, 1465 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

The smoke emits `Identity Crypt CMap Import`, `Private Crypt Safe Import`, `crypt_identity_filter_supported=true`, `crypt_identity_unsupported_filter_count=0`, `crypt_private_filter_rejected=true`, `crypt_private_unsupported_filter_count=1`, `leaking_cmap_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat malformed CMap dictionary/literal filter operands, selected indirect filter operand review, current-generation stale filter selection, malformed DecodeParms parameter rejection, trailing DecodeParms fail-closed behavior, null-filter DecodeParms slot handling, inherited `/UseCMap` DecodeParms review, post-`endcmap` parser bounding, second CMap program exclusion, unsupported image-filter rejection, generic content-stream identity Crypt stacks, encrypted-PDF permission preflight, or CMap width/text grouping.

The bounded behavior is specifically CMap review metadata and fail-closed behavior for `/Crypt` filters based on the stream's DecodeParms.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream dictionary reader, filter resolver, DecodeParms slot alignment, identity Crypt pass-through decoder, ToUnicode CMap parser, and WordPress smoke renderer. Non-identity crypt filters still require real decryption support and remain fail-closed. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
