# Classic XRef Malformed Subsection Header Boundary

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T113343Z`

Accepted base: `035001e74d7af9d7ff1b2f8346081405093c2a76`

## Behavior

Classic xref rebuild now rejects a later xref table whose second subsection
header carries a private punctuation tail such as `20 12 /PrivateTail`. Before
this patch, the parser accepted the completed `0 1` subsection, ignored the
malformed later header and rows, then allowed trailer graph repair to recover
the decoy `/Root 20 0 R` graph. That could replace current WordPress import
text, XMP/Info metadata, and EmbeddedFiles attachment selection.

The change preserves accepted recovery for completed subsections followed by a
malformed trailing row or a direct object before the real trailer. It only
rejects the malformed subsection-header shape that looks like a new object/count
pair with extra non-comment tail data.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicMalformedSubsectionHeaderBoundaryCurrentBaseTest.php`

Result before the source fix: `1 test files, 3 assertions, 1 failures`; actual
visible text came from `Decoy malformed-subsection page` / `Punctuation header
root leak`.

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicMalformedSubsectionHeaderBoundaryCurrentBaseTest.php`

Result: `1 test files, 30 assertions, 0 failures`.

Regression family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 663 assertions, 0 failures`.

Classic xref current-base family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassic*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewClassicXrefBoundaryCurrentBaseTest.php`

Result: `27 test files, 1506 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-classic-xref-malformed-subsection-header-currentbase.php`

Result: exits `0`, emits current WordPress paragraph blocks, and reports
`malformed_subsection_header_rejected=true`, `current_classic_xref_import_kept=true`,
`decoy_import_excluded=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Lint and whitespace:

`php -l` passed for `PdfTextExtractor.php`, `PdfMetadataExtractor.php`,
`PdfEmbeddedFileExtractor.php`, `PdfAttachmentExtractor.php`,
`PdfXrefFreeObjectMap.php`,
`PdfXrefClassicMalformedSubsectionHeaderBoundaryCurrentBaseTest.php`, and
`wordpress-pdf-classic-xref-malformed-subsection-header-currentbase.php`.

`git diff --check -- lanes/markerpdf` passed with no output.

## Non-Overlap

This does not repeat the accepted malformed row, punctuation-suffixed row,
zero-count subsection, trailing subsection, stream-owned trailer, malformed
startxref, private startxref tail, or post-EOF xref boundary clusters. It covers
the distinct header-level private-tail case after a completed subsection.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP classic
xref table parser and existing text, metadata, embedded-file, attachment, and
free-object-map import surfaces. GPU/OCR/model execution remains intentionally
out of scope under the current markerPDF no-GPU directive.
