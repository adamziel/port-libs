# markerPDF Type3 CharProcs marked-content balance boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T151954Z`

Base accepted HEAD: `73c0bbac79227ee2db977ad15039a8acb1dad8b8`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to pdftext/PDFium before layout/OCR/model stages. At this native parser boundary, Type3 `/CharProcs` are glyph programs: `d0`/`d1` metrics may drive text advance grouping, but CharProc paint/text payloads and malformed wrapper state must not leak into visible WordPress paragraphs or erase real word gaps.

PDF marked-content wrappers are balanced by `BMC`/`BDC` and `EMC`. Existing current-base Type3 slices covered valid wrappers, malformed wrapper operands, pre-metric text/paint rejection, graphics-state balance, compatibility sections, inline images, resource exclusion, exact generations, stream filters, and width precedence. This slice narrows the remaining boundary: an unmatched `EMC` before a late `d0`/`d1` is invalid setup and must not make that late metric authoritative.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBalanceBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'GoodWide',
  1 => 'Bad Gap',
  2 => 'Extra Gap',
)
Actual: array (
  0 => 'GoodWide',
  1 => 'BadGap',
  2 => 'ExtraGap',
)

1 test files, 1 assertions, 1 failures
```

The native parser accepted `d0` after an unmatched `EMC` and after an extra `EMC`, so malformed Type3 glyph programs incorrectly joined WordPress text.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now tracks pre-metric marked-content depth alongside the existing graphics-state depth. `BMC` and `BDC` increment the depth, `EMC` decrements it, and an `EMC` with no matching opener rejects the CharProc metric before `d0`/`d1` can be used. Existing balanced marked-content wrappers around or before metrics remain valid.

The focused fixture proves:

- balanced `/Glyph BMC ... d0 ... EMC` preserves the `GoodWide` Type3 metric;
- an unmatched leading `EMC` rejects the late `d0` metric and uses `/MissingWidth`, preserving `Bad Gap`;
- an extra `EMC` after a balanced wrapper rejects the late `d0` metric and preserves `Extra Gap`;
- CharProc payload text remains excluded from visible WordPress paragraphs.

## Evidence

Red-first focused command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBalanceBoundaryCurrentBaseTest.php
```

After implementation: `1 test files, 11 assertions, 0 failures`.

Adjacent marked-content/graphics-state run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentPointBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGraphicsStateBoundaryCurrentBaseTest.php
```

Result: `4 test files, 41 assertions, 0 failures`.

Type3 CharProc focused family:

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProc*CurrentBaseTest.php' -o -name 'PdfFontType3CharProcs*CurrentBaseTest.php' \) | sort)
```

Result: `32 test files, 282 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-marked-content-balance-currentbase.php
```

Result: emitted Gutenberg paragraphs for `GoodWide`, `Bad Gap`, and `Extra Gap`, with `balanced_marked_content_metric_preserved=true`, `unmatched_emc_metric_rejected=true`, `extra_emc_metric_rejected=true`, `charproc_payload_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBalanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-marked-content-balance-currentbase.php
```

Result: no syntax errors.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, exact-generation object lookup, Type3 CharProc dictionary parser, stream decoder, content tokenizer, FontDescriptor fallback width handling, text advance grouping path, and WordPress smoke renderer. No Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, CharProc fallback exclusion, generation selection, indirect `/CharProcs` dictionary selection, top-level `/CharProcs` lookup, nested dictionary parsing, stream-filter fail-closed behavior, Type3 `FontMatrix` normalization, marked-content operand validation, valid BMC/BDC wrapper acceptance, pre-metric paint rejection, graphics-state balance, Type3 CMap/CIDSet grouping, image/filter boundaries, or xref/object-stream repair. The bounded behavior is specifically unmatched pre-metric `EMC` rejection before WordPress text grouping.

Root harness: not run - isolated micro-slice.
