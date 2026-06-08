# markerPDF CCITT Fax Inline Source Alias Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T192315Z`
Base: `e97bdf9331ef05dac3f6237d837a28df8dd53eb5`

## Behavior

PDF inline images use abbreviated dictionary keys and values. The native review path canonicalizes those before planning RGB preview boundaries, so `/F [/AHx /CCF]` became `/Filter [/ASCIIHexDecode /CCITTFaxDecode]`. That canonical form is correct for filter dispatch, but WordPress review lost the source alias that distinguishes an authored `/CCF` CCITT Fax boundary from a canonical `/CCITTFaxDecode` row.

`PdfImageRenderer::inlineImageReviewPlan()` now preserves source filter metadata under `inline_image` while keeping canonical planning unchanged:

- `source_filters=["AHx","CCF"]`
- `source_preview_only_filters=["CCF"]`
- `source_filter_aliases` records `AHx -> ASCIIHexDecode` and `CCF -> CCITTFaxDecode`
- `source_ccitt_alias_used=true`
- canonical `image_filters=["ASCIIHexDecode","CCITTFaxDecode"]` and review-only CCITT handling remain unchanged

The payload remains review-only image bytes and is excluded from visible WordPress text, without native CCITT raster decoding.

## Red-First Evidence

A focused renderer probe before the implementation returned only the canonical inline filter stack and no source alias metadata for `/F [/AHx /CCF]`. That made inline CCITT review weaker than accepted Image XObject CCITT alias review, which already preserves source `/CCF` while exposing canonical `CCITTFaxDecode`.

## Verification

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
No syntax errors detected in lanes/markerpdf/src/PdfImageRenderer.php

php -l lanes/markerpdf/tests/PdfCcittFaxInlineSourceAliasBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxInlineSourceAliasBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-inline-ccitt-source-alias-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-ccitt-source-alias-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxInlineSourceAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves inline CCF source aliases separately from canonical CCITT Fax review metadata
PASS keeps inline source-alias CCITT Fax payload excluded from WordPress text extraction

1 test files, 27 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfCcittFax.*Test\.php$' | sort)
Focused test run: 7 selected test files (root lock skipped)
...
7 test files, 1390 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(InlineImage|ImageInline|ParserInline).*Test\.php$' | sort)
Focused test run: 29 selected test files (root lock skipped)
...
29 test files, 2477 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-ccitt-source-alias-boundary-currentbase.php
```

The smoke exits 0 and emits `inline_source_filters=["AHx","CCF"]`, `inline_source_preview_only_filters=["CCF"]`, `canonical_filters=["ASCIIHexDecode","CCITTFaxDecode"]`, `review_only_filters=["CCITTFaxDecode"]`, `source_ccitt_alias_used=true`, `payload_excluded_from_text=true`, `payload_excluded_from_review=true`, and both Python/model and external-PDF-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted terminal CCITT exclusion, Image XObject `/CCF` alias preservation, post-CCITT unreachable filter metadata, nonterminal CCITT owner repair, native prefix decoded-byte handoff, CCITT row/EOL/RTC/EOFB ownership, malformed/duplicate DecodeParms fail-closed behavior, indirect filter tail repair, inline CCITT tokenizer fallback, DCT native-prefix alias metadata, inline image Decode/DecodeParms/geometry boundaries, OCR/model execution, or native raster decoding.

The bounded behavior is only inline-image source filter alias review metadata for CCITT Fax filter stacks before WordPress import.

## Dependency Closure

No new support component is needed. This reuses the native PHP inline-image dictionary canonicalizer, PDF filter resolver, CCITT DecodeParms review path, inline-image tokenizer, and WordPress smoke path. Full CCITT raster decoding, PDFium/PIL rendering, Surya/Torch OCR/layout, Texify equation recognition, GPU/model workers, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.
