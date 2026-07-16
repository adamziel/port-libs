# XMP Unquoted Instruction Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T064912Z`  
Base: `c73ab3af9ca883f50ffd6b3d1d33ae6c6162db8c`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- In the current no-GPU markerPDF scope, Catalog `/Metadata` XMP packet selection is native parser behavior before WordPress import; OCR, Surya, Texify, PDFium rendering, PIL, and model execution are intentionally out of scope.
- XMP packet delimiters are `xpacket` processing-instruction pseudo-attributes. XML pseudo-attribute values are quoted, so unquoted `begin=...` or `end=w` text must not define the active document metadata packet.

## Behavior

`PdfMetadataExtractor` now records whether parsed `xpacket` pseudo-attributes were quoted and only treats quoted `begin` plus quoted terminal `end="r"` or `end="w"` as packet boundaries.

This keeps stale unquoted packets out of:

- promoted document XMP metadata;
- redacted rejected XML stream summaries;
- visible WordPress paragraph text.

Quoted current packets still delimit normally, and trailing valid packets remain excluded once the current packet is selected.

## Red First

Before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUnquotedInstructionBoundaryCurrentBaseTest.php`

Result: `1 test files, 19 assertions, 2 failures`

Failures:

- expected `Current Quoted Instruction XMP Title`, got `Stale Unquoted Instruction XMP Title`;
- expected rejected-summary created date `2026-06-08T07:03:31Z`, got stale date `2026-06-08T07:59:59Z`.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUnquotedInstructionBoundaryCurrentBaseTest.php`

Result: `1 test files, 42 assertions, 0 failures`

Adjacent XMP packet-boundary family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUnquotedInstructionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInstructionAttributeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInternalBeginInstructionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInternalEndInstructionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpUnpairedBeginBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMalformedPacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCompletePacketFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php`

Result: `9 test files, 410 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-unquoted-instruction-boundary-currentbase.php`

Result: passed. The smoke reports `unquoted_packet_ignored=true`, `trailing_packet_excluded=true`, `visible_text_excludes_xmp_metadata=true`, `rejected_xml_summary_status=rejected_non_metadata_xml_stream`, `rejected_xml_summary_redacted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2949 -> 2951` from 2 new focused TestRunner PASS cases.
- `wordpressScenarios`: `2452 -> 2453` from the new WordPress smoke.
- Manifest mapped count: unchanged; this is a bounded native parser behavior slice already within the metadata/XMP inventory.

## Non-Overlap

This does not repeat accepted XMP packet padding, complete-packet fallback, instruction attribute quote-text decoys, internal begin/end instructions inside the active root, malformed first-packet fail-closed handling, unpaired begin recovery, comment/CDATA/root false-boundary handling, entity/DTD rejection, typed-node parsing, language alternatives, PDF/A schema correlation, encrypted metadata priority, xref metadata generation repair, or PageLabels/outline/image/filter boundary work.

The bounded behavior is only the trust boundary for unquoted `xpacket` processing-instruction pseudo-attributes.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet scanner, DOM-based metadata extractor, metadata review summary path, text extractor, focused TestRunner, and WordPress smoke pattern. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
