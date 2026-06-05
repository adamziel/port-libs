# Outline Metadata Prev Boundary Current Base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260604T235749Z`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets PDF TOC/bookmark rows from the PDF outline adapter and treats those rows as navigation metadata, not visible page text.
- PDF outline item dictionaries form sibling lists through `/Next` and `/Prev` backlinks. The native no-GPU boundary for WordPress import should fail closed when an explicit `/Prev` contradicts the current sibling chain, because a same-parent stale action row can otherwise be spliced into document outline metadata and navigation review.

## Implementation

- `PdfMetadataExtractor::document_outline` now stops outline item traversal when a row reached through `/Next` has an explicit `/Prev` that does not point back to the previous accepted sibling.
- `PdfOutlineExtractor` applies the same boundary to upstream-style TOC rows, destination-view TOC rows, composite navigation review rows, outline action review rows, and remote GoTo review rows.
- Missing `/Prev` remains accepted for lightweight or older fixtures; only an explicit contradictory backlink is treated as a stale/corrupt boundary.
- Added a WordPress smoke showing the stale same-parent remote action and untrusted tail outline are excluded from review metadata and visible paragraph text.

## Focused Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataPrevBoundaryCurrentBaseTest.php` failed with 1 test file, 9 assertions, 2 failures. The current base counted all 3 document outline rows and TOC/navigation reached the untrusted tail after the bad `/Prev`.
- After implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataPrevBoundaryCurrentBaseTest.php` passed with 1 test file, 35 assertions, 0 failures.
- Adjacent outline family: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php lanes/markerpdf/tests/PdfMetadata*Outline*Test.php` passed with 31 test files, 2032 assertions, 0 failures.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-prev-boundary-currentbase.php` passed and emitted `imported_item_count=1`, `remote_action_count=0`, `stale_outline_excluded=true`, `stale_remote_action_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass` moves `1147 -> 1149` from the two new focused PASS cases.
- Focused assertion coverage for the new prev-boundary test is 35 assertions.
- WordPress scenario count moves `1137 -> 1138` from the added smoke.

## Non-Overlap

This does not repeat accepted outline metadata color preservation, `/SE` StructElem review metadata, declared `/Last` traversal, missing/wrong `/Parent` boundaries, EOF-bounded outline selection, generation-exact outline references, named-destination action context, remote GoTo review extraction, or action-chain target review. The new behavior is only explicit `/Prev` backlink mismatch handling while walking outline sibling chains.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object parser, outline dictionary traversal, destination resolution, metadata extraction, and WordPress smoke path. Full live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
