# markerPDF Type3 CharProcs marked-content boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T084704Z`

Base accepted HEAD: `2ae1928a75c28b9c5973a7f1a99a0f16b37e9c23`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before model handoff. At this native parser boundary, Type3 `/CharProcs`
streams are glyph programs rather than document page text, and their `d0`/`d1`
operators provide glyph metrics for text grouping. Non-painting marked-content
wrappers around those metric operators should not make the native parser fall
back to stale `/MissingWidth` spacing.

## Red check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'WideBlock',
  1 => 'Thin Text',
)
Actual: array (
  0 => 'Wide Block',
  1 => 'ThinText',
)

1 test files, 1 assertions, 1 failures
```

That proved the current CharProc width parser treated `BMC`/`BDC` marked-content
wrappers as pre-metric paint, discarded the real `d0`/`d1` widths, and used
`/FontDescriptor /MissingWidth 500` for grouping.

## Implementation

`PdfTextExtractor::type3CharProcAllowsPreMetricSetupOperator()` now permits
the non-painting marked-content wrapper operators `BMC`, `BDC`, and `EMC`
before a Type3 `d0` or `d1` metric. The existing guard still rejects text,
path, image, and XObject paint before a metric, so malformed late metrics do
not erase WordPress word gaps.

The fixture proves:

- `BDC` plus a property dictionary before `d0` preserves a wide CharProc
  metric;
- a `BMC`/`EMC` wrapper before `d1` preserves a thin CharProc metric;
- marked-content property dictionary decoys do not become metric operands;
- CharProc stream payload text remains excluded from visible WordPress
  paragraphs.

## Evidence

Focused test after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

Adjacent Type3/font sweep:

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name '*Type3*Test.php' -o -name '*CharProc*Test.php' \) | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `27 test files, 848 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-marked-content-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`marked_content_metric_widths_preserved=true`,
`missing_width_fallback_excluded_from_grouping=true`,
`marked_content_property_decoy_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Changed PHP lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-marked-content-boundary-currentbase.php
```

Result: no syntax errors.

## Dependency closure

No new support component is needed. This reuses the existing native PDF object
scanner, exact-generation object lookup, Type3 CharProc dictionary resolver,
stream decoder, content tokenizer, FontDescriptor fallback-width handling, text
advance grouping path, and WordPress smoke path. No Python, PDFium, pypdfium2,
Poppler, Ghostscript, OCR, Surya, Texify, Torch, GPU/model execution, browser
service, or external PDF tool was run.

## Non-overlap

This does not repeat accepted Type3 CharProc fallback-payload exclusion,
exact object generation selection, comment-split references, nested/top-level
CharProcs dictionary guards, indirect CharProcs dictionary generation, stream
filters, FontMatrix normalization, full `wx wy` vector transforms, width-array
precedence, initial/post-paint metric rejection, inline-image paint rejection,
resource-subtype decoys, Type3 CMap/CIDSet grouping, or Type0 CMap
source-width work. The new boundary is specifically non-painting marked-content
wrapper operators before Type3 CharProc metrics.

Root harness: not run - isolated micro-slice.
