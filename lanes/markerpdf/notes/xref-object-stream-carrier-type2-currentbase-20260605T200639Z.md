# markerPDF xref object-stream carrier type-2 current base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T200639Z`
Session: `port-dev-markerpdf-object-xref-20260605T200639Z`
Base accepted HEAD: `b04f57c7230c881432b7183ac804ada5839368dd`

## Source truth

- Upstream markerPDF delegates searchable-PDF parsing to PDF text extraction before OCR/model fallback. This slice stays in the native no-GPU PHP parser path and does not run Python, OCR, PDFium, Surya, Texify, Torch, or external PDF tools.
- PDF 1.5 xref-stream type-2 rows point to ordinary generation-zero objects inside a direct `/ObjStm` carrier. The carrier itself is a stream object and must remain a direct file object, even when a malformed current xref-stream row incorrectly advertises that carrier object number as compressed.
- WordPress import review must keep visible text, XMP/Info/catalog metadata, EmbeddedFiles extraction, and lightweight attachment summaries on the current object graph while excluding decoy compressed carrier members and payload bytes from summaries.

## Implementation

`PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now preserve direct `/ObjStm` carrier definitions when a type-2 xref-stream row targets the carrier object number itself. They also preserve direct `/XRef` stream definitions for the same owner-cycle boundary. This ports the already-covered `PdfTextExtractor` behavior into the coupled metadata and attachment parsers, so current compressed catalog, Info, name-tree, and FileSpec dictionaries can be expanded from the direct carrier instead of being dropped or replaced by a decoy compressed member.

## Red-first evidence

Before the parser edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps direct object-stream carriers when malformed type two rows target the carrier itself (lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'xmp',
  1 => 'info',
  2 => 'catalog',
)
Actual: array (
)
1 test files, 6 assertions, 1 failures
```

## Focused verification

After the parser edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps direct object-stream carriers when malformed type two rows target the carrier itself
1 test files, 37 assertions, 0 failures
```

Adjacent object-stream and metadata/attachment gates:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentCarrierRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentFreeCarrierRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS summarizes xref-stream object-stream FileSpec attachments before stale direct rows
PASS keeps direct object-stream carriers when malformed type two rows target the carrier itself
PASS repairs current xref stream carrier rows before expanding type-2 object-stream members
PASS repairs a current xref-stream free carrier row when valid type two rows point at the current object stream
PASS keeps current object-stream base direct while applying explicit type-2 member index
PASS selects current object-stream catalog metadata and attachments across xref Prev chain
6 test files, 139 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 1774 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-carrier-type2-currentbase.php
```

The smoke reports `direct_carrier_preserved=true`, `attachment_count=1`, `filenames=["current-type2-carrier.xml"]`, `payload_bytes_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat the accepted explicit type-2 member-index text path, omitted carrier-row repair, free carrier-row repair, unsupported xref-stream entry fail-closed behavior, or `/Prev` object-stream metadata slice. The new boundary is the metadata, full embedded-file, and lightweight attachment parser behavior when the current xref stream contains a malformed type-2 row for the direct object-stream carrier itself.

## Dependency closure

No new support component is needed. The patch reuses the native PHP xref-stream parser, object-stream member parser, FlateDecode support, metadata extractor, embedded-file extractor, attachment-summary path, and WordPress smoke/example. OCR/model execution and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around xref repair, fonts/CMaps, stream filters, page geometry, annotations/forms, image/filter metadata, and security preflight.
