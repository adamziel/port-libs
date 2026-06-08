# Named-Destinations Stream Value Boundary Current Base

Slice: `markerpdf-named-destinations-boundary-current-base-20260608T122826Z`

Base: `03d7c4f1ec7ff6e233514aae2d1542db24fa965c`

## Source Truth

The native no-GPU markerPDF lane owns searchable-PDF catalog/page metadata, annotations, outlines, named destinations, and WordPress import review. Upstream markerPDF relies on pdftext/PDFium for searchable PDF navigation metadata in model-free paths; this PHP port parses `/Names /Dests` and legacy `/Dests` directly. PDF stream objects can carry payload bytes and unrelated dictionaries, so a destination value that references a stream carrier must not be trusted as a normal destination dictionary even if it contains a `/D` array.

## Behavior

`PdfNamedDestinationExtractor` now rejects stream-carrier destination values before destination normalization. The same boundary is mirrored into `PdfMetadataExtractor`, `PdfActionReviewExtractor`, and `PdfOutlineExtractor` so standalone destination rows, document metadata mirrors, annotation link promotion, and outline destination views all fail closed on the same referenced stream value.

The focused fixture keeps a valid names-tree destination, a valid legacy `/Dests` destination, and an invalid names-tree value pointing at object `21 0 R`: `<< /Type /EmbeddedFile /D [4 0 R /XYZ 72 640 0] /Length ... >> stream ... endstream`. Before the patch, `Stream Value Target` was imported and the matching link annotation was promoted as a local destination. After the patch, only `Clean Target` and `LegacyOk` remain in destination metadata and link promotion.

## Verification

Red-first current-base check before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationStreamValueBoundaryCurrentBaseTest.php`

Result before fix: `1 test files, 4 assertions, 2 failures`; actual destination names included `Stream Value Target`, and the stream-value annotation carried `local-destination`.

Passing focused checks after the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationStreamValueBoundaryCurrentBaseTest.php`

Result: `1 test files, 44 assertions, 0 failures`.

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)`

Result: `61 test files, 2029 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-stream-value-boundary-currentbase.php`

Result: exits 0 with `stream_carrier_destination_value_rejected=true`, `visible_text_excludes_stream_payload=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

Final hygiene:

- `php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php`: no syntax errors.
- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`: no syntax errors.
- `php -l lanes/markerpdf/src/PdfActionReviewExtractor.php`: no syntax errors.
- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfNamedDestinationStreamValueBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-stream-value-boundary-currentbase.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`: `json ok`.
- `git diff --check -- lanes/markerpdf`: exits 0.

## Status Delta

- `phpPass`: `3090` -> `3092`
- `wordpressScenarios`: `2549` -> `2550`
- Manifest behavior: `pdfNamedDestinationExtractorCurrentBaseBehaviors` and `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors` move `3` -> `4`

## Non-Overlap

This slice does not repeat existing named-destination Limits ordering/fallback, duplicate key rejection, malformed UTF-16 keys, stream-keyword dictionary text, object-stream extraction, action aliases, surplus operands, xref selection, page-label, outline traversal, or stream-object name-tree node rejection. It is limited to destination values that reference a stream-carrier object from an otherwise valid name-tree leaf.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object parser, generation-aware object resolver, name-tree traversal, destination normalizer, metadata mirror, annotation/link review, outline review, text extractor, TestRunner, and WordPress smoke path. It does not run OCR, Surya, Texify, Torch, PDFium, PIL, raster rendering, Python models, live services, PDF actions, or external PDF tools.
