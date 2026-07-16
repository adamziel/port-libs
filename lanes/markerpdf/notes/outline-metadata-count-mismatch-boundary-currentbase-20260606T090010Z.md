# markerPDF outline metadata count mismatch boundary current-base slice

Session: `port-dev-markerpdf-outline-meta-20260606T090010Z`
Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260606T090010Z`
Base accepted HEAD: `99cf2de666d876a3801263f5952cbba286757315`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF TOC rows through the parser boundary and treats outline/bookmark rows as document navigation metadata, not visible page text. Prior lane notes cite `marker/cleaners/toc.py::get_pdf_toc` for the title/level/page handoff.
- PDF outline root `/Count` is signed outline-tree state metadata. Its magnitude is useful for review, but real-world PDFs can carry stale or inconsistent counts. The native importer should preserve valid linked outline rows and expose count disagreement as payload-free review metadata rather than cap traversal or leak action payloads into document metadata.

## Implementation

- `PdfMetadataExtractor::documentOutlineMetadata()` now compares the absolute root `/Count` value with the actual visible outline item rows after traversal, respecting collapsed ancestors while still reporting the total imported metadata row count.
- When they differ, `document_outline` gains payload-free review fields:
  - `declared_count_mismatch_review_only`
  - `declared_count_mismatch_payload_included`
  - `declared_count_expected_visible_item_count`
  - `declared_count_actual_visible_item_count`
  - `declared_count_actual_item_count`
  - `declared_count_visible_item_count_delta`
- Added `PdfOutlineMetadataCountMismatchBoundaryCurrentBaseTest.php` with a root `/Count 1` and two valid linked outline items. The test proves both rows remain importable through document metadata, TOC, and navigation review while the chained URI action remains outside document metadata and visible WordPress text.
- Added `wordpress-pdf-outline-metadata-count-mismatch-boundary-currentbase.php` to prove the Gutenberg-facing paragraph/navigation output keeps the mismatch as review-only metadata and does not invoke models or external PDF tools.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCountMismatchBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records outline root Count mismatch without dropping valid linked rows (lanes/markerpdf/tests/PdfOutlineMetadataCountMismatchBoundaryCurrentBaseTest.php)
Values are not identical
Expected: true
Actual: NULL
PASS keeps Count mismatch review separate from TOC navigation and visible WordPress text

1 test files, 30 assertions, 1 failures
```

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCountMismatchBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records outline root Count mismatch without dropping valid linked rows
PASS keeps Count mismatch review separate from TOC navigation and visible WordPress text

1 test files, 36 assertions, 0 failures
```

Adjacent outline metadata boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
Focused test run: 40 selected test files (root lock skipped)
40 test files, 1870 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-count-mismatch-boundary-currentbase.php
```

Passed and emitted `declared_count_expected_visible_item_count=1`, `declared_count_actual_visible_item_count=2`, `declared_count_actual_item_count=2`, `declared_count_visible_item_count_delta=1`, `count_mismatch_review_only=true`, `count_mismatch_payload_included=false`, `outline_titles=["Count Mismatch Chapter","Count Mismatch Appendix"]`, `action_review_types=["GoTo","URI"]`, `metadata_excludes_action_payload=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Final local checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineMetadataCountMismatchBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineMetadataCountMismatchBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-count-mismatch-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-metadata-count-mismatch-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf JSON ok\n";'
markerpdf JSON ok

git diff --check -- lanes/markerpdf
passed with no output
```

## Delta

- Focused PHP PASS cases: `+2`.
- Focused assertions: `+36` in the new test file.
- WordPress scenario: `+1`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline metadata color preservation, title encoding, required-title gating, collapsed root `/Count` state, root Count-zero suppression, zero-count child suppression, declared `/Last` traversal, missing/wrong `/Parent` boundaries, `/Prev` mismatch boundaries, EOF/trailer-root outline selection, generation-exact outline references, xref owner boundaries, named-destination action context, PageLabels, page transition/action metadata, outline `/SE` structure metadata, remote GoTo/GoToE review, or rich action-chain review. The bounded behavior is only root-level declared-count mismatch review metadata after valid linked outline rows are imported.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, catalog outline resolver, destination name-tree resolver, metadata extractor, TOC/navigation review paths, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, PDFium execution, model downloads, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
