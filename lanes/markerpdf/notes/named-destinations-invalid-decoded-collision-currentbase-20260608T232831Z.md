# named-destinations invalid decoded-collision current-base slice

Session: `port-dev-markerpdf-named-destinations-20260608T232831Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T232831Z`
Base accepted HEAD: `72ddd104de73563cbfd9ef3ec17976bf6afc1676`

## Behavior

PDF name trees can contain multiple raw string keys that decode to the same
Unicode label, but malformed destination rows should not create an ambiguous
decoded-name collision for otherwise valid WordPress navigation aliases.

This slice covers a `/Names /Dests` tree where literal ASCII `(Collision)`
points at missing page object `99 0 R`, while UTF-16BE
`<FEFF0043006F006C006C006900730069006F006E>` decodes to the same `Collision`
label and points at a valid `/XYZ` destination. `(Alias Valid)` points at
`(Collision)`.

The native named-destination and metadata extractors now compute decoded-name
collisions from map-allowed / normalizable rows. Alias resolution still tries
raw PDF string bytes first, preserving existing source-byte collision behavior,
but falls back to the decoded key when the raw-byte target cannot normalize and
the decoded key is available. The invalid row remains visible only as
`document_destinations.unresolved_count=1`; it does not leak into destination
names, outline/link/span promotion, or visible text.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationInvalidDecodedCollisionBoundaryCurrentBaseTest.php`

Result before the fix: `1 test files, 21 assertions, 1 failures`, with
standalone named destinations missing `Alias Valid`.

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationInvalidDecodedCollisionBoundaryCurrentBaseTest.php`

Result: `1 test files, 45 assertions, 0 failures`.

Adjacent named-destination, metadata, outline, annotation, and link family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationInvalidDecodedCollisionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionSourceOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameTreeKeyActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php`

Result: `12 test files, 1877 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-invalid-decoded-collision-currentbase.php`

Result: exits `0`; reports `destination_names=["Collision","Alias Valid"]`,
`destination_pages=[1,1]`, `metadata_destination_pages=[1,1]`,
`metadata_unresolved_count=1`, `toc_pages=[1]`, `link_destination_page=1`,
`span_destination_page=1`,
`visible_text_excludes_destination_metadata=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat decoded-collision direct destination extraction,
source-byte alias routing, decoded-collision action dictionaries, decoded
source-order review, ordinary alias resolution, or action alias-cycle
rejection. The new boundary is malformed raw-byte rows being excluded from
decoded collision disambiguation while valid decoded aliases remain usable.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
PDF name-tree parser, PDF string decoder, named-destination extractor,
document metadata review extractor, outline extractor, annotation extractor,
link promotion, and Markdown post-processing paths. No Python, OCR/model,
GPU/Torch, external PDF tools, PDF action execution, or live provider services
are involved.

## Next Task

A useful follow-up is a distinct native searchable-PDF boundary around xref
repair, annotations/forms, page geometry, font/CMap behavior, image/filter
metadata, or supplied-boundary table/equation review that does not revisit
decoded-collision alias lookup.
