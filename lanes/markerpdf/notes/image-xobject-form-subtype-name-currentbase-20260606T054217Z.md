# markerpdf image XObject Form subtype name boundary current-base

Session: `port-dev-markerpdf-image-xobject-20260606T054217Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T054217Z`
Accepted base: `e4ea169e4e976809e607e8fc8164a335a8929b16`

## Source Truth

- Upstream markerPDF keeps searchable text extraction separate from image rendering/handoff (`marker/pdf/extract_text.py` and `marker/pdf/images.py::render_image` in the manifest-pinned upstream family).
- Native no-GPU port boundary: preserve searchable text while reviewing Image XObject metadata and decoded payload hashes without exposing raster/image stream bytes in WordPress paragraph text.
- PDF name objects may contain hexadecimal escapes. A Form XObject written as `/Sub#74ype /F#6frm` is equivalent to `/Subtype /Form` and must be traversed before nested Image XObject review.

## Implementation

- `PdfTextExtractor::decodedFormXObjectBody()` now validates Form XObjects through the existing token-aware `isFormXObjectBody()` helper instead of a raw `/Subtype /Form` regex.
- `PdfTextExtractor::decodedAppearanceStreamWithFontMaps()` uses the same helper for appearance Form streams, keeping Form recognition consistent across image review and appearance parsing.
- The helper path reuses `streamDictionaryHasName()`, which resolves PDF-name escapes before matching dictionary names and values.

## Red-First Evidence

- Before the source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 3 assertions, 1 failures`
  - Failure: escaped `/Sub#74ype /F#6frm` Form XObject was not traversed, so `image_xobject_count` stayed `0` instead of `1`.

## Verification

- Focused:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 30 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-subtype-name-currentbase.php`
  - Result: emitted `escaped_form_subtype_resolved=true`, `nested_form_parent_object=5`, `nested_image_sha256_matches=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Adjacent image/resource family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php`
  - Result: `5 test files, 1254 assertions, 0 failures`
- Broader Image XObject family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php`
  - Result: `10 test files, 1432 assertions, 0 failures`
- Extra broader text-extractor check:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - Result: `1 test files, 626 assertions, 2 failures`
  - Existing broader failures were in ToUnicode usecmap inheritance expectations (`Import Blocks` and cyclic usecmap text), not in Image XObject/Form subtype traversal. This run is recorded as non-acceptance evidence only.

## Non-Overlap

This slice does not repeat accepted Image XObject CTM placement, Form matrix placement, resource provenance, entry wrappers, page resource inheritance, OPI proxy metadata, pattern wrappers, Type3 CharProc boundaries, optional-content review, clipping, ExtGState, masks, malformed `Do`/`cm`, text object boundaries, or compatibility-section handling. It only covers PDF-name escaped `/Subtype /Form` recognition before nested Image XObject review.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF tokenizer/object parser, stream dictionary name matching, stream decoders, and Image XObject boundary review. No Python, GPU/model execution, live OCR, external PDF tooling, or network service is required.
