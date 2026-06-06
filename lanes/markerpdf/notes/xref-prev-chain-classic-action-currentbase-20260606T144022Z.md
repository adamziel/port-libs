# markerpdf xref prev-chain classic action current-base

- Session: `port-dev-markerpdf-xref-prev-chain-20260606T144022Z`
- Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T144022Z`
- Base accepted HEAD: `c225160401688bd1c3ca993be227a17e71dcecc4`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to lower-level PDF
parsers before conversion. In this no-GPU lane, the native parser boundary is
PDF object/xref selection and action review metadata, not OCR/model execution.

This slice covers a bounded PDF incremental-update behavior: a latest classic
xref table with `/Prev` must select the current object rows for annotation
actions even when same-number stale action objects are appended after the xref
table. The stale post-xref objects are valid raw `obj` bodies, but they are not
the current revision rows and must not drive URI or additional-action review.
Action rows remain review-only; JavaScript is never executed.

## Red-first gap

Before the source change, `PdfActionReviewExtractor` selected xref-stream rows
but fell back to raw last-object order for classic xref-table updates. A
red-first probe using current action objects `8 0` and `9 0`, with stale
same-number decoys appended after the latest classic xref table, reported:

- stale URI selected: `https://example.com/post-xref-stale-action-decoy`
- current additional-action URI missing:
  `mailto:current-classic-action@example.test`

## Implementation

`PdfActionReviewExtractor` now selects object definitions from the latest xref
section, not only from xref streams. The selector understands both xref streams
and classic xref tables, follows `/Prev`, repairs current update rows through
the existing current-xref repair path, merges older rows without overriding
newer in-use entries, and only falls back to raw object order when no usable
xref section can be selected.

The focused regression fixture proves that annotation extraction, link
application, and Markdown post-processing all keep the current action URI and
additional-action URI while excluding stale previous-revision and post-xref
decoy actions.

## Verification

- `php -l lanes/markerpdf/src/PdfActionReviewExtractor.php`
  - `No syntax errors detected in lanes/markerpdf/src/PdfActionReviewExtractor.php`
- `php -l lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-classic-action-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-classic-action-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php`
  - `1 selected test files, 48 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDamagedPrevCurrentBaseTest.php`
  - `4 selected test files, 593 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationFreedActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataActionChainBoundaryCurrentBaseTest.php`
  - `4 selected test files, 452 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-classic-action-currentbase.php`
  - emits `markerpdf-xref-prev-chain-classic-action-currentbase`
  - emits `post_xref_decoy_excluded=true`
  - emits `executes_python_or_models=false`
  - emits `executes_external_pdf_tools=false`

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat searchable text extraction, metadata-only xref repair,
freed annotation selection, xref-stream action review, classic metadata
updates, attachment/image extraction, OCR, Surya/Texify/Torch, or external PDF
tool behavior. The changed behavior is limited to native object selection for
action-review/link handling when the current incremental update is a classic
xref table with `/Prev`.

## Dependency closure

No new support component is needed. The slice reuses the native markerPDF PDF
parser helpers, annotation/link extractors, action review extractor, and
Markdown post-processor. No GPU/model runtime, Python service, live provider,
or external PDF tool is required.
