# Xref Object Stream Omitted Prev Carrier Current Base

Slice: `markerpdf-object-stream-xref-parser-current-base-20260605T112940Z`

Accepted base: `1251bc133ab993e2642f4fd2c957e70cae634c16`

## Behavior

Current xref streams may carry valid type-2 rows for compressed catalog, Info,
name-tree, and FileSpec dictionaries while omitting the direct object-stream
carrier row. When `/Prev` still has a stale row for that same carrier object,
metadata and attachment parser paths must recover the current in-window
`/ObjStm` carrier before inheriting stale previous rows.

This patch ports the bounded carrier-row recovery already used by the text
parser into `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and
`PdfAttachmentExtractor`.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierPrevMetadataCurrentBaseTest.php`

Result: `1 test files, 4 assertions, 1 failures`; metadata selected
`Stale Omitted Prev Carrier XMP Title`.

Focused run after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierPrevMetadataCurrentBaseTest.php`

Result: `1 test files, 38 assertions, 0 failures`.

Adjacent family check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierPrevMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php`

Result: `3 test files, 77 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-omitted-prev-carrier-currentbase.php`

Result: current metadata title, Info title, catalog language, embedded filename,
attachment summary, and text all selected; stale Prev metadata, embedded file,
and attachment names excluded; Python/model and external-PDF-tool flags false.

## Non-Overlap

This does not repeat the text-only omitted carrier row slice, object-stream
offset-boundary slices, xref-stream `/Prev` helper operands, classic xref
rebuild trailer boundaries, or present-carrier Prev-chain metadata behavior.
The new boundary is specifically stale inherited `/Prev` carrier rows in the
metadata, embedded-file, and attachment parser implementations.

## Dependency Closure

No new support component is needed. The slice reuses native PHP object parsing,
Flate stream decoding, xref stream parsing, and object-stream expansion already
present in markerPDF. No OCR, GPU/model execution, Python, or external PDF tools
were used.
