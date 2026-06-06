# Image XObject Indirect BBox Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260606T221337Z`
Base: `e1f112b8ea648ea7e836cfb9bbd4f19dce3d5584`

## Source Truth

Native PDF image review already tracks Image XObject placements through q/Q/cm graphics state, clipping paths, Form XObject resources, and tiling Pattern paints. The remaining boundary in this slice is PDF object indirection inside rectangle arrays: Form XObject and tiling Pattern `/BBox` arrays may contain indirect numeric operands, and those operands must be resolved before clipping nested Image XObject review metadata.

This is bounded to native searchable-PDF parser behavior. No raster decoding, OCR, Surya/Texify/Torch, GPU/model execution, Streamlit/FastAPI workers, or external PDF tools are used.

## Behavior

`PdfTextExtractor::pdfRectangleValueAfterName()` now resolves numeric indirect object operands before normalizing PDF rectangles. This prevents object identifiers such as `31 0 R` from being interpreted as coordinate values when Form XObject or tiling Pattern `/BBox` arrays clip nested Image XObject placements.

The slice adds two focused cases:

- Form XObject `/BBox [31 0 R 32 0 R 33 0 R 34 0 R]` clips a nested image to the resolved rectangle `[100, 200, 140, 220]`.
- Tiling Pattern `/BBox [41 0 R 42 0 R 43 0 R 44 0 R]` clips pattern-painted images to the resolved rectangle `[3, 4, 20, 10]`.

Both fixtures keep image payload bytes out of visible text.

## Red-First Evidence

Before the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectBBoxBoundaryCurrentBaseTest.php`

Result: `1 test files / 24 assertions / 2 failures`.

Failure shape:

- Form XObject clip bbox used object ids: expected `[[100.0,200.0,140.0,220.0]]`, actual `[[1340.0,200.0,1380.0,200.0]]`.
- Pattern clip bbox used object ids: expected `[[3.0,4.0,20.0,10.0]]`, actual `[[44.0,4.0,44.0,4.0]]`.

## Verification

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectBBoxBoundaryCurrentBaseTest.php`

Result: `1 test files / 51 assertions / 0 failures`.

Broader Image XObject family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectDuplicateResourceNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormAliasSuppressionCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectBBoxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectResourceEntryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php`

Result: `16 test files / 1796 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-bbox-currentbase.php`

Expected metadata includes `image_xobject_count=2`, `invoked_image_xobject_count=2`, `form_bbox_indirect_numbers_resolved=true`, `pattern_bbox_indirect_numbers_resolved=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Manifest And Status Delta

- `phpPass`: `2696 -> 2698`.
- `wordpressScenarios`: `2271 -> 2272`.
- `pdfImageXObjectPlacementBoundaryCurrentBaseBehaviors`: `2 -> 4`.
- `mappedPdfImageXObjectPlacementBoundaryCurrentBaseBehaviors`: `2 -> 4`.

## Non-Overlap

This patch does not repeat accepted Image XObject payload exclusion, Form resource provenance, Form subtype-name resolution, direct clipping paths, q/Q/cm placement, OPI proxy, color-key mask, Type3 CharProc, page-resource inheritance, CMap, font-width, xref repair, metadata, annotations, forms, encryption, OCR, or model-worker slices. It only resolves indirect numeric operands for rectangle arrays used as Image XObject clipping boundaries.

## Dependency Closure

No new support component is needed. The slice reuses the existing native object table, PDF array resolver, content-stream parser, and WordPress smoke harness.
