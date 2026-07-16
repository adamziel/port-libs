# Link Annotation URI Base Boundary Current Base

Slice: `markerpdf-annotations-links-boundary-current-base-20260605T210110Z`

Accepted base: `68e05d76831a99dc0655fe8f9599b7d9f68bfc9f`

## Source Truth

- PDF link annotation `/A << /S /URI /URI (...) >>` actions may use a
  document-level catalog `/URI << /Base (...) >>` value to resolve relative
  URI strings.
- WordPress import should promote the resolved safe URI as the href while
  preserving the raw relative URI and catalog base as review metadata.
- Upstream markerPDF's searchable-PDF path treats link annotations as native
  PDF parser metadata; this no-GPU slice does not execute PDF actions, OCR,
  Surya, Texify, Torch, browser rendering, or external PDF tools.

## Behavior

- `PdfLinkAnnotationExtractor::applyLinksToPages()` now copies the
  already-reviewed URI resolution fields from the selected link row onto the
  promoted span:
  `link_raw_uri`, `link_uri_base`, `link_uri_relative`, and
  `link_uri_resolved_from_base`.
- Relative path, query-only, and fragment-only URI actions resolve against
  the catalog base before Markdown link promotion.
- Absolute safe URI actions remain promoted without raw/base metadata.
- Annotation payload strings and action target text remain excluded from
  visible searchable text.

## Evidence

Red-first on accepted base after adding the focused test and before the source
edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationUriBaseBoundaryCurrentBaseTest.php`

Result: `1 test files, 14 assertions, 1 failures`; the selected span had the
resolved `link_uri`, but `link_raw_uri` was missing.

After the source edit:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationUriBaseBoundaryCurrentBaseTest.php`
  => `1 test files, 33 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php`
  => `10 test files, 408 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-link-annotation-uri-base-boundary-currentbase.php`
  => emitted promoted objects `[7,8,9,10]`,
  resolved path/query/fragment hrefs under
  `https://example.com/imports/2026/base.pdf?keep=1`,
  `span_raw_uris=["docs/../guides/import.html?source=pdf#section","?download=1","#fragment-only",null]`,
  `span_resolved_from_base=[true,true,true,false]`,
  `annotation_payload_text_visible=false`,
  `executes_python_or_models=false`, and
  `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This slice does not repeat decoded control-byte URI blocking, primary action
array/scalar boundaries, indirect action subtype resolution, object-stream
action selection, link destination generation selection, rotated/UserUnit
geometry promotion, QuadPoints matching, self-destination suppression, or
remote GoToR review. It owns span-level preservation of catalog URI Base
resolution metadata for safe URI link annotations.

## Dependency Closure

No new support component is needed. The patch reuses native PHP PDF action
review, catalog URI Base parsing, link span promotion, Markdown post-processing,
and the existing WordPress example harness. GPU/model execution, live OCR,
external PDF renderers, network services, and live provider tests remain
intentionally out of scope.
