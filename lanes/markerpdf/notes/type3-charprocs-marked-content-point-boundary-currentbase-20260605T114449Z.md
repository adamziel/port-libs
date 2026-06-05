# markerPDF Type3 CharProcs marked-content point boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T114449Z`

Base accepted HEAD: `21ca4e606962df02c069c2fe826037f969abd856`

## Source Truth

Upstream markerPDF routes searchable-PDF text through pdftext/PDFium before
Markdown and WordPress paragraphs are assembled. In the native no-GPU PHP path,
Type3 `/CharProcs` remain glyph programs: `d0`/`d1` metrics drive text advance
grouping, while glyph program text, marked-content properties, and payload
strings remain hidden from visible WordPress paragraphs.

PDF content streams support marked-content point operators `MP` and `DP` in
addition to the already-covered `BMC`/`BDC` wrappers. Valid point markers before
a Type3 metric operator are non-painting metadata and should not force stale
`/Widths` fallback. Malformed point-marker operand stacks still fail closed.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentPointBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'WideBlock',
  1 => 'Thin Text',
  2 => 'Bad Join',
)
Actual: array (
  0 => 'Wide Block',
  1 => 'ThinText',
  2 => 'Bad Join',
)

1 test files, 1 assertions, 1 failures
```

That proved valid pre-metric `MP` and `DP` operators were rejected, causing the
extractor to use conflicting stale `/Widths` values. The malformed `DP` row
already stayed fail-closed.

## Implementation

`PdfTextExtractor::type3CharProcAllowsPreMetricSetupOperator()` now accepts
valid Type3 CharProc marked-content point operators before metrics:

- `MP` requires exactly one marked-content tag operand.
- `DP` requires exactly one marked-content tag plus a name or dictionary
  property operand.
- malformed `DP` operands remain rejected so late `d0`/`d1` metrics cannot
  erase real WordPress word gaps.

## Evidence

Focused red-first/green command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentPointBoundaryCurrentBaseTest.php
```

Result after fix: `1 test files, 10 assertions, 0 failures`.

Adjacent Type3/font sweep:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `30 test files, 875 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-marked-content-point-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock`, `Thin Text`, and
`Bad Join`, with `marked_content_point_widths_preserved=true`,
`mp_width_overrides_stale_widths=true`,
`dp_width_overrides_stale_widths=true`,
`malformed_dp_metric_rejected=true`,
`marked_content_property_decoys_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentPointBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-marked-content-point-boundary-currentbase.php
```

Result: all passed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
stream decoder, content tokenizer, Type3 CharProc width parser, marked-content
operand validation helpers, font-width grouping path, focused PHP tests, and
WordPress smoke renderer. No Python, PDFium, pypdfium2, Surya, Texify, Torch,
OCR, GPU/model execution, browser service, live provider, or external PDF tool
was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, CharProc
fallback exclusion, same-number CharProc stream generation selection, indirect
`/CharProcs` dictionary exact-generation selection, comment-split references,
top-level `/CharProcs` lookup, nested CharProcs dictionary parsing, Type3
Encoding Differences, named/base Encoding color glyph widths, Type3 CMap/CIDSet
grouping, Type3 glyph-name Unicode recovery, Type3 `/FontMatrix`
normalization, `wx/wy` vector transformation, compatibility sections, `BMC`/
`BDC` marked-content wrappers, malformed marked-content wrapper operands, path
setup, inline-image paint rejection, image/subtype CharProc boundaries, resource
fallback exclusion, pre-metric painting rejection, font-width advance slices,
xref/object-stream parser behavior, OCR/model execution, table recognition,
annotations, forms, image filters, metadata, or security preflight. The bounded
behavior is only valid `MP`/`DP` marked-content point operators before Type3
`d0`/`d1` metrics, plus malformed `DP` fail-closed preservation.

Root harness: not run - isolated micro-slice.
