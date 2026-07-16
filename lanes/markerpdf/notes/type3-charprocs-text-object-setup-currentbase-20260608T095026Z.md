# Type3 CharProc text-object setup boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T095026Z`

Base accepted HEAD: `2d73d97058438bddda69f12958834d59c4b7c86c`

## Source truth

Upstream markerPDF delegates searchable PDF text extraction to pdftext/PDFium before OCR/model fallback. In this no-GPU markerPDF lane, the equivalent port surface is native searchable-PDF parsing: fonts, encodings, content streams, and Type3 glyph programs. Type3 `/CharProcs` are glyph programs, not page-visible text. Their `d0`/`d1` operators define glyph metrics for downstream text grouping. A balanced pre-metric text object that only sets text state is setup code and should not force fallback to stale `/Widths`, while pre-metric text painting and unclosed text objects remain unsafe boundaries and fail closed.

## Red check

Before the source patch, the focused test rejected a no-paint `BT ... ET` setup block before `d0` and split `WideBlock` with stale fallback widths:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectSetupBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL accepts no-paint Type3 CharProc text objects before metrics on current base
Expected: ['WideBlock','Thin Text','Paint Gap','Open Gap']
Actual: ['Wide Block','ThinText','Paint Gap','Open Gap']
1 test files, 1 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now tracks balanced text objects before the first Type3 metric operator. It accepts only non-painting text-state setup operators (`Tf`, `Tc`, `Tw`, `Tz`, `TL`, `Tr`, `Ts`, `Td`, `TD`, `Tm`, and `T*`) before `ET`, then continues scanning for `d0`/`d1`. It still rejects painting text operators before metrics and rejects unclosed text objects, so CharProc payload text cannot leak into imported paragraphs.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectSetupBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS accepts no-paint Type3 CharProc text objects before metrics on current base
1 test files, 14 assertions, 0 failures
```

Type3 family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*CurrentBaseTest.php
Focused test run: 59 selected test files (root lock skipped)
59 test files, 603 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-text-object-setup-currentbase.php
text_object_setup_charproc_widths_preserved=true
pre_metric_text_paint_rejected=true
open_text_object_metric_rejected=true
charproc_payload_visible_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
paragraphs=WideBlock|Thin Text|Paint Gap|Open Gap
```

PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectSetupBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-text-object-setup-currentbase.php
No syntax errors detected in all changed PHP files.
```

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoding, content tokenizer, Type3 CharProc metric parsing, font-width grouping, and the lane-local WordPress smoke fixture. It does not invoke Python, pypdfium2/PDFium, OCR, Surya, Texify, Torch, model workers, GPU execution, raster rendering, live services, or external PDF tools.

## Non-overlap

This slice is bounded to no-paint `BT ... ET` setup before Type3 `d0`/`d1` metrics. It does not repeat accepted direct Type3 metric parsing, fallback-payload exclusion, exact generation selection, nested/top-level CharProcs, FontMatrix normalization, path/marked-content/compat setup, inline-image paint rejection, resource fallback, or xref/object-stream repair work.
