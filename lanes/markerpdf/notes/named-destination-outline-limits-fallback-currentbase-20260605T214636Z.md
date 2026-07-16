# markerPDF named-destination outline Limits fallback current-base slice

## Scope

Lane: `markerpdf`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T214636Z`
Accepted base: `90854239e2675032c2ad9d4f94cc8a69f5df5884`

This slice stays inside the native no-GPU PDF parser scope. It does not run OCR,
Surya, Texify, Torch, PDFium/PIL raster rendering, JavaScript, Python
markerPDF, or external PDF tools.

Upstream markerPDF delegates PDF outline and destination resolution to
pdftext/PDFium at the searchable-PDF boundary. The native PHP port already
mapped malformed child `/Limits` fallback for standalone named-destination
document metadata. The remaining boundary was the outline/navigation resolver:
the same catalog `/Names /Dests` tree could appear in
`document_destinations`, but outlines that referenced those names disappeared
because `PdfOutlineExtractor` treated every active child limit as mandatory.

## Behavior

`PdfOutlineExtractor` now intersects each child name-tree `/Limits` range with
the inherited range before collecting destination rows. If the child range is
inconsistent with inherited bounds, it falls back to the inherited range. This
recovers current outline TOC/navigation rows for malformed child ranges while
preserving the accepted rootless limit behavior where an out-of-range child
continues to prune stale action rows.

The focused fixture contains:

- root destination limits `(Current Start)` through `(Review Summary)`;
- a child with malformed `/Limits [(zz-stale) (zz-stale)]`;
- valid current destination rows `(Current Start)` and `(Review Summary)`;
- a stale decoy `(zz-stale)` destination and outline row.

Expected WordPress behavior: current document destination metadata and outline
navigation rows agree, while stale decoy destinations and outline operands stay
out of Gutenberg paragraph text.

## Red First

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationOutlineLimitsFallbackCurrentBaseTest.php`

Result:

`1 test files, 13 assertions, 1 failures`

Failure: outline titles were empty for the current named destinations even
though `PdfNamedDestinationExtractor` and `PdfMetadataExtractor` recovered the
document destination names.

## Verification

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationOutlineLimitsFallbackCurrentBaseTest.php`

Result:

`1 test files, 21 assertions, 0 failures`

Accepted limit regression guard plus new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNameTreeLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationOutlineLimitsFallbackCurrentBaseTest.php`

Result:

`2 test files, 45 assertions, 0 failures`

Named-destination family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination|PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest|PdfLinkAnnotationExtractorTest|PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest).*Test\.php$' | sort)`

Result:

`31 test files, 863 assertions, 0 failures`

Outline family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutline.*Test\.php$' | sort)`

Result:

`59 test files, 2967 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-outline-limits-fallback-currentbase.php`

Result: emitted `stale_decoy_omitted=true`, recovered `Current Start Outline`
and `Review Summary Outline`, and kept stale decoy operands out of visible text.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted standalone named-destination `/Limits` pruning,
malformed child `/Limits` document metadata fallback, byte-string comparison,
PDF-name key rejection, sparse name arrays, duplicate-key precedence, alias
chains, page-only destinations, indirect page operands, action dictionaries,
view-mode/coordinate validation, trailer-root selection, xref/object-stream
repair, link annotation name-tree limits, outline action-chain target context,
or generic outline traversal boundaries.

The bounded behavior is only carrying inherited malformed-child name-tree
destination limits into `PdfOutlineExtractor` destination and action maps before
TOC/navigation review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
parser, outline resolver, catalog destination name-tree walker, page-tree
indexer, metadata extractor, visible text extractor, and lane-local WordPress
smoke renderer. Full upstream model/runtime parity remains intentionally out of
scope under the current markerPDF no-GPU directive.
