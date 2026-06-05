# markerPDF Type3 CharProcs pre-metric operand boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T125729Z`

Base accepted HEAD: `a7fcab9938b3f699e7572fbf8e5c7dcf121bd3dc`

## Source truth

Upstream markerPDF uses `pdftext`/PDFium page text extraction before model
handoffs. In the current no-GPU PHP scope, native searchable-PDF text
extraction must keep Type3 `/CharProcs` as glyph programs: valid non-painting
setup may appear before `d0`/`d1`, but malformed setup operands must not make a
later glyph metric authoritative for WordPress paragraph grouping.

The PDF Type3 boundary here is intentionally narrow: fixed-arity pre-metric
graphics/path operators now validate numeric/name operands before the parser
accepts a later `d0`/`d1` width. This prevents a malformed glyph program such
as a literal string before `m` from overriding fallback `/Widths` while still
preserving valid `cm`/path setup before the metric operator.

## Behavior

- valid `q`, `cm`, `m`, `l`, `h`, `Q` setup before `d0` keeps `ValidPath`
  joined through the Type3 CharProc width;
- malformed pre-metric path operands reject the later `d0`, so `Bad Path`
  falls back to the Type3 `/Widths` array;
- CharProc payload text stays excluded from visible WordPress paragraphs;
- no Python, OCR/model, GPU, or external PDF tooling is invoked.

## Red-first evidence

Before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPreMetricOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed Type3 CharProc pre-metric path operands before WordPress text grouping on current base
Expected: ['ValidPath', 'Bad Path']
Actual: ['ValidPath', 'BadPath']
1 test files, 1 assertions, 1 failures
```

## Verification

Focused run after patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPreMetricOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed Type3 CharProc pre-metric path operands before WordPress text grouping on current base
1 test files, 10 assertions, 0 failures
```

Adjacent Type3/font run:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name '*Type3*Test.php' -o -name '*CharProc*Test.php' \) | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 34 selected test files (root lock skipped)
34 test files, 916 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-premetric-operand-boundary-currentbase.php
```

The smoke emits `ValidPath`, `Bad Path`,
`valid_premetric_setup_width_preserved=true`,
`malformed_premetric_path_operands_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PDF tokenizer,
Type3 CharProc width parser, `/Widths` fallback, and WordPress block smoke
paths already present under `lanes/markerpdf/**`.

## Non-overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, exact
generation CharProcs lookup, comment-split CharProc references, compatibility
sections, initial-paint rejection, inline-image rejection, marked-content
wrappers, text-state operand validation, operand-count validation on the metric
operators themselves, path setup admission, resource fallback exclusion,
FontMatrix/width-vector normalization, CMap/CIDSet Type3 spacing, xref/object
stream parser behavior, annotations, forms, image filters, metadata, OCR/model
execution, or table recognition. The bounded behavior is fixed-arity
pre-metric setup operand validation before Type3 glyph metrics can override
fallback `/Widths`.
