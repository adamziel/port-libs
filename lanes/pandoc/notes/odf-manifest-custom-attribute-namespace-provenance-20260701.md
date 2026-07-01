# ODF manifest custom attribute namespace provenance

ODF/ODT package ingestion now carries metadata-only namespace URI rollups for
custom manifest file-entry attributes through compact `OpenDocumentPackage`
manifest review/package identity and rich `OdfReader` package provenance/document
identity. The slice distinguishes same-prefix custom attributes declared under
different namespace URIs without exposing package payload bytes or invoking
external Pandoc, office, ZIP, browser, or validator tools.

Focused coverage:

- `lanes/pandoc/tests/OdfManifestCustomAttributeNamespaceParityTest.php`

Direct-format parity remains active for the ODF/ODT blocker lane.
