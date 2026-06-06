# markerPDF classic xref missing final startxref boundary

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T183433Z`

Upstream markerPDF is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path delegates native object loading to parser-backed text extraction before OCR/model fallback. Under the current no-GPU scope, this PHP lane owns the native parser boundary for searchable PDFs: classic xref repair, catalog/page metadata, text, metadata, and attachment selection before WordPress import.

This slice covers a damaged incremental PDF where an older completed revision still has a valid `startxref`, but the newer current revision appends a valid classic xref table and trailer before `%%EOF` without writing the final `startxref` operand. The parser must not let the older valid pointer hide the current xref table, and it must still reject post-EOF xref/trailer decoys. The fixture also keeps `startxref` text inside current page/filename strings before the current xref table, proving object-owned tokens do not cap the rebuild scan when a later valid table exists.

Implementation:

- `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now allow classic rebuild selection to extend from an older valid `startxref` token to the latest top-level `%%EOF` only when a later valid classic xref table exists and no top-level `startxref`-shaped terminator follows that candidate table.
- The same extractors now make their no-valid-startxref classic rebuild fallback use the latest valid classic table before the computed EOF/ignored-token boundary.
- Object/composite-owned `startxref` text remains a tighter boundary only when no later valid classic table appears before EOF, preserving the existing commented/name/hint/private-tail decoy protections.
- `PdfXrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBaseTest.php` verifies text extraction, text runs, naive text, outline page count, XMP title, Info title/author, EmbeddedFiles extraction, attachment summary, stale prior revision exclusion, and post-EOF decoy exclusion.
- `wordpress-pdf-classic-xref-missing-final-startxref-currentbase.php` is the WordPress smoke.

Focused evidence:

Red-first focused failure after adding the new fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBaseTest.php
...
FAIL bounds rebuild after prior valid startxref when final current startxref is missing
Values are not identical
Expected: 'current-missing-final-startxref.xml'
Actual: 'stale-prior-startxref.xml'
1 test files, 14 assertions, 1 failures
```

Focused green after the repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBaseTest.php
PASS bounds rebuild after prior valid startxref when final current startxref is missing
1 test files, 35 assertions, 0 failures
```

Adjacent regression check:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfXrefClassic.*CurrentBaseTest\.php$' | sort)
18 test files, 1202 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-missing-final-startxref-currentbase.php
```

The smoke emits `current_import_kept=true`, `stale_and_post_eof_decoys_excluded=true`, `embedded_file=current-missing-final-startxref-import.xml`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Non-overlap:

This does not repeat accepted stale-valid `startxref` repair where a later final `startxref` token exists, EOF-bounded post-EOF xref rejection with no prior valid pointer, commented/name/composite/literal/hint/private-tail startxref decoys, malformed active xref rows, completed-subsection preservation, stream-owned trailers, forward `/Prev` repair, xref-stream `/Prev` generation repair, hybrid `/XRefStm` merge policy, object-stream carrier/member repair, or pdftext dictionary worker bounds. The bounded behavior is specifically a newer valid classic xref/trailer before EOF when the final current `startxref` token is missing and an older valid pointer remains earlier in the file.

Dependency closure:

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref table parser, top-level EOF/token boundary scanner, xref-chain walkers, page-tree text extractor, XMP/Info metadata extractor, EmbeddedFiles extractor, attachment summary preflight, and WordPress smoke renderer. GPU/model/OCR, PDFium, pdftext, PIL, Surya/Torch, Texify, Streamlit/FastAPI, and external PDF tools were not run and remain intentionally outside the current no-GPU markerPDF scope.
