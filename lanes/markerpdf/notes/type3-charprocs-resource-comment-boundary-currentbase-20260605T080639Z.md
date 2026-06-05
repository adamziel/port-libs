# markerPDF Type3 CharProcs resource comment boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T080639Z`

Accepted base: `90c134ef160d0dae68131072cad507459f78c7e8`

## Source truth

Upstream markerPDF reaches searchable-PDF text through pdftext/PDFium before
Markdown and WordPress paragraphs are assembled. In this no-GPU PHP lane, Type3
`/CharProcs` glyph programs and their private `/Resources /XObject` paint
resources are not page-visible fallback text. PDF comments are whitespace inside
indirect references, including `/Resources /XObject` entries such as
`/GlyphPaint 5 % comment\n 0 R`.

## Red-first evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceCommentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats PDF comments as whitespace inside Type3 CharProc resource references before fallback extraction on current base
Expected: array (
  0 => 'Visible fallback content',
)
Actual: array (
  0 => 'Visible fallback content',
  1 => 'top comment-split Type3 resource leak',
  2 => 'stream comment-split Type3 resource leak',
  3 => 'nested comment-split Type3 resource leak',
)
```

That proved stream-only fallback excluded the direct CharProc stream but failed
to walk comment-split glyph-private Form XObject references.

## Implementation

`PdfTextExtractor::topLevelResourceReferenceEntries()` now uses the existing
comment-aware indirect-reference token reader instead of a whitespace-only regex
when parsing resource dictionary entries. The Type3 private resource walker can
therefore find exact object/generation references split by PDF comments and
exclude the reachable Form XObject streams from fallback WordPress text.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfFontType3CharProcsResourceCommentBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfFontType3CharProcsResourceCommentBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-resource-comment-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-resource-comment-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceCommentBoundaryCurrentBaseTest.php
1 test files, 8 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfFontType3|PdfFont.*Type3|PdfFontCMapCidType3' | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
26 test files, 839 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
8 test files, 802 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-resource-comment-currentbase.php
```

The smoke emits only `Visible fallback content` and reports
`direct_charproc_payload_excluded=true`,
`top_level_type3_resource_form_excluded=true`,
`stream_type3_resource_form_excluded=true`,
`nested_type3_resource_form_excluded=true`,
`pdf_comments_in_resource_references_are_whitespace=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner,
comment-aware indirect-reference parser, exact-generation object lookup, Type3
font detection, CharProcs dictionary resolution, resource dictionary parser,
Form XObject resource traversal, stream decoder, and fallback text extractor.
Python, pdftext, pypdfium/PDFium, Poppler, Ghostscript, OCR/model workers, GPU
execution, and external PDF tools remain intentionally out of scope.

## Non-overlap

This does not repeat accepted direct Type3 CharProc stream fallback exclusion,
Type3 private resource fallback exclusion, same-number CharProc generation
selection, exact indirect CharProcs dictionary generation, comment-split
CharProc dictionary references, subtype/top-level/nested-dictionary guards,
FontMatrix/vector width scaling, initial operator/inline-image fail-closed
metric parsing, resource-subtype image decoys, color glyph width resources under
normal page extraction, page-resource generation filtering, xref/object-stream
repair, image XObject review, OCR/model execution, table recognition,
annotations, forms, metadata, or security preflight. The bounded behavior is
only PDF-comment-as-whitespace parsing inside Type3 glyph-private resource
references before fallback stream exclusion.
