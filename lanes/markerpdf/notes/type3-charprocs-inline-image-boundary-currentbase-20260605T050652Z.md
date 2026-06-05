# markerPDF Type3 CharProcs inline-image boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T050652Z`

Base accepted HEAD: `bd28920b7f3ed02f501965b633a3e53666fd2f67`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF text/parser
facilities before document block assembly. In the native no-GPU PHP fallback,
Type3 `/CharProcs` are glyph programs rather than visible page text. Type3
`d0` and `d1` metric operators can drive text advance decisions only when they
appear before glyph painting. Inline images (`BI ... ID ... EI`) inside a
CharProc are paint content, so a later `d0`/`d1` must not be accepted as the
glyph metric that controls WordPress paragraph spacing.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'WideBlock',
  1 => 'Late Image',
)
Actual: array (
  0 => 'WideBlock',
  1 => 'LateImage',
)

1 test files, 1 assertions, 1 failures
```

That proved the Type3 CharProc width scanner skipped the inline-image payload
without treating `BI` as pre-metric paint, then accepted the late `d0` and
removed the expected WordPress word gap.

## Implementation

`PdfTextExtractor::contentTokens()` now accepts a narrowly scoped
`$preserveInlineImageOperator` flag. Normal page content parsing keeps the
accepted behavior of skipping inline-image payload bytes. The Type3 CharProc
metric scanner opts into the flag, receives a `BI` sentinel when an inline image
is skipped, and rejects any following `d0`/`d1` through the existing
pre-metric-paint guard.

The focused fixture proves:

- a valid initial `d0` width keeps `WideBlock` joined;
- an inline image before `d0` causes the late metric to be rejected;
- `/FontDescriptor /MissingWidth 250` preserves `Late Image`;
- CharProc text and inline-image dictionary payloads stay out of visible
  Gutenberg paragraphs.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfFontType3.*CurrentBaseTest\.php|PdfFontSimpleType3.*CurrentBaseTest\.php|PdfFontCMapCidType3.*CurrentBaseTest\.php|PdfFontCidType3.*CurrentBaseTest\.php' | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `21 test files, 799 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-inline-image-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Late Image`, with
`valid_initial_metric_width_preserved=true`,
`post_inline_image_metric_rejected=true`,
`missing_width_fallback_used=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Syntax checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-inline-image-boundary-currentbase.php
```

Result: all reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
stream decoder, Type3 `/CharProcs` dictionary resolver, content tokenizer,
inline-image skipper, FontDescriptor fallback width path, and text-advance
grouping pipeline. No Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR,
GPU/model execution, browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted Type3 CharProc fallback exclusion, exact object
generation selection, indirect `/CharProcs` dictionary generation, top-level
and nested dictionary boundaries, subtype gating, stream-filter fail-closed
behavior, pre-metric text/path paint rejection, FontMatrix normalization,
full `wx wy` vector transforms, private glyph fallback, resource-subtype
decoys, Type3 CMap/CIDSet spacing, Type3 glyph-name Unicode recovery, color
glyph resource width handling, or page inline-image text-token exclusion. The
new boundary is only inline-image paint inside a Type3 CharProc before a late
metric operator.
