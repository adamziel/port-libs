# markerpdf-xmp-metadata-boundary-current-base-20260605T194830Z

Accepted base: `0c4a7b708ed751f3a29de5406d9dde90a2973a6d`

## Scope

This isolated markerPDF slice keeps catalog `/Metadata` XMP promotion bounded to real PDF stream objects. If the catalog references an indirect object that is only a dictionary, even when that dictionary advertises `/Type /Metadata /Subtype /XML` and `/Length`, `PdfMetadataExtractor` now records the review row as `rejected_non_stream_metadata_object` and does not promote any dictionary values into document XMP or visible text.

## Source Truth

- Upstream source anchor: `sddai/markerPDF` pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Native PDF parser boundary: document XMP metadata is accepted from catalog `/Metadata` only when the referenced object is a PDF stream object with `/Type /Metadata /Subtype /XML`.
- No-GPU scope: this patch is parser-only. It does not run OCR, Surya, Texify, Torch, Python model code, multiprocessing workers, or external PDF tools.

## Behavior Added

- Reuses the existing native object parser, dictionary reader, stream decoder, XMP parser, Info fallback, and text extractor.
- Adds a distinct fail-closed status for indirect catalog `/Metadata` dictionaries without a `stream` keyword: `rejected_non_stream_metadata_object`.
- Preserves review metadata such as object number, `/Type`, `/Subtype`, and declared `/Length`.
- Keeps Info metadata fallback and visible page text intact while redacting hidden dictionary values from both encoded metadata and extracted WordPress text.

## Verification

- Red-first before source change:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 48 assertions, 1 failures`
- Focused after source change:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 56 assertions, 0 failures`
- Adjacent XMP metadata family:
  - `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfMetadataXmp.*CurrentBaseTest\.php|PdfMetadataExtractorTest\.php' | sort)`
  - Result: `32 test files, 2220 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-boundary-currentbase.php`
  - Result: emits `non_stream_metadata_status=rejected_non_stream_metadata_object`, `non_stream_metadata_type=Metadata`, `non_stream_metadata_subtype=XML`, `non_stream_metadata_declared_length=321`, `non_stream_metadata_values_redacted=true`, and `non_stream_metadata_not_visible_text=true`.

## Non-Overlap

This does not repeat the accepted XMP packet begin/end boundary, stream-object tail rejection, associated FileSpec generation exactness, DTD/entity rejection, UTF-16 packet decoding, direct/null/unresolved catalog `/Metadata` handling, unreadable filtered stream handling, encryption preflight, OutputIntent, attachment, outline, annotation, forms, runtime, or model/OCR handoff slices.

## Dependency Closure

No new support component is needed. The patch reuses bounded native PHP PDF parsing and metadata review helpers already in `lanes/markerpdf/src`, and the WordPress smoke stays local with synthetic in-memory PDFs.
