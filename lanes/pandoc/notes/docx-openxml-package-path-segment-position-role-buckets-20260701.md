# DOCX OpenXML Package Path Segment-Position Role Buckets

`plib-vsnib` extends DOCX package identity/provenance with path segment-position role buckets and byte exposure policy buckets.

The slice keeps package bytes blocked while exposing deterministic package inventory metadata for `first`, `middle`, `last`, and `only` path segment positions. Array-backed reads report `docx-package-part-bytes-blocked`; ZIP-backed reads report `docx-zip-entry-metadata-only`.

Focused coverage:

- `lanes/pandoc/tests/DocxOpenXmlPackagePathSegmentPositionRoleBucketsTest.php`
