# markerpdf annotations links catalog URI Base operand boundary current base

Slice: `markerpdf-annotations-links-boundary-current-base-20260608T090250Z`

Accepted base: `ca61108bdd827e1c12cda271a01da7d8c060a0f3`

## Source truth

PDF link actions can resolve relative `/URI` values against the catalog `/URI`
dictionary `/Base` entry, but malformed PDF dictionary value boundaries must
not donate trusted operands. This slice keeps normal catalog URI Base behavior
from the existing current-base coverage, while rejecting a catalog `/URI`
dictionary whose `/Base` value is followed by a stray indirect object operand.

No upstream GPU/model/OCR behavior is involved. This is native searchable-PDF
parser/converter behavior around catalog metadata and link annotation
promotion.

## Implementation

- `PdfActionReviewExtractor::catalogUriBase()` now resolves the catalog `/URI`
  dictionary once, inspects parser-produced malformed value operand metadata,
  and returns no base when the `/Base` key is malformed.
- Relative and fragment Link annotation URI actions remain safe review links,
  but they stay raw-relative and are not marked as resolved from the tainted
  base. Absolute safe links still promote normally; unsafe JavaScript URI links
  remain blocked.

## Red-first evidence

Before the extractor change, the focused test failed because the relative link
was resolved through the malformed catalog base:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed catalog URI Base operands before resolving Link annotation hrefs (lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseOperandBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'articles/import.html#setup'
Actual: 'https://docs.example.com/import/current/articles/import.html#setup'

1 test files, 4 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed catalog URI Base operands before resolving Link annotation hrefs

1 test files, 40 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php
6 test files, 529 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-link-catalog-uri-base-operand-boundary-currentbase.php
exits 0 with relative_href=articles/import.html#setup, relative_resolved_from_base=false, fragment_href=#field-reference, tainted_catalog_base_promoted=false, unsafe_uri_promoted=false, executes_python_or_models=false, executes_external_pdf_tools=false.
```

## Non-overlap

This does not duplicate the accepted catalog URI Base slice that resolves valid
relative Link annotation URIs, the URI-control-byte boundary, or action
dictionary malformed `/URI` operands. The new behavior is specifically the
catalog `/URI` dictionary `/Base` malformed-value operand boundary.

## Dependency closure

No new support component is needed. The slice reuses native PHP PDF object
parsing and existing malformed dictionary operand review metadata. No Python,
CUDA, OCR, model execution, raster rendering, action execution, decryption, or
external PDF tools are required.

## Next task

Continue with non-overlapping native PDF parser/converter behavior around link
annotations, forms, outlines, page geometry, stream filters, CMaps, metadata,
xref repair, or supplied-boundary table/equation handoffs under the current
no-GPU markerPDF scope.
