# XMP Stream Dictionary Duplicate Type Boundary Current Base

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T103403Z`  
Base: `aafdefee09bf90e527df1bcd5b451a92fb989b76`

## Behavior

Catalog `/Metadata` streams now reject duplicate top-level `/Type` or
`/Subtype` keys before document XMP promotion. This keeps malformed stream
dictionaries review-only even when last-key-wins parsing would otherwise make
the final values `/Type /Metadata` and `/Subtype /XML`.

The rejected stream still records redacted review metadata under
`catalog.metadata_stream_review`, including duplicate key names, observed Type
and Subtype values, filters, byte length/hash, and an XMP field/date summary.
Valid escaped single keys such as `/Ty#70e /Metadata` and `/Sub#74ype /XML`
remain accepted as ordinary document XMP.

This matches markerPDF's no-GPU native boundary: document metadata comes from a
trusted PDF catalog metadata stream, while ambiguous or malformed stream role
dictionaries must not promote hidden XML values into WordPress document roots
or Gutenberg paragraphs.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpStreamDictionaryDuplicateTypeBoundaryCurrentBaseTest.php`

Result: `1 test files / 18 assertions / 1 failure`

The duplicate-key fixture was promoted as `source=["xmp","info"]` instead of
remaining `source=["info","catalog"]` with review-only XMP.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpStreamDictionaryDuplicateTypeBoundaryCurrentBaseTest.php`

Result: `1 test files / 40 assertions / 0 failures`

Adjacent XMP metadata family:

`php tools/run-tests.php $(ls lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php | sort) lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `42 test files / 2714 assertions / 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-stream-dictionary-duplicate-type-boundary-currentbase.php`

Result: emitted `source=["info","catalog"]`,
`xmp_promoted=false`,
`review_status="rejected_duplicate_metadata_stream_type_keys"`,
`duplicate_keys=["Type","Subtype"]`,
`hidden_xmp_redacted=true`, and `visible_text_excludes_xmp=true`.

## Non-Overlap

This does not repeat accepted XMP packet padding, complete-packet fallback,
empty-root/self-closing fallback, non-Adobe namespace, xpacket instruction,
unpaired begin, comment, CDATA, entity, qualified value, typed-node,
attribute/member-list, stream filter ownership, stream-object tail,
direct/unresolved Metadata reference, duplicate catalog `/Metadata` key,
unreadable stream, xref, image/filter, OutputIntent, associated-file, or
encrypted metadata clusters. The bounded behavior is only duplicate
`/Type`/`/Subtype` keys in the root Catalog `/Metadata` stream dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, top-level dictionary parser, stream decoder, catalog `/Metadata`
review path, XMP parser, Info fallback, and WordPress smoke renderer. Python,
pdftext, pypdfium/PDFium, PIL, Surya, Texify, Torch, OCR/model workers,
Streamlit/FastAPI services, online services, and external PDF tools remain
intentionally outside this no-GPU markerPDF slice.
