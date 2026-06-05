# markerPDF classic xref forward Prev boundary

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T084905Z`

Upstream markerPDF is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path delegates object loading and text extraction to parser-backed `pdftext`/PDFium before OCR/model fallback. Under the current no-GPU scope, this PHP lane owns the native parser boundary for classic xref rebuild, `/Prev` traversal, text, metadata, and attachment selection before WordPress import.

PDF incremental update `/Prev` links are backward pointers. The new fixture covers a damaged classic xref rebuild where the selected current xref table has a `/Prev` value that points forward to post-EOF decoy xref data. The current catalog is valid, and its unchanged page tree, XMP/Info metadata, and EmbeddedFiles name tree live in the prior xref section. The native parser must repair the forward `/Prev` to the latest earlier xref section instead of following the post-EOF decoy.

Implementation:

- `PdfTextExtractor::previousXrefOffsetForSectionBody()` now treats `/Prev >= current xref offset` as damaged and repairs to the latest valid earlier xref section.
- The same guard is applied to `PdfMetadataExtractor` and `PdfEmbeddedFileExtractor`, which keep duplicated xref-chain walkers for metadata and attachment review paths.
- `PdfXrefClassicRebuildBoundaryCurrentBaseTest.php` adds the forward-`/Prev` fixture and verifies WordPress paragraphs, XMP title, Info title/author, EmbeddedFiles extraction, and attachment summary select the prior valid dependency section while excluding post-EOF decoy text/metadata/files.
- `wordpress-pdf-classic-xref-forward-prev-currentbase.php` is the WordPress smoke.

Focused evidence:

Initial focused failure while adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
...
FAIL repairs forward classic Prev pointers to the prior xref section before WordPress imports
Values are not identical
Expected: 'current-forward-prev-xref.xml'
Actual: 'decoy-forward-prev-xref.xml'
1 test files, 383 assertions, 1 failures
```

Focused green after the repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
...
PASS repairs forward classic Prev pointers to the prior xref section before WordPress imports
1 test files, 400 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-forward-prev-currentbase.php
```

The smoke emits `forward_prev_repaired_to_prior_section=true`, `current_import_kept=true`, `post_eof_prev_decoy_excluded=true`, `embedded_file=current-forward-prev-import.xml`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Non-overlap:

This does not repeat accepted damaged numeric `startxref` rebuild, stale-valid `startxref` repair to later current table, EOF-bounded post-EOF xref rejection without `/Prev`, commented/composite/name/string xref/startxref decoys, malformed active xref rows, completed-subsection preservation, stream-owned trailers, xref-stream `/Prev` generation repair, hybrid `/XRefStm` merge policy, or object-stream carrier/member repair. The bounded behavior is specifically forward classic `/Prev` offsets during current classic xref rebuild.

Dependency closure:

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref table parser, `/Prev` chain walker, page-tree text extractor, XMP/Info metadata extractor, EmbeddedFiles extractor, and WordPress smoke renderer. GPU/model/OCR, PDFium, pdftext, PIL, Surya/Torch, Texify, Streamlit/FastAPI, and external PDF tools were not run and remain intentionally outside the current no-GPU markerPDF scope.
