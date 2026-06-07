# markerpdf xref Prev chain action review indirect Prev current-base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260607T032630Z`

Accepted base: `e317e737ded2631bfb4a3cb4f4e8b3396813a62c`

## Source truth

- Upstream markerPDF delegates PDF object and annotation parsing to native PDF parser behavior before model/OCR stages. Under the current no-GPU markerPDF scope, this port owns the native PHP xref-chain and annotation-action review boundary.
- Existing markerPDF PHP coverage already handled direct xref-stream `/Prev` action-review repair and central text/page-property indirect `/Prev` helpers. This slice targets the remaining local `PdfActionReviewExtractor` chain parser, which still treated `/Prev` as direct-only before repairing current xref-stream rows.

## Behavior

- `PdfActionReviewExtractor` now resolves `/Prev` from direct integers or bounded indirect helper objects before repairing same-generation current xref-stream action rows.
- The indirect helper resolution is fail-closed: object references are resolved only from definitions before the current xref section, cycles are rejected, and resolution is depth-bounded.
- WordPress link promotion now selects current `/A` and `/AA` action dictionaries when the latest xref stream stores `/Prev 30 0 R` and stale previous-section offsets are present in the action rows.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect xref Prev before repairing action review current rows (lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php)
current primary action wins through indirect Prev repair
Expected: 'https://example.com/current-indirect-prev-action'
Actual: 'https://example.com/stale-indirect-prev-action'

1 test files, 6 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect xref Prev before repairing action review current rows

1 test files, 17 assertions, 0 failures
```

Adjacent action-review xref family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS repairs action review xref-stream rows through Prev chain before WordPress link promotion
PASS keeps first current action xref-stream rows before duplicate free rows
PASS selects latest classic xref-table action rows before post-xref decoys
PASS resolves indirect xref Prev before repairing action review current rows

2 test files, 65 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-action-review-indirect-prev-currentbase.php
```

The smoke exits 0 and reports `indirect_prev_helper_used=true`, `current_uri_promoted=true`, `current_additional_action_reviewed=true`, `stale_prev_action_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

- Does not repeat central `PdfTextExtractor` indirect `/Prev` repair, page review indirect `/Prev` metadata, direct action-review `/Prev` repair, duplicate free-row handling, or classic post-xref decoy handling.
- Does not run OCR/model/GPU paths, PDF actions, Python workers, external PDF tools, or live services.

## Dependency closure

No new support component is needed. This reuses the existing native PHP object-definition scan, parsed PDF reference values, xref stream decoding, annotation extraction, and WordPress link-promotion smoke path.
