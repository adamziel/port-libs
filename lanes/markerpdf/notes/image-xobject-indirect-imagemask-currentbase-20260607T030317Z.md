# markerpdf-image-xobject-indirect-imagemask-current-base-20260607T030317Z

Base accepted HEAD: `a078a096a4cf93f92c4400252bcd9ac19a5f846a`.

## Scope

Native no-GPU markerPDF Image XObject boundary work. This slice covers
top-level `/ImageMask` values that are indirect scalar booleans:

```text
<< /Subtype /Image /Width 1 /Height 1 /ImageMask 6 0 R /Filter /FlateDecode >>
6 0 obj
true
endobj
```

Before this patch the Image XObject review path only recognized direct boolean
values, and one path used a non-top-level dictionary scan. A stencil image with
`/ImageMask 6 0 R` was therefore classified as an ordinary image with no
one-bit stencil fallback or paint-color handoff. The parser now resolves exact
generation indirect scalar booleans from the top-level dictionary value before
Image XObject review. Nested private `/ImageMask` decoys remain ignored.

The upstream boundary is markerPDF's split between searchable text extraction
and image rendering/review handoff: image payload bytes stay out of text while
image/stencil metadata remains available for WordPress media review.

## Evidence

Pre-fix local probe showed `/ImageMask 6 0 R` returned
`image_mask=false`, `bits_per_component=null`, and no stencil paint-color row.

Focused run after the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectImageMaskBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves top-level indirect ImageMask booleans before image XObject review
1 test files, 41 assertions, 0 failures
```

Adjacent Image XObject family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectDuplicateResourceNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExplicitSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormAliasSuppressionCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectBBoxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectImageMaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectResourceEntryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTopLevelDimensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 1930 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-imagemask-currentbase.php
```

The smoke emits two paragraphs and metadata confirming
`indirect_stencil_image_mask=true`, `indirect_stencil_bits_per_component=1`,
`ordinary_image_mask_from_indirect_false=false`,
`ordinary_image_ignored_private_imagemask_decoy=true`,
`payload_in_visible_text=false`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Final local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectIndirectImageMaskBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectIndirectImageMaskBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-imagemask-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-imagemask-currentbase.php
```

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement,
optional-content visibility, clipping, dimensions, Mask/SMask stream metadata,
direct ImageMask paint-color handling, top-level dimension filtering, resource
entry wrappers, Pattern/Form/Type3 image handoffs, DCT/CCITT/JPX/JBIG2 filter
metadata, inline image tokenizer behavior, encrypted fail-closed handling, or
OCR/model image rendering. The bounded behavior is exact-generation resolution
of top-level indirect boolean `/ImageMask` values before native image review.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object
table, exact-generation object-body resolver, top-level PDF name scanner, Flate
stream decoder, Image XObject review metadata path, and WordPress smoke path.
GPU/model/OCR, PDFium, and external PDF tools remain intentionally out of scope
for this no-GPU markerPDF slice.
