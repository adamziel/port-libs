# markerPDF annotation link destination generation boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T122009Z`
Session: `port-dev-markerpdf-annotations-links-20260605T122009Z`
Base accepted HEAD: `2eb3d4038b9e93816e26565fe8d737d48cc80c63`

## Source truth

Upstream markerPDF promotes safe PDF link annotations into Markdown only after
searchable PDF text extraction. In the native no-GPU PHP parser, local
destination actions must follow PDF indirect-reference boundaries: page objects
are generation-qualified, so a link destination to `4 1 R` must not be resolved
through stale page generation `4 0 R` when the current page tree is rooted at
generation `2 1 R`.

## Behavior

`PdfActionReviewExtractor` now builds page indexes from exact page-tree
references instead of object numbers alone. Local Link `/Dest` and GoTo action
destinations resolve to a page only when the referenced object and generation
are present in the current page tree. References to stale same-number page
generations are preserved as annotation review metadata but no longer become
clickable WordPress destination links.

The focused fixture covers:

- current catalog `/Pages 2 1 R` with current page tree kids `[3 1 R 4 1 R]`;
- a current Link annotation `/Dest [4 1 R /FitH 720]` promoted with
  `destination_page=1`;
- a stale Link annotation `/Dest [4 0 R /FitH 111]` left out of promoted link
  rows even though stale generation `4 0 obj` appears later in the file;
- visible text limited to current page-generation content while stale page
  generation text and annotation payloads stay out of Gutenberg paragraphs.

## Evidence

Red-first focused command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php`

Result before the source fix: `1 test files, 4 assertions, 1 failures`; the
current `4 1 R` destination resolved to stale page index `0`.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php`

Result: `1 test files, 28 assertions, 0 failures`.

Adjacent annotation-link family command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCommentDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationFlagsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIsMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationParentGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationStructTreeGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php`

Result: `27 test files, 987 assertions, 0 failures`.

Shared action/named-destination focused command:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'Pdf*Action*Test.php' -o -name 'PdfNamedDestination*Generation*Test.php' -o -name 'PdfNamedDestination*Page*Test.php' -o -name 'PdfAnnotationExtractorTest.php' \) | sort)`

Result: `48 test files, 2848 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-annotation-link-destination-generation-boundary-currentbase.php`

Result: emits `promoted_link_objects=[7]`, `destination_pages=[1]`,
`current_destination_promoted=true`, `stale_destination_promoted=false`,
`stale_annotation_review_preserved=true`,
`stale_generation_promoted_link_excluded=true`,
`stale_page_text_visible=false`, `executes_pdf_actions=false`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted top-level `/Annots` selection, escaped `/Annots`
names, exact annotation object generation selection, exact annotation `/P`
page membership, indirect numeric geometry operands, parent CropBox generation
boundaries, URI `/Base`, URI control-byte blocking, IsMap review, primary
action `/Next` handling, remote GoToR, crop/rotation/QuadPoints geometry,
widget field inheritance, link presentation metadata, or named-destination
name-tree generation and Limits handling. The bounded behavior is specifically
same-object page-generation selection for local Link destination page indexes
inside the shared action reviewer.

## Dependency closure

No new support component is needed. This slice reuses the native PHP PDF object
parser, generation-qualified object table, page-tree traversal, action review
extractor, link span promoter, Markdown post-processor, and WordPress smoke
path. Live OCR, Surya/Torch/Texify models, pypdfium/PDFium rendering, external
PDF tools, and exact upstream GPU/model benchmark parity remain intentionally
out of scope for the no-GPU markerPDF lane.
