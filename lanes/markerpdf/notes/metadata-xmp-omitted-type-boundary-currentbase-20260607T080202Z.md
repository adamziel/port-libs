# markerpdf XMP metadata omitted-Type boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260607T080202Z`

Accepted base: `912c56d812f68fca8f6ea91b90c49265da9a9a1d`

## Behavior

PDF catalog `/Metadata` streams identify document XMP by the catalog reference plus `/Subtype /XML`. The stream dictionary `/Type` entry is optional when present as `/Metadata`, but the current native extractor treated an omitted `/Type` as a non-metadata XML stream and fell back to trailer `/Info`.

This patch keeps the existing fail-closed behavior for duplicate, conflicting, malformed, direct, unresolved, unreadable, and non-stream `/Metadata` operands, while accepting catalog metadata streams that omit `/Type` and still declare `/Subtype /XML`.

## Red-first evidence

Before the source change, a direct omitted-`/Type` probe returned:

```text
source=["info","catalog"]
title="Info Title"
metadata_stream_review.status="rejected_non_metadata_xml_stream"
```

That meant WordPress import metadata used the fallback Info title even though the catalog `/Metadata` stream contained valid XMP.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php
=> 1 test files, 128 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
=> 49 test files, 3111 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-xmp-omitted-type-boundary-currentbase.php
=> exits 0; source=["xmp","info"], title_from_xmp=true, catalog_rejection_absent=true, visible_text_excludes_xmp=true
```

## Non-overlap

This does not repeat accepted duplicate catalog `/Metadata`, null `/Metadata`, non-stream metadata objects, unreadable compressed metadata, xpacket begin/end packet bounds, XMP namespace/CDATA/comment/entity handling, encrypted metadata policy, xref generation selection, OutputIntent, associated-file, PieceInfo, or PDF/A schema provenance slices. The bounded behavior is only the optional `/Type` entry on the catalog XMP metadata stream dictionary.

## Dependency closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, catalog `/Metadata` resolver, stream filter decoder, XMP packet parser, metadata merger, text extractor, and WordPress smoke renderer. No Python, CUDA, OCR, model execution, PDFium, external PDF tools, or live services were used.
