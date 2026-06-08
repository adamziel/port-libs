# markerpdf xref Prev-chain action-review forward Prev current-base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T100133Z`

Base accepted HEAD: `0556f6c8205c8f235f6b3f8f8751917296dbd9c3`

## Source truth

- PDF incremental updates use `/Prev` to point from the current xref section to the previous xref section. A forward or non-section `/Prev` cannot be followed safely.
- The accepted native xref parser already repairs invalid previous offsets by selecting the latest real xref table/stream before the current update. This slice applies that same boundary to the action-review xref chain before link annotation action promotion.
- Scope stayed inside native searchable-PDF parsing and action metadata review. No PDF actions, JavaScript, OCR, model/GPU code, raster rendering, or external PDF tools ran.

## Behavior implemented

- `PdfActionReviewExtractor` now normalizes a resolved xref stream/table `/Prev` before current-update row repair:
  - valid earlier xref sections are preserved;
  - forward `/Prev` offsets fall back to the latest real earlier xref section;
  - invalid earlier offsets fall back the same way;
  - absent or negative `/Prev` remains absent.
- This lets stale current xref stream rows for action objects be repaired against the current update window before inherited previous rows can promote stale URI/JavaScript action data into WordPress links.

## Focused evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewForwardPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs forward Prev action review xref-stream rows before WordPress link promotion
Values are not identical
Expected: 'https://example.com/current-forward-prev-action'
Actual: 'https://example.com/stale-forward-prev-action'

1 test files, 3 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewForwardPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs forward Prev action review xref-stream rows before WordPress link promotion

1 test files, 15 assertions, 0 failures
```

Adjacent xref Prev/action-review family:

```text
php tools/run-tests.php \
  lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfXrefPrevChainActionReviewForwardPrevCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfXrefPrevChainCompressedActionRowsCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php

5 test files, 732 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-action-review-forward-prev-currentbase.php
```

The smoke exits `0` with:

- `forward_prev_repaired=true`
- `current_uri_promoted=true`
- `current_additional_action_reviewed=true`
- `stale_prev_action_excluded=true`
- `executes_pdf_actions=false`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Syntax and status validation:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainActionReviewForwardPrevCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-action-review-forward-prev-currentbase.php
php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo $data["phpPass"] . " pass / " . $data["phpFail"] . " fail\n";'
```

All three PHP files lint cleanly; lane status validates as `3031 pass / 0 fail`.

## Status delta

- Focused PASS cases: `3030 -> 3031`
- WordPress scenarios: `2506 -> 2507`
- Added behavior test assertions: `15` in the new focused file.
- Preserved adjacent family: `5 test files / 732 assertions / 0 failures`.

## Dependency closure

No new support component is needed. This reuses the lane's native PDF tokenizer, object-definition inventory, xref table parser, xref stream parser, and action-review link promotion path. The remaining gap is broader native xref/action parity in other extractor copies, not an external dependency.

## Non-overlap and next task

This slice does not duplicate the accepted xref Prev-chain incremental update tests for metadata, attachments, page review, page properties, compressed action rows, indirect `/Prev`, or CMap source-width fallback. It is limited to action-review promotion when the current xref stream's `/Prev` is forward or invalid.

Next useful markerPDF work: continue non-overlapping native xref repair or object-stream/filter metadata behavior in other extractor surfaces, keeping model/OCR work out of scope unless explicitly authorized.
