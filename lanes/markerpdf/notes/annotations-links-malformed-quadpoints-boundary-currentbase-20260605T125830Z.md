# markerPDF malformed Link QuadPoints boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T125830Z`
Session: `port-dev-markerpdf-annotations-links-20260605T125830Z`
Base accepted HEAD: `a7fcab9938b3f699e7572fbf8e5c7dcf121bd3dc`

## Source truth

Upstream markerPDF promotes searchable PDF link annotations only after the PDF
parser/pdftext boundary, and no PDF action execution is needed for WordPress
import. PDF Link annotation `/QuadPoints` entries define clickable
quadrilateral text regions as complete groups of eight numeric coordinates.
Malformed coordinate tokens must not be allowed to shift later coordinates into
a synthetic clickable rectangle.

## Behavior

`PdfLinkAnnotationExtractor` now parses Link annotation `/QuadPoints` as
contiguous groups of eight numeric coordinates. A malformed token or unresolved
numeric reference resets the current group. Later complete groups are still
preserved, so a damaged first quad does not make `/Rect` or shifted coordinates
donate a WordPress href while a later valid quad can still promote its span.

The focused fixture covers one Link annotation with:

- broad `/Rect [72 700 345 718]` review geometry;
- malformed first `/QuadPoints` group interrupted by `/BadCoordinate`;
- a later valid `/QuadPoints` group around the `Valid quad` span;
- supplied pdftext-like spans for `Broken quad`, `Valid quad`, and `Rect decoy`.

Only the later valid quad becomes a WordPress link.

## Evidence

Red-first focused command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php`

Result before the source fix: `1 test files, 5 assertions, 1 failures`; the
extractor recombined coordinates around `/BadCoordinate` into a shifted quad.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php`

Result: `1 test files, 17 assertions, 0 failures`.

Adjacent QuadPoints geometry command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php`

Result: `4 test files, 124 assertions, 0 failures`.

Adjacent annotation-link family command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCommentDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationFlagsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIsMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationParentGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationStructTreeGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php`

Result: `28 test files, 1004 assertions, 0 failures`.

Syntax:

`php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php`

`php -l lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-link-malformed-quadpoints-currentbase.php`

Result: all report no syntax errors.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-link-malformed-quadpoints-currentbase.php`

Result: emits `malformed_first_quad_linked=false`,
`valid_later_quad_linked=true`, `rect_decoy_linked=false`,
`visible_text_imported=true`, `annotation_payload_text_visible=false`,
`executes_pdf_actions=false`, `executes_javascript=false`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted generic URI extraction, local/remote GoTo action
review, page-level `/Annots` ownership, escaped annotation keys, exact object
generation selection, annotation `/P` page membership, URI control-byte
blocking, catalog URI Base, IsMap review, primary `/A` array rejection,
previous URI `/PA` review, remote GoToR review, name-tree `/Limits`, CropBox
clipping, rotation/UserUnit mapping, valid Link `/QuadPoints` parsing, text
markup `/QuadPoints`, widget field inheritance, link presentation metadata, or
StructTree link context. The bounded behavior is specifically malformed Link
annotation `/QuadPoints` coordinate-group boundaries.

## Dependency closure

No new support component is needed. This slice reuses the native PDF object
scanner, annotation action reviewer, generation-aware indirect numeric operand
resolver, page geometry transforms, supplied marker/pdftext span model,
Markdown post-processor, and WordPress smoke path. Live OCR, Surya/Torch/Texify
models, pypdfium/PDFium rendering, external PDF tools, and exact upstream
GPU/model benchmark parity remain intentionally out of scope for the no-GPU
markerPDF lane.
