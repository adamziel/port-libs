# XMP Alt Attribute Membership Boundary

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T160745Z`

Accepted base: `d4dade701f14fb2b26e0c359f97ad9c5febe3948`

## Behavior

RDF/XML allows collection membership properties to be represented as ordered
attributes such as `rdf:_1` and `rdf:_2`. MarkerPDF already handled those
membership attributes for list metadata. This slice extends the same native XMP
collection path to RDF containers themselves, so document XMP such as
`<rdf:Alt rdf:_1="Title" rdf:_2="Secondary"/>` promotes the first ordered value
for title and description while preserving existing packet, Catalog `/Metadata`,
and text-extraction boundaries.

Rejected non-Metadata XML streams still remain review-only: the parser reports
field names and counts in `catalog.metadata_stream_review.xmp_summary`, redacts
the packet payload, and keeps XMP text out of visible WordPress paragraphs.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpAltAttributeMembershipBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 15 assertions, 2 failures`. The
accepted document XMP fell back to Info title, and the rejected stream summary
omitted title/description.

Green after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpAltAttributeMembershipBoundaryCurrentBaseTest.php`

Result: `1 test files, 50 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF stream
decoder, Catalog `/Metadata` boundary review, DOM-based XMP parser, and text
extractor. No Python, OCR, GPU/model execution, raster rendering, live services,
or external PDF tools are invoked.

## Next

Continue with non-overlapping native searchable-PDF behavior: XMP/PDF metadata
edge cases, font encodings/CMaps, stream filters, xref repair, annotations,
forms, page geometry, image/filter metadata, or supplied-boundary handoffs.
