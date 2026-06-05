# markerPDF Type3 CharProcs text-state boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T122009Z`

Base accepted HEAD: `2eb3d4038b9e93816e26565fe8d737d48cc80c63`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before OCR/model handoff. At this native no-GPU parser boundary, Type3
`/CharProcs` are glyph programs, not visible page text. Their `d0`/`d1`
operators provide glyph metrics for text advance grouping, while non-painting
CharProc setup may appear before the metric operator.

Existing accepted slices already cover direct metrics, exact generations,
indirect/comment-split `/CharProcs`, FontMatrix normalization, path setup,
marked-content setup, compatibility sections, stream filters, resource
fallback exclusion, width precedence, and pre-metric painting rejection. This
slice adds the adjacent non-painting text-state setup boundary: `Tc`, `Tw`,
`Tz`, `TL`, `Tf`, `Tr`, and `Ts` before `d0`/`d1`.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTextStateBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'WideBlock',
  1 => 'Thin Text',
  2 => 'Guard Gap',
)
Actual: array (
  0 => 'Wide Block',
  1 => 'ThinText',
  2 => 'Guard Gap',
)

1 test files, 1 assertions, 1 failures
```

That proved the native parser rejected valid text-state setup before Type3
metrics and fell back to stale `/Widths` values in both directions.

## Implementation

`PdfTextExtractor::type3CharProcAllowsPreMetricSetupOperator()` now accepts
well-formed text-state setup before a Type3 metric:

- `Tf` requires exactly a font-name operand and numeric size;
- `Tc`, `Tw`, `Tz`, `TL`, `Tr`, and `Ts` require exactly one numeric operand;
- text showing, `BT` text objects, malformed text-state operands, inline
  images, XObject invocation, and painting before metrics remain rejected.

The focused fixture proves valid text-state setup preserves `WideBlock` and
`Thin Text`, while `(bad text-state operand) /Fghost 9 Tf` still fails closed
to `/Widths`/`MissingWidth`, preserving `Guard Gap`. CharProc payload text stays
out of visible WordPress paragraphs.

## Evidence

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsTextStateBoundaryCurrentBaseTest.php
```

Result: `1 test files, 12 assertions, 0 failures`.

Adjacent Type3/font sweep:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfFont(Type3|SimpleType3|CMapCidType3|CidType3).*Test\.php$' | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `33 test files, 906 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-text-state-boundary-currentbase.php
```

Result: emitted paragraphs for `WideBlock`, `Thin Text`, and `Guard Gap` with
`text_state_charproc_widths_preserved=true`,
`malformed_text_state_operand_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, exact-generation object lookup, Type3 `/CharProcs` dictionary parser,
stream decoder, content tokenizer, FontDescriptor fallback width handling,
text advance grouping path, focused PHP runner, and WordPress smoke renderer.
No Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution,
browser service, live provider, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, fallback
payload exclusion, exact-generation stream/dictionary lookup, comment-split
references, Encoding generation selection, top-level/nested `/CharProcs`
dictionary parsing, FontMatrix normalization, width-vector transforms,
path-setup wrappers, marked-content wrappers, BX/EX compatibility sections,
inline-image or pre-metric paint rejection, resource fallback exclusion, stale
`/Widths` precedence, Type3 CMap/CIDSet behavior, xref repair, stream-filter
boundaries, image filters, annotations, forms, metadata, security preflight,
OCR/model work, or table/equation supplied-boundary work. The bounded behavior
is only non-painting Type3 CharProc text-state setup before metrics.
