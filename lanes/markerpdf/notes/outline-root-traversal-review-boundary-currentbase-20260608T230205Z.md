# markerPDF outline root traversal review boundary current-base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260608T230205Z`
Lane: `markerpdf`
Base accepted HEAD: `d8ca989a03aa98e6028adc24e3edc39bb34ec9a6`

## Source truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF TOC extraction to the PDF engine through `get_pdf_toc`, returning title, level, and page metadata separately from extracted page text:
`https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py`.

Under the no-GPU markerPDF scope, the PHP port owns the native PDF outline metadata boundary. Malformed outline-root traversal operands must not produce TOC rows or visible WordPress text, but reviewers still need a payload-free reason why outline navigation was suppressed.

## Implemented behavior

- `PdfMetadataExtractor` now summarizes malformed outline-root `/First`, `/Last`, and `/Count` traversal operands when the selected value has extra top-level operands before the next dictionary key.
- The summary records only review metadata: root key names, rejection statuses, selected/trailing reference object numbers, operand shapes, and promotion flags. Hidden outline titles, action URIs, and decoy payload strings are not copied.
- `PdfOutlineExtractor::getNavigationReviewMetadata()` now carries that already-redacted root traversal review into `outline_root_review` even when there is no root `/Metadata` stream review.
- The WordPress smoke demonstrates visible paragraph extraction with no promoted outline/action rows and a review-only navigation item.

## Red-first evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalReviewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records malformed outline root traversal operands as review metadata
Expected: 2
Actual: NULL
FAIL carries malformed root traversal review into navigation without visible text leakage
Expected navigation to include outline_root_review

1 test files, 11 assertions, 2 failures
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalReviewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 62 assertions, 0 failures
```

Adjacent root outline boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootTraversalOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootSummaryBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 275 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-root-traversal-review-currentbase.php
exits 0 with boundary_count=2, boundary_keys=[First,Count], trailing_references=[9,11], navigation_carries_root_review=true, outline_rows_promoted=0, action_rows_promoted=0, visible_text_excludes_outline_metadata=true, executes_python_or_models=false, and executes_external_pdf_tools=false
```

Syntax/status checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalReviewBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-root-traversal-review-currentbase.php
php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
all passed
```

Status delta: `phpPass` moves `3569 -> 3571`; `wordpressScenarios` moves `2882 -> 2883`.

## Non-overlap

This does not repeat accepted outline `/Metadata`, `/SE`, destination/action operand, typed item, color/style, root traversal suppression, direct-root traversal operand, duplicate traversal key, no-page root review, page label, annotation, encrypted permission, OCR, model, table, equation, or image behavior. The bounded behavior is only payload-free document/navigation review for malformed outline-root traversal operands that already suppress TOC rows.

## Dependency closure

No new support component is needed. The patch reuses the native PDF object and dictionary scanners, top-level operand boundary reader, document outline metadata extractor, navigation review handoff, text extractor, and WordPress smoke harness. It does not invoke Python, CUDA, OCR, Surya/Texify/Torch models, PDF rendering, action execution, or external PDF tools.

Root harness not run - isolated micro-slice.
