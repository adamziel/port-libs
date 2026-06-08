# markerPDF Type3 CharProcs resource value wrapper boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T204744Z`

Accepted base: `898d03b9e45c99616bfd6eeb7951afc7149971b6`

## Source truth

Upstream markerPDF routes searchable PDF text extraction through pdftext/PDFium
before Markdown and WordPress-visible paragraphs are assembled. In the native
no-GPU PHP scope, Type3 `/CharProcs` are glyph programs, not page-visible text.
The same boundary applies to streams reachable only through a Type3 font or
CharProc `/Resources` dictionary. Even malformed resource values that wrap an
XObject or Pattern stream reference in an array or dictionary are glyph-private
paint resources, not stream-only fallback page content.

## Red-first evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceValueWrapperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL excludes wrapped Type3 CharProc XObject and Pattern resource values from fallback WordPress text on current base
Values are not identical
Expected: array (
  0 => 'Visible fallback content',
)
Actual: array (
  0 => 'Visible fallback content',
  1 => 'array-wrapped Type3 XObject text leak',
  2 => 'dictionary-wrapped Type3 XObject text leak',
  3 => 'array-wrapped Type3 pattern text leak',
  4 => 'dictionary-wrapped Type3 pattern text leak',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::collectType3PrivateResourceStreamGenerations()` now scans
raw Type3-private `/XObject` and `/Pattern` resource values in addition to the
existing normal resource-reference walkers. This lets the fallback exclusion
set find stream references embedded inside malformed array or dictionary
wrappers while preserving the existing valid XObject/Pattern recursion.

This does not change normal page content extraction or page-invoked Form
XObject extraction. It only prevents glyph-private resource streams from
becoming WordPress paragraphs in the stream-only fallback path.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceValueWrapperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS excludes wrapped Type3 CharProc XObject and Pattern resource values from fallback WordPress text on current base

1 test files, 10 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceValueWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsArrayWrapperBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 47 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-resource-value-wrapper-currentbase.php
```

The smoke emits one `Visible fallback content` paragraph and reports
`direct_charproc_payload_excluded=true`,
`array_wrapped_xobject_excluded=true`,
`dictionary_wrapped_xobject_excluded=true`,
`array_wrapped_pattern_excluded=true`,
`dictionary_wrapped_pattern_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. The patch reuses the native object scanner,
exact-generation object lookup, Type3 font detection, resource dictionary
parser, stream decoder, object-reference walker, and fallback text extractor.
Python, pdftext, pypdfium/PDFium, Poppler, Ghostscript, OCR/model workers, and
external PDF tools remain excluded by the no-GPU markerPDF scope.

## Non-overlap

This does not repeat accepted direct CharProc stream fallback exclusion,
valid Type3 resource XObject recursion, Pattern resource fallback exclusion,
array-wrapped `/CharProcs` dictionary exclusion, Type3 width-vector parsing,
FontMatrix scaling, CMap/CIDSet grouping, Type3 image XObject review, xref
repair, stream-filter recovery, annotations, forms, metadata, security
preflight, table/equation handoffs, or OCR/model execution. The bounded
behavior is only fail-closed stream-only fallback exclusion for malformed
array/dictionary-wrapped XObject and Pattern values reachable solely from
Type3 glyph resources.
