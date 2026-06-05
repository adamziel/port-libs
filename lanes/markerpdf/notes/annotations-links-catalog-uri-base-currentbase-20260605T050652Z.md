# markerPDF Link Annotation Catalog URI Base Boundary Current Base

Session: `port-dev-markerpdf-annotations-links-20260605T050652Z`
Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T050652Z`
Base accepted HEAD: `bd28920b7f3ed02f501965b633a3e53666fd2f67`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native searchable-PDF text through PDF parser/PDFium-style page text boundaries and does not execute annotation actions during import. PDF URI actions can use the catalog `/URI << /Base ... >>` dictionary to resolve relative URI action targets. This slice keeps that upstream no-execution boundary while making relative link targets usable for WordPress import review.

## Implemented Behavior

- `PdfActionReviewExtractor` now reads trusted catalog `/URI /Base` values for `http`, `https`, and `ftp` bases.
- Relative `/S /URI` action targets on page Link annotations resolve against that base before `PdfLinkAnnotationExtractor` promotes safe links to supplied pdftext spans.
- URI action rows carry review metadata: `raw_uri`, `uri_base`, `uri_relative`, and `uri_resolved_from_base`.
- Absolute URI actions remain unchanged, and unsafe URI schemes such as `javascript:` remain blocked and unpromoted.
- The native path does not execute PDF actions, JavaScript, Python models, OCR, or external PDF tools.

## Evidence

Red-first focused run after adding the regression and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves catalog URI Base for relative Link annotations before WordPress span promotion
Expected: ['review-uri', 'review-uri', 'blocked-unsafe-uri', 'review-uri']
Actual: ['blocked-unsafe-uri', 'review-uri', 'blocked-unsafe-uri', 'review-uri']
1 test files, 3 assertions, 1 failures
```

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves catalog URI Base for relative Link annotations before WordPress span promotion
1 test files, 41 assertions, 0 failures
```

Adjacent annotation/link family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(LinkAnnotation|AnnotationExtractor|MarkupAnnotationExtractor|PageAnnotation.*Action|PageParentTreeActionAnnotation|PageWidgetFieldActionLink|PageWidgetLink|PageAnnots.*Link|AnnotationLinkGeneration)' | sort)
Focused test run: 19 selected test files (root lock skipped)
19 test files, 1064 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-catalog-uri-base-currentbase.php
```

The smoke emitted `promoted_link_objects=[7,8,10]`, `relative_raw_uri=articles/plugin-guide.pdf?from=pdf#setup`, `relative_uri_base=https://docs.example.com/import/current/guide.pdf`, `relative_resolved_href=https://docs.example.com/import/current/articles/plugin-guide.pdf?from=pdf#setup`, `fragment_resolved_href=https://docs.example.com/import/current/guide.pdf#field-reference`, `unsafe_uri_promoted=false`, `visible_text_excludes_link_metadata=true`, and all execution flags false.

## Status Delta

- Adds 1 focused PHP PASS case under the native annotation/link denominator.
- Adds 1 WordPress smoke/example for catalog URI base link promotion.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted link URI control-byte blocking, escaped Link dictionary parsing, primary-action gating, GoToR review metadata, QuadPoints/CropBox geometry, presentation metadata, generation exactness, widget link promotion, outline action-chain metadata, or page annotation StructTree/action context. The new behavior is specifically catalog `/URI /Base` resolution for relative `/S /URI` Link action targets before safe WordPress span promotion.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object parser, catalog dictionary resolution, existing annotation action reviewer, link annotation extractor, supplied pdftext span merger, and Markdown postprocessor. GPU/model/OCR execution, Surya/Texify/Torch, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
