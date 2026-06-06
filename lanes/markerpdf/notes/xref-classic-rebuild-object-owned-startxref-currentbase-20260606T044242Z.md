# markerpdf-classic-xref-rebuild-boundary-current-base-20260606T044242Z

## Scope

Native no-GPU markerPDF classic xref repair boundary. The slice covers damaged searchable PDFs where a later syntactic `startxref` token appears inside a direct object after the current classic xref table. That token is not selectable as a `startxref` offset, but it is a useful repair boundary: the current top-level classic xref table before it should win over an older valid `startxref` section.

## Source Truth

- PDF parser behavior: `startxref` offsets inside object bodies or composite tokens are not top-level trailer pointers.
- Existing lane behavior: classic xref rebuild already rejects comment, string, stream-owned, post-startxref, malformed-header, and stale `/Prev` decoys.
- This patch extends the same boundary rule across text, metadata, embedded-file, and attachment-summary extraction.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildObjectOwnedStartxrefCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses object-owned startxref tokens only as classic rebuild scan boundaries before WordPress imports
Values are not identical
Expected: array (
  0 => 'Current object-owned startxref page',
  1 => 'Ignored object token bounded rebuild',
)
Actual: array (
  0 => 'Stale object-owned startxref page',
  1 => 'Older valid startxref leak',
)

1 test files, 4 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildObjectOwnedStartxrefCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses object-owned startxref tokens only as classic rebuild scan boundaries before WordPress imports

1 test files, 30 assertions, 0 failures
```

Adjacent family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassic*Test.php
Focused test run: 13 selected test files (root lock skipped)
...
13 test files, 1054 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-object-owned-startxref-currentbase.php
```

The smoke emits current WordPress paragraphs, current XMP/Info metadata, current EmbeddedFiles attachment filename, `current_revision_selected=true`, `stale_revision_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

Avoided the accepted AcroForm duplicate Parent/P boundary and existing classic xref rebuild cases for comment delimiters, literal-string xref decoys, stream-owned trailers, post-startxref trailers, malformed subsection headers, overdeclared counts, forward `/Prev`, plus-header sections, signed startxref operands, and zero-count subsections.

## Dependency Closure

No new support component is needed. The existing native PHP PDF tokenizer, direct-object inventory, classic xref table parser, trailer metadata extractor, and EmbeddedFiles/attachment review paths are reused. GPU/OCR/model execution remains intentionally out of scope.

## Next

Continue with non-overlapping native parser work around xref repair, fonts/CMaps, stream filters, page geometry, annotations/forms, metadata, image/filter metadata, or supplied-boundary table/equation handoffs.
