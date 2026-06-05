# markerPDF Type3 CharProcs marked-content operand boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T110828Z`

Base accepted HEAD: `0147d7cd16fbde22482892e48538f86512fde76c`

## Source truth

Upstream markerPDF relies on searchable-PDF text extraction before any model
handoff. In the native PHP parser, Type3 `/CharProcs` streams are glyph
programs, not visible page text, and their first valid `d0`/`d1` metric sets
the glyph advance used for WordPress paragraph grouping.

The accepted marked-content boundary already allows well-formed `BMC`, `BDC`,
and `EMC` wrappers before Type3 metrics. This slice tightens that boundary:
malformed marked-content operators with extra operands must not be used to
discard arbitrary tokens before a later `d0`/`d1` metric. The parser now fails
closed to `/MissingWidth` for those malformed wrappers while keeping valid
marked-content wrappers accepted.

## Red check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'Bmc Gap',
  1 => 'Bdc Gap',
  2 => 'WideOk',
)
Actual: array (
  0 => 'BmcGap',
  1 => 'BdcGap',
  2 => 'WideOk',
)

1 test files, 1 assertions, 1 failures
```

That proved extra numeric operands before `BMC` and `BDC` were being cleared as
pre-metric setup, allowing the later `1000 0 d0` width to collapse the expected
WordPress word gap.

## Implementation

`PdfTextExtractor::type3CharProcAllowsPreMetricSetupOperator()` now receives
the current operand stack and validates only the marked-content wrapper
operators:

- `BMC` requires exactly one name operand;
- `BDC` requires exactly two operands with a name tag first;
- `EMC` requires no operands.

Other accepted pre-metric setup operators keep their existing behavior. Valid
marked-content wrappers, path setup, compatibility sections, inline-image
paint rejection, and exact `d0`/`d1` operand-count validation remain covered by
the adjacent Type3 suite.

## Evidence

Focused red/green test after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

Adjacent Type3/font sweep:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*.php lanes/markerpdf/tests/PdfFontSimpleType3*.php lanes/markerpdf/tests/PdfFontCMapCidType3*.php lanes/markerpdf/tests/PdfFontCidType3*.php
```

Result: `30 test files, 256 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-marked-content-operand-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `Bmc Gap`, `Bdc Gap`, and `WideOk`,
with `malformed_marked_content_metrics_rejected=true`,
`valid_marked_content_metric_preserved=true`,
`malformed_metric_payloads_not_grouped=true`,
`charproc_payload_visible_text_excluded=true`,
`marked_content_property_decoy_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Changed PHP lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-marked-content-operand-boundary-currentbase.php
```

Result: no syntax errors.

Lane status JSON parse:

```bash
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
```

Result: `lane-status json ok`.

Whitespace check:

```bash
git diff --check -- lanes/markerpdf
```

Result: no output, exit 0.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner,
exact-generation object lookup, Type3 CharProc dictionary resolver, stream
decoder, content tokenizer, FontDescriptor fallback-width handling, text
advance grouping path, and WordPress smoke path. No Python, PDFium, pypdfium2,
Poppler, Ghostscript, OCR, Surya, Texify, Torch, GPU/model execution, browser
service, or external PDF tool was run.

## Non-overlap

This does not repeat accepted Type3 CharProc fallback-payload exclusion, exact
object generation selection, comment-split references, indirect CharProcs
dictionary generation, nested/top-level CharProcs dictionary guards, stream
filters, FontMatrix normalization, width-vector transforms, width-array
precedence, pre-metric paint rejection, inline-image paint rejection, valid
marked-content wrapper acceptance, path setup acceptance, compatibility-section
handling, resource-subtype decoys, Type3 CMap/CIDSet grouping, or Type0 CMap
source-width work. The new boundary is specifically malformed marked-content
operand stacks before Type3 CharProc metrics.

Root harness: not run - isolated micro-slice.
