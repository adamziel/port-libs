# DOCX ZIP package layout summary handoff

This slice keeps the DOCX/OpenXML reader on native PHP ZIP/OPC ingestion and forwards the shared
`ZipPackage::packageManifestPreflight()` archive-layout fields into `packageProvenance.summary`.

Added summary fields cover central-directory offsets and ends, the central-directory-to-EOCD gap,
EOCD offsets and ends, package-comment offsets and presence, and central-directory signature
metadata. The existing full `zipPackageManifestPackageSource` array is still preserved; these
scalar fields make the blocker review path filterable without unpacking that nested source record.

Focused coverage lives in `DocxOpenXmlReaderTest.php` under the DOCX source ZIP package manifest
fixture, including a package comment and native manifest comparisons.
