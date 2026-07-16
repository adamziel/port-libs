# XMP Stream Filter Dictionary Boundary Current Base - 2026-06-05

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T133352Z`  
Base: `d9125af6a016500c53c9d723eacb8808f8b9e63a`

## Behavior

Catalog `/Metadata` stream decoding now reads `/Filter` only from top-level
stream dictionary entries before XMP promotion or review. Fake `/Filter`
tokens inside literal strings, arrays, nested dictionaries, or comments no
longer make an otherwise valid unfiltered XMP metadata stream look compressed.

This preserves the native PDF parser boundary used by markerPDF/pdftext-style
metadata import: stream dictionaries drive decoding, while descriptive string
payloads stay inert. WordPress imports now select current XMP metadata from
unfiltered metadata streams even when a dictionary description contains text
like `Decoy /Filter /FlateDecode`.

The patch does not run Python, PDFium, OCR, Surya, Texify, Torch, external PDF
tools, or model workers.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpStreamFilterDictionaryBoundaryCurrentBaseTest.php`

Result: `1 test files, 6 assertions, 2 failures`

Failures:

- valid unfiltered `/Type /Metadata /Subtype /XML` XMP was rejected as
  unreadable because `/Filter /FlateDecode` appeared inside a literal `/Desc`
  string;
- rejected non-metadata XML review reported `unreadable_metadata_stream`
  instead of `rejected_non_metadata_xml_stream`.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpStreamFilterDictionaryBoundaryCurrentBaseTest.php`

Result: `1 test files, 38 assertions, 0 failures`

Additional focused metadata/XMP family and smoke verification are recorded in
`lane-status.json` for this isolated patch.

## Non-Overlap

This does not repeat accepted XMP packet padding, complete-packet fallback,
empty-root fallback, namespace, instruction, comment, CDATA, entity expansion,
qualified-value, typed-node, attribute, stream-object tail, direct/unresolved
metadata reference, unreadable-stream, xref, image/filter, OutputIntent, or
associated-file metadata clusters. The new boundary is only top-level
metadata stream filter ownership when fake `/Filter` names appear inside
dictionary string values.

## Dependency Closure

No new support component is needed. This slice reuses native PHP PDF object
scanning, top-level dictionary parsing, stream decoding, catalog `/Metadata`
review, XMP packet parsing, and existing WordPress smoke rendering. GPU/model,
OCR, PDFium rendering, table-model inference, and exact upstream benchmark
parity remain intentionally outside the current no-GPU markerPDF scope.
