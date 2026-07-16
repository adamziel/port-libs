# markerpdf named-destination stream-node boundary current-base

Session: `port-dev-markerpdf-named-destinations-20260608T100435Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T100435Z`
Accepted base: `6bc71cbbbe736a9858bd60708161d8103d8ce185`

## Source truth

The pinned upstream markerPDF manifest points at `sddai/markerPDF`
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream uses native PDF text
extraction before OCR/model fallback; under the current no-GPU lane scope this
patch ports the native searchable-PDF trust boundary only. PDF name-tree nodes
are dictionary nodes. Referenced stream objects such as object streams,
metadata streams, embedded-file streams, xobjects, or xref streams are carrier
objects, so their leading stream dictionary must not be promoted as a catalog
`/Names /Dests` name-tree node for WordPress navigation or action review.

## Implemented behavior

Added a current-base regression fixture where catalog `/Names /Dests` points to
a stream object whose stream dictionary carries a decoy `/Names` array. The
native named-destination, metadata, outline, and action-review consumers now
reject referenced stream-object name-tree roots and child nodes before
destination collection. Legacy catalog `/Dests` entries remain valid and still
promote to WordPress navigation/link review.

The new WordPress smoke exercises the native no-GPU import path and proves that
only the legacy `LegacyOk` destination is emitted while the stream carrier's
`Carrier Decoy` name never becomes metadata, link promotion, or visible text.

## Red-first evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationStreamNodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 4 assertions, 2 failures`

Failures showed `Carrier Decoy` being promoted into document destinations and
local annotation-action review.

## Verification

`php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php`
`php -l lanes/markerpdf/src/PdfActionReviewExtractor.php`
`php -l lanes/markerpdf/src/PdfOutlineExtractor.php`
`php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
`php -l lanes/markerpdf/tests/PdfNamedDestinationStreamNodeBoundaryCurrentBaseTest.php`
`php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-stream-node-boundary-currentbase.php`

All changed PHP files reported no syntax errors.

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationStreamNodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 42 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*Test.php`

Result: `59 test files, 1940 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Destination*Test.php lanes/markerpdf/tests/PdfOutlineMetadata*Destination*Test.php lanes/markerpdf/tests/PdfLinkAnnotation*Destination*Test.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php`

Result: `21 test files, 1441 assertions, 0 failures`

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-stream-node-boundary-currentbase.php`

Result: exit 0 with `stream_object_destination_rejected=true`,
`legacy_destination_promoted=true`, `visible_text_excludes_stream_payload=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status delta

Adds 2 focused PHP PASS cases, 42 focused assertions, and 1 WordPress smoke.
Expected lane counters: `phpPass` 3032 -> 3034 and `wordpressScenarios`
2507 -> 2508.

## Non-overlap

This patch does not work on OCR, Surya/Texify/Torch, live model workers,
PDFium execution, exact upstream benchmark parity, xref repair, font/CMap
decoding, outlines traversal, annotation action execution, or image/filter
payload extraction. It is limited to the native named-destination name-tree
stream-object boundary and the directly coupled WordPress navigation review
surfaces.

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP
PDF object parser, dictionary resolver, name-tree traversal, annotation action
review, outline review, metadata extraction, and WordPress block conversion
helpers. Remaining OCR/model parity is an intentional no-GPU scope limit, not
a dependency blocker for this slice.
