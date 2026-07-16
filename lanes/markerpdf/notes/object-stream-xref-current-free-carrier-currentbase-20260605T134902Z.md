# markerpdf object-stream xref current free-carrier repair

Slice: `markerpdf-object-stream-xref-parser-current-base-20260605T134902Z`

Base accepted HEAD: `ef6e7af990f8283c93255ef23b84fc3629ee4681`

## Behavior

PDF 1.5 xref streams can select ordinary objects from `/ObjStm` carriers with
type-2 rows. This slice covers a damaged current xref stream where valid type-2
rows point at a current direct `/ObjStm`, but the same current section marks
the carrier row free. The native parser now treats that carrier row as damaged
only when a direct `/ObjStm` exists in the current revision window, so current
compressed page text is selected before WordPress paragraph rendering.

This does not change stale `/Prev` member suppression: previous compressed rows
still require the same selected carrier storage, and current free member rows
still suppress stale compressed page objects.

## Evidence

Red-first focused run before the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentFreeCarrierRepairCurrentBaseTest.php`

Result: `1 test files / 1 assertions / 1 failures`; only the direct guard page
was extracted.

Focused run after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentFreeCarrierRepairCurrentBaseTest.php`

Result: `1 test files / 21 assertions / 0 failures`.

Object-stream/xref family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfObjectStream*.php lanes/markerpdf/tests/PdfParserObjectStream*.php lanes/markerpdf/tests/PdfParserXrefObjectStream*.php lanes/markerpdf/tests/PdfXrefObjectStream*.php lanes/markerpdf/tests/PdfXrefHybridObjectStream*.php lanes/markerpdf/tests/PdfXrefLinearizedObjectStream*.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php`

Result: `47 test files / 856 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-current-free-carrier-currentbase.php`

Result: emits `compressed_page_selected=true`, `direct_page_selected=true`,
`free_carrier_row_repaired=true`, `member_dictionary_hidden=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

`php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfXrefObjectStreamCurrentFreeCarrierRepairCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-current-free-carrier-currentbase.php`

Result: no syntax errors detected in all changed PHP files.

Whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed with no output.

## Dependency Closure

No new dependency or support component is needed. The slice reuses the existing
native PHP xref-stream decoder, object-stream expander, FlateDecode support,
and WordPress paragraph smoke path. GPU/model/OCR execution remains out of
scope under the active markerPDF no-GPU directive.

## Non-overlap

This is distinct from the accepted object-stream carrier-omission, previous
free-carrier, current free member-row, hybrid free-entry, stream-member,
duplicate-offset, and `/First` boundary slices. The new case is specifically a
current-section free carrier row that conflicts with current-section valid
type-2 rows and a current direct `/ObjStm` body.
