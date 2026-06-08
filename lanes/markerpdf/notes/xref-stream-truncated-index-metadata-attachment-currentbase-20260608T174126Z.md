# xref-stream truncated Index metadata/attachment current-base slice

Session: `port-dev-markerpdf-object-xref-20260608T174126Z`
Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260608T174126Z`
Base accepted HEAD: `00ea4d517c515ab21e88a62bfef7ac09185dceae`

## Behavior

PDF xref-stream rows are fixed-width tuples. When an explicit `/Index` declares
more rows than the decoded stream contains, the current xref stream is
malformed and must not be used as a prefix row set for object-stream metadata.

This patch aligns `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and
`PdfAttachmentExtractor` with the text parser boundary:

- decoded xref-stream bytes must be aligned to the `/W` row width;
- explicit `/Index` row counts must not exceed the decoded row count;
- rejection happens before row decoding and before compressed catalog, Info,
  name-tree, and FileSpec object-stream members can be imported.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamTruncatedIndexMetadataAttachmentCurrentBaseTest.php`

Failed before the fix with metadata source leaking from the truncated row
prefix:

`Actual: array (0 => 'xmp', 1 => 'info', 2 => 'catalog')`

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamTruncatedIndexMetadataAttachmentCurrentBaseTest.php`

Result: `1 test files, 24 assertions, 0 failures`.

Adjacent xref/object-stream family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamTruncatedIndexObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainOmittedCurrentRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCompressedOperandCascadeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamWhitespaceAttachmentMetadataCurrentBaseTest.php`

Result: `5 test files, 165 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-stream-truncated-index-metadata-attachment-currentbase.php`

Result: exits `0`; reports `metadata_sources=[]`, `attachment_count=0`,
`malformed_xref_stream_row_alignment_count=1`, and
`owner_policy=truncated_xref_stream_index_rows`.

## Non-overlap

This does not repeat the existing text-only truncated `/Index` fixture,
declared object-stream member-count boundary, zero-width carrier/member-index
repair, skipped object-stream header-index repair, or same-generation omitted
current-row repair. The new coverage is the metadata/embedded-file/attachment
summary import path that previously accepted the prefix of a truncated explicit
xref-stream `/Index`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
xref-stream decoders, object-stream expansion, metadata extraction, embedded
file extraction, and attachment summary paths. No Python, OCR/model,
GPU/Torch, external PDF tools, PDF action execution, or live provider services
are involved.

## Next Task

A useful follow-up is a distinct parser boundary in annotations/forms/outlines
or xref-stream repair that is not another explicit `/Index` truncated-row
metadata/attachment slice.
