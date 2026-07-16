# Classic Xref Rebuild Trailing Subsection Boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T081344Z`

Accepted base: `eabf2addac7c2c5b012c94b74de9b49f75b6cfef`

## Scope

Native no-GPU markerPDF parser behavior only. This slice covers classic PDF xref tables with multiple subsections where a later trailing subsection begins with a malformed row before the current trailer.

PDF classic xref tables are subsection-based. A malformed first row in a later subsection should not discard already completed valid subsections in the same current table; those completed rows are still the current source of truth for `/Root`, `/Info`, `/Metadata`, `/EmbeddedFiles`, page content, and attachment preflight. Malformed rows inside an active subsection remain fail-closed and are still covered by the existing malformed-row test.

## Patch

- `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now preserve completed xref subsection rows when parsing reaches a malformed first row of a later subsection.
- `PdfXrefClassicRebuildBoundaryCurrentBaseTest.php` adds a current-vs-stale fixture where a damaged `startxref` rebuild finds a current table with a valid first subsection followed by a malformed trailing subsection, plus an older valid `/Prev` table with stale text/metadata/files.
- `wordpress-pdf-classic-xref-trailing-subsection-currentbase.php` adds a WordPress-facing native smoke that emits current paragraphs and metadata while proving stale `/Prev` import data is excluded.

## Red-First Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
1 test files, 343 assertions, 0 failures
```

After adding the focused test before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
1 test files, 345 assertions, 1 failures
```

Failure mode: the malformed trailing subsection caused the current table to be discarded, so stale `/Prev` page text, stale XMP/Info metadata, and stale embedded-file/attachment records won.

## Final Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
1 test files, 370 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-trailing-subsection-currentbase.php
exit 0; current_classic_xref_import_kept=true; stale_prev_import_excluded=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-classic-xref-trailing-subsection-currentbase.php
```

All changed PHP files reported `No syntax errors detected`.

```text
git diff --check -- lanes/markerpdf
```

No whitespace errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP classic xref repair and extractor paths. GPU/OCR/model execution, Python worker execution, and external PDF tools remain intentionally excluded under the current markerPDF no-GPU scope.

## Non-Overlap

This does not repeat the accepted malformed active-row rejection, commented `xref`/`startxref` decoy handling, EOF-bounded rebuild, literal/name/composite decoys, stale startxref rebuild, or `/Prev` chain owner/generation slices. It specifically covers the boundary between completed valid subsections and a malformed first row in a later trailing subsection of the same current classic xref table.
