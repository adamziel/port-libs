# DOCX Content Type Override Path Buckets

Bead: `plib-cnn75`

This slice adds metadata-only path provenance for DOCX/OpenXML content type override declarations. The reader now carries override declaration directory, top-level segment, and part-extension counts through `packageProvenance.contentTypesPart` and `packageProvenance.summary`.

Each override declaration also records its directory, directory depth, top-level segment, base name, base-name stem, path segments, path segment count, normalized extension, and raw extension. Relationship override declarations keep the existing relationship source/source-exists review fields.

Focused coverage:

- `DocxOpenXmlContentTypeOverridePathBucketsTest.php`
- 1 fixture
- 47 assertions

The fixture covers existing, missing, parameterized image, custom XML, document, docProps, and relationship override targets without invoking upstream Pandoc, Office, external ZIP validators, or exposing package bytes.
