# markerPDF Type3 CharProcs resource fallback boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T073016Z`

Accepted base: `2f0c67a4423e347ffc4b41c379e28870d045a8ab`

## Source truth

Upstream markerPDF routes searchable PDF text extraction through pdftext/PDFium
before Markdown and WordPress-visible paragraphs are assembled. In the native
no-GPU PHP scope, Type3 `/CharProcs` are glyph programs, not page-visible text.
That boundary also applies to Form XObject resources reachable only from a
Type3 font `/Resources` dictionary or from a CharProc stream `/Resources`
dictionary: those streams are glyph-private paint resources, not fallback page
content.

## Red-first evidence

Before the source change, an ad hoc focused probe with no page tree showed the
stream-only fallback correctly excluded the direct CharProc stream but still
promoted its glyph-private Form XObject resources:

```text
array (
  0 => 'Visible fallback content',
  1 => 'Glyph resource form leak',
)
Visible fallback content
Glyph resource form leak
```

The checked-in focused fixture expands that case to cover:

- a Type3 font-level `/Resources /XObject` Form stream;
- a CharProc stream-level `/Resources /XObject` Form stream;
- a nested Form XObject below the CharProc resource stream;
- a separate ordinary fallback stream that must remain visible.

## Implementation

`PdfTextExtractor::allDecodedStreams()` now builds a second Type3-private
exclusion set in addition to direct CharProc stream objects. The new set walks
XObject references from Type3 font resources and CharProc stream resources,
recursing through nested Form XObjects, and excludes those exact
object-number/generation streams from stream-only fallback extraction.

This does not change normal page content expansion or page-invoked Form XObject
extraction. It only prevents glyph-private resource streams from becoming
WordPress paragraphs in the no-page-tree fallback path.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS excludes Type3 CharProc resource streams from stream-only fallback WordPress text extraction on current base
1 test files, 9 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name '*Type3*Test.php' -o -name '*CharProc*Test.php' \) | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 25 selected test files (root lock skipped)
25 test files, 831 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-resource-fallback-currentbase.php
```

The smoke emits `Visible fallback content` and reports
`direct_charproc_payload_excluded=true`,
`top_level_type3_resource_form_excluded=true`,
`stream_type3_resource_form_excluded=true`,
`nested_type3_resource_form_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. The patch reuses the native object scanner,
exact-generation object lookup, Type3 font detection, CharProcs dictionary
resolution, resource dictionary parser, XObject reference parser, Form XObject
resource traversal, stream decoder, and fallback text extractor. Python,
pdftext, pypdfium/PDFium, Poppler, Ghostscript, OCR/model workers, and external
PDF tools remain excluded by the no-GPU markerPDF scope.

## Non-overlap

This does not repeat accepted Type3 direct CharProc stream fallback exclusion,
same-number CharProc generation selection, exact indirect CharProcs dictionary
generation, comment-split references, subtype/top-level/nested-dictionary
guards, FontMatrix/vector width scaling, initial operator/inline-image
fail-closed metric parsing, resource-subtype image decoys, color glyph width
resources under normal page extraction, stale `/Widths` precedence, Type3
CMap/CIDSet grouping, xref/object-stream parser behavior, image XObject page
boundaries, OCR/model execution, table recognition, annotations, forms,
metadata, or security preflight. The bounded behavior is only stream-only
fallback exclusion for XObject resource streams reachable solely from Type3
glyph programs.
