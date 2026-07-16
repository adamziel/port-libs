# Outline Destination Action Metadata Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T142925Z`
Base accepted HEAD: `bf75562f447c1c8f603ede7bf5edd88ff3917f71`

## Source Truth

Native markerPDF no-GPU scope only. This slice follows PDF outline/name-tree behavior where a catalog outline item's `/Dest` name can resolve through `/Names /Dests` to a local action dictionary instead of a plain destination array. The upstream boundary is review-only metadata: URI, JavaScript, and Launch payload strings must not become document metadata or visible WordPress text.

## Implementation

- `PdfMetadataExtractor::documentOutlineItemMetadataRow()` now adds `destination_action_*` fields when an outline `/Dest` resolves to an action dictionary.
- The new metadata is payload-free: action name/object/type, chain count, chain types, object ids, and JavaScript/Launch flags only.
- Direct `/A` outline action summaries keep their existing `action_*` keys; `/Dest`-sourced action dictionaries use separate `destination_action_*` keys.

## Evidence

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfOutlineMetadataDestinationActionChainCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-destination-action-chain-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationActionChainCurrentBaseTest.php` => 1 test files, 54 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataActionChainBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationViewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php` => 5 test files, 298 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-destination-action-chain-currentbase.php` => smoke emitted `document_metadata_payload_excluded=true`, `direct_outline_action_keys_absent=true`, `visible_text_excludes_outline_action_metadata=true`, and model/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP object parsing, name-tree destination map, dictionary resolution, and outline action-chain summarizer. GPU/OCR/model parity remains intentionally out of scope for this lane.

## Follow-Up

Separate from this document-metadata boundary, older `PdfOutlineExtractor` navigation review coverage for destination names that resolve to action dictionaries is still a parser/navigation admission surface to handle in a future navigation-focused slice.
