# markerPDF classic xref private-tail missing-prior startxref current-base

Session: `port-dev-markerpdf-xref-classic-rebuild-20260608T205214Z`
Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T205214Z`
Base accepted HEAD: `c62efec169d36d2f15524503520d693771159cf3`

## Behavior

Classic xref rebuild must not let a later malformed top-level `startxref`
section replace the current import state when the current xref table has no
valid `startxref` footer.

This slice covers a current classic xref table terminated by `%%EOF`, followed
by a later xref table whose top-level `startxref` numeric operand has a private
tail, then another later xref before final EOF. The rebuild boundary now uses
the latest classic xref table before that malformed `startxref` token, so the
native parser preserves the current page text, XMP/Info metadata, EmbeddedFiles
payload, and attachment summary while excluding private-tail and later decoys.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPrivateTailMissingPriorStartxrefCurrentBaseTest.php`

Before the fix the fixture imported `Later missing-prior decoy page` and
`Unbounded final EOF leak`.

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPrivateTailMissingPriorStartxrefCurrentBaseTest.php`

Result: `1 test files, 34 assertions, 0 failures`.

Adjacent classic-xref regression run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPrivateTailMissingPriorStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildPrivateTailEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildObjectOwnedStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `7 test files, 879 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-private-tail-missing-prior-currentbase.php`

Result: exits `0`; reports `uses_current_page_text=true`,
`metadata_title_current=true`, `info_title_current=true`,
`embedded_file_current=true`, `attachment_summary_current=true`,
`excludes_private_tail_xref=true`, `excludes_later_xref=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted classic xref damaged-startxref repair, stale
valid startxref selection, EOF/post-EOF bounds, commented startxref/xref
handling, object-owned/composite/name/literal/stream-owned startxref decoys,
private-tail handling with an earlier valid startxref, or malformed no-digit
startxref boundaries. The new case is specifically a malformed private-tail
`startxref` token after a current classic xref table that has no valid prior
`startxref` footer.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, classic xref table locator, xref rebuild boundary logic, text
extractor, metadata extractor, embedded-file extractor, attachment summary,
and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution,
PDF rendering, JavaScript/action execution, external PDF tools, and live
provider services remain intentionally out of scope for this no-GPU markerPDF
slice.

## Next Task

Continue with a non-overlapping native searchable-PDF parser boundary, such as
annotation/form xref repair, CMap/font encoding edges, image/filter metadata,
or supplied-boundary table/equation handoff behavior.
