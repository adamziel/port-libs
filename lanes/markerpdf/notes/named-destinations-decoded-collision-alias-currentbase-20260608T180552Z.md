# named-destinations decoded-collision alias current-base slice

Session: `port-dev-markerpdf-named-destinations-20260608T180552Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T180552Z`
Base accepted HEAD: `1d10c26783e331f072073a9dc0eef297e722aedb`

## Behavior

PDF name trees can contain two string keys that decode to the same Unicode
label while retaining different source bytes. This slice covers a `/Names
/Dests` tree with both literal ASCII `(Collision)` and UTF-16BE
`<FEFF0043...>` keys, plus two aliases:

- `(Alias ASCII)` points to the literal ASCII key and must resolve to page 1.
- `(Alias UTF16)` points to the UTF-16BE key and must resolve to page 2.

The native named-destination and metadata extractors now seed name-tree lookup
by raw PDF string bytes for decoded collisions and withhold the ambiguous
decoded-only key. Alias chains still display decoded names for WordPress review
metadata, but traversal uses raw lookup keys when an alias operand is a PDF
string.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionAliasBoundaryCurrentBaseTest.php`

Failed before the fix with the ASCII alias resolving through the decoded label
to the UTF-16 collision target:

`Actual destination pages: [1, 2, 2, 2]`

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 43 assertions, 0 failures`.

Adjacent named-destination, metadata, outline, annotation, and link family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameTreeKeyActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php`

Result: `10 test files, 1782 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-decoded-collision-alias-currentbase.php`

Result: exits `0`; reports `destination_pages=[1,2,1,2]`,
`metadata_destination_pages=[1,2,1,2]`, `toc_pages=[1,2]`,
`span_destination_pages=[1,2]`,
`visible_text_excludes_destination_metadata=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat decoded-collision direct action coverage, direct
decoded-collision destination extraction, ordinary name-tree alias resolution,
or GoTo action alias-cycle rejection. The new boundary is ambiguous alias
resolution where two raw PDF string keys have the same decoded label and alias
operands must resolve by source bytes.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
name-tree parser, PDF string decoder, named-destination extractor, metadata
review extraction, outline extraction, annotation extraction, link application,
and Markdown post-processing paths. No Python, OCR/model, GPU/Torch, external
PDF tools, PDF action execution, or live provider services are involved.

## Next Task

A useful follow-up is a distinct native searchable-PDF boundary in outlines,
annotations, forms, page geometry, image/filter metadata, or xref repair that
does not revisit decoded-collision alias lookup.
