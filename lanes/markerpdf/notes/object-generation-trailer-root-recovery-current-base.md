# markerPDF Object Generation Trailer Root Recovery

## Source Truth

- Upstream markerPDF delegates native PDF catalog/page discovery to the PDF parser stack (`pdftext` / `pypdfium2`) before model and Markdown stages. The PHP lane must therefore recover the active PDF catalog before page text extraction.
- PDF incremental updates make the latest `startxref` trailer or xref-stream dictionary `/Root` the current catalog reference. Older catalog objects may remain live and object-number-ordered earlier, but they must not drive WordPress paragraph extraction when the latest trailer names a different root generation.
- The local upstream clone path recorded in `UPSTREAM_TEST_MANIFEST.json` was not present in this isolated worktree. This slice uses the accepted lane manifest, prior xref/object-stream notes, and PDF parser semantics as source truth.

## Implementation

- `PdfTextExtractor` now resolves the latest `startxref` table or xref-stream chain for `/Root`, including `/Prev` fallback and hybrid `/XRefStm` lookup when needed.
- After live direct-object and object-stream recovery, the trailer-selected root catalog object is promoted before the fallback catalog scan. This preserves existing fallback behavior while preventing stale catalog objects from winning only because their object number is lower.
- Added a focused incremental PDF fixture where object `1 0` remains a stale catalog, the latest trailer points to `10 1 R`, and WordPress extraction must emit only the current generation catalog page.

## Verification

- Red-first check before the patch with an in-stdin PHP fixture emitted `Stale catalog page`, confirming the old catalog-order bug.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`: passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php`: passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-trailer-root-generation-import.php`: passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`: passed, `1 test files, 380 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-trailer-root-generation-import.php`: passed; smoke reported `uses_latest_trailer_root_catalog=true` and `excludes_stale_catalog_page=true`, then emitted `Recovered trailer root page` and `Generation one catalog` paragraph blocks.
- `php tools/run-tests.php lanes/markerpdf/tests`: passed, `58 test files, 2377 assertions, 0 failures`.

## Non-Overlap

This does not repeat the accepted object-generation free-entry reuse guard, latest `startxref` object-stream rebuild precedence, xref-stream `/Index` / zero-width `/W` handling, linearized hint-table exclusion, object-stream indirect `/Length` recovery, stream-filter error boundaries, or StructTreeRoot RoleMap slices. The new behavior is specifically trailer `/Root` catalog recovery across incremental object generations.

## Dependency Closure

No new support component is needed. This reuses the native PHP xref/trailer parser already in `PdfTextExtractor`; full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, `pypdfium2`, Surya/Torch, tabled, Texify, and live benchmark/runtime tooling.
