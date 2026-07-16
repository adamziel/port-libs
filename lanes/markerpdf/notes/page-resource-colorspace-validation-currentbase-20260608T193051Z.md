# Page Resource ColorSpace Validation Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T193051Z`

Base: `13ef792b9726ca74a5372ce5b45a701d4366670c`

## Source Truth

Upstream markerPDF relies on native PDF page resources for searchable-PDF text import and review metadata before WordPress conversion. Under the no-GPU scope, this slice stays in native PDF parser behavior: inherited page `/Resources` dictionaries and their `/ColorSpace` subdictionary operands.

The PDF color-space operand boundary for this native port is:

- valid resource values are direct or indirect PDF names, such as `/DeviceRGB`;
- valid resource values are direct or indirect color-space arrays, such as `[/Indexed /DeviceRGB 0 <00>]` or `[/CalRGB << /WhitePoint [1 1 1] >>]`;
- invalid resource decoys such as `null`, literal strings, numbers, and standalone dictionaries are not color-space operands and must not be promoted into page-boundary review metadata.

## Behavior

`PdfPagePropertyExtractor` now validates inherited page ColorSpace resource entries before listing `color_space_names`. Valid direct and indirect name/array operands are preserved, while invalid direct values are excluded.

`PdfTextExtractor` now applies the same name-or-array operand rule before handing inherited ColorSpace resources to inline-image tokenizer boundary logic, so invalid decoy values do not affect image-payload ownership decisions.

## Red-First Evidence

Before the implementation change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceColorSpaceValidationCurrentBaseTest.php`

failed with `1 test files, 11 assertions, 1 failures` because `GoodIndirectName` was missing from `color_space_names` and invalid `BadString`, `BadNumber`, and `BadDictionary` entries were promoted.

## Verification

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceColorSpaceValidationCurrentBaseTest.php`

Result: `1 test files, 21 assertions, 0 failures`.

Adjacent page-resource family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceColorSpaceValidationCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInlineImageColorSpaceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCommentReferenceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceNullWhitespaceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryCommentReferenceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryObjectTailBoundaryCurrentBaseTest.php`

Result: `8 test files, 406 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-page-resource-colorspace-validation-currentbase.php`

Result: exits `0`; emits two WordPress paragraph blocks, `color_space_names` for `GoodName`, `GoodArray`, `GoodIndirectName`, and `GoodIndirectArray`, and `invalid_color_space_entries_excluded=true`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, page-resource inheritance resolver, indirect object resolver, and page-boundary metadata extractor. It does not run Python, OCR, Surya, Texify, Torch, pypdfium/PIL, raster rendering, external PDF tools, or live provider services.

## Non-Overlap

This does not repeat recent Type3 CharProc compatibility boundaries, xref repair, stream filter recovery, page geometry, image rendering metadata, annotations/forms/security preflight, or model/OCR work. It only adds a stricter inherited `/ColorSpace` operand boundary for page resources and directly coupled WordPress review metadata.

Root harness status: not run - isolated micro-slice.
