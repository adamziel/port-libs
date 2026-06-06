# MarkerPDF Page Resource Object-Stream Inheritance Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260606T153250Z`

Accepted base: `23f74c35222a9d365dc34bb6ddefcff9734650d4`

## Behavior

Native page boundary metadata now materializes xref-stream type-2 entries whose selected object stream contains a page-tree `/Resources` dictionary. This keeps searchable-PDF imports aligned when a `/Pages` node inherits `/Resources 10 0 R` and object `10 0` is compressed in an `/ObjStm` carrier selected by the current xref stream.

The expansion is bounded:

- only current xref-selected type-2 entries are materialized;
- compressed members are generation zero;
- duplicate object numbers, duplicate member offsets, index mismatches, empty members, and stream-object members fail closed;
- unselected object-stream decoys remain invisible to page metadata and WordPress text output.

This ports the relevant native PDF parser contract, not OCR/model behavior.

## Evidence

Focused runs:

- First focused run of the new test during implementation failed on metadata key expectation: `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceObjectStreamInheritanceCurrentBaseTest.php` -> `1 test files, 4 assertions, 1 failures`.
- Focused after parser implementation and schema alignment: `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceObjectStreamInheritanceCurrentBaseTest.php` -> `1 test files, 14 assertions, 0 failures`.

Regression coverage:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfPageResourceObjectStreamInheritanceCurrentBaseTest.php` -> `5 test files, 308 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php` -> `26 test files, 691 assertions, 0 failures`.

WordPress smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-page-resource-object-stream-inheritance-currentbase.php` verifies compressed inherited resource selection, page-tree owner reporting, Font/Form XObject visibility, unselected object-stream decoy exclusion, and no Python/models/external PDF tools.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP xref stream, Flate stream decoding, PDF dictionary/value parsing, and page boundary metadata paths. GPU/model/OCR execution remains intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue with non-overlapping native searchable-PDF behavior: page geometry, font/CMap widths, xref repair, annotations/forms, image/filter metadata, and supplied-boundary table/equation handoffs. Avoid repeating page resource dictionary inheritance without a distinct parser boundary.
