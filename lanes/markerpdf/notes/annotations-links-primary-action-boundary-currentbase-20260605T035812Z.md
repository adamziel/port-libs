# markerPDF annotations links primary-action boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T035812Z`
Session: `port-dev-markerpdf-annotations-links-20260605T035812Z`
Base accepted HEAD: `9cb6468056fab1a0e88e67740ac56fbb783fc17f`

## Source truth

Upstream markerPDF promotes PDF link references into Markdown after extracting
searchable PDF text. In the native PHP no-GPU port, PDF action review already
walks `/Next` chains without executing actions. This slice keeps that review
surface intact while preserving the PDF action boundary: the Link annotation's
direct `/A` action is the primary activation action; chained `/Next` actions are
follow-ups and must not rescue a blocked JavaScript or Launch primary into a
clickable WordPress href.

## Behavior

`PdfLinkAnnotationExtractor` now ignores actions marked as chained while choosing
the primary link action for span promotion. Direct safe primary URI/GoTo/GoToR
actions still promote supplied pdftext spans. Safe URI/local-destination/remote
document actions reached only through `/Next` after blocked JavaScript or Launch
primaries remain non-executing annotation review metadata.

## Evidence

Red-first focused command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php`

Result before the extractor fix: `1 test files, 11 assertions, 1 failures`;
annotation objects `8` and `9` incorrectly promoted chained safe actions as
links.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php`

Result: `1 test files, 40 assertions, 0 failures`.

Adjacent annotation-link family command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotation*.php lanes/markerpdf/tests/PdfPageAnnots*LinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php`

Result: `13 test files, 530 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-annotation-link-primary-action-boundary-currentbase.php`

Result: emits `promoted_link_objects=[7]`,
`promoted_link_uris=[https://example.com/direct-safe]`,
`chained_uri_review_only=true`, `remote_gotor_review_only=true`,
`annotation_payload_text_excluded_from_visible_text=true`,
`executes_python_or_models=false`, `executes_external_pdf_tools=false`, and
`executes_pdf_actions=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation-name
handling, exact object-generation link selection, URI control-byte filtering,
crop/rotation/QuadPoints geometry, remote GoToR direct-primary review, widget
field inheritance, link presentation metadata, or generic `/Next` action review.
The bounded behavior is only direct-primary link promotion versus safe chained
`/Next` review after blocked primary actions.

## Dependency closure

No new support component is needed. The patch reuses the existing native PDF
parser, annotation action review extractor, link span promoter, and Markdown
post-processor. GPU/model OCR, pypdfium, external PDF tools, and live PDF action
execution remain intentionally out of scope for this markerPDF worker.
