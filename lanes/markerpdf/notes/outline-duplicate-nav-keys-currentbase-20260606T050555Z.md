# markerPDF Outline Duplicate Navigation-Key Metadata Boundary

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260606T050555Z`

Accepted base: `919f537f75b29e4e26191e7da80180455a74c185`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives searchable PDF outline/navigation metadata from PDF parser dependencies before model/OCR handoff. Under the current no-GPU markerPDF scope, this slice maps a native PDF dictionary boundary for catalog `/Outlines`: duplicate top-level outline item `/Title`, `/Dest`, and `/A` entries are review metadata, while selected WordPress TOC/navigation rows continue to use the last top-level operand.

## Behavior

- `PdfMetadataExtractor` now adds per-item `duplicate_key_review` for duplicate `/Title`, `/Dest`, and `/A` operands.
- The review row records only key names, counts, selected entry indexes, and selection policy.
- Stale duplicate titles, destinations, nested dictionary decoys, and unselected action operands are not exposed as payload text, document metadata roots, or visible WordPress paragraphs.
- Document outline metadata now summarizes `duplicate_item_key_count`, `duplicate_item_keys`, and review-only/payload-exclusion flags.

## Red-First Evidence

Before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateNavKeyBoundaryCurrentBaseTest.php`

Result: `1 test files / 22 assertions / 1 failures`.

Failing assertion: expected `duplicate_item_key_count` to be `2`; actual `null`.

## Verification

Focused test after source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateNavKeyBoundaryCurrentBaseTest.php`

Result: `1 test files / 57 assertions / 0 failures`.

Affected outline metadata family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutlineMetadata.*Test\.php$' | sort) lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `40 test files / 2415 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-duplicate-nav-keys-currentbase.php`

Result: emits `duplicate_item_key_count=2`, `duplicate_item_keys=["Title","Dest","A"]`, `selected_action_object=13`, `stale_duplicate_title_excluded=true`, `stale_duplicate_dest_excluded=true`, `stale_duplicate_action_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline `/Metadata` duplicate-key stream review, outline color/style review, titleless-item traversal boundaries, named-destination `/Limits` ordering, outline action-chain target context, OpenAction review, page transition/thread enrichment, xref owner repair, encrypted security preflight, page-label extraction, annotation/form review, image/filter metadata, table geometry, or OCR/model behavior. The bounded behavior is only duplicate top-level outline navigation-key provenance for `/Title`, `/Dest`, and `/A`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level dictionary tokenizer, outline metadata extractor, destination resolver, action-chain reviewer, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope for the current no-GPU markerPDF lane.
