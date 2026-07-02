# DOCX ZIP source-record package part directory base names

DOCX/OpenXML package provenance now summarizes loaded ZIP source records by exact package-part directory basename and case-folded directory basename.

- `packageProvenance.summary.partZipSourceRecordPackagePartDirectoryBaseName*` exposes exact directory-basename counts, source-record byte totals, duplicate basename buckets across directories, data-descriptor and source-span issue counts, and detailed per-basename review rows.
- `packageProvenance.summary.partZipSourceRecordPackagePartCaseFoldDirectoryBaseName*` mirrors the same review surface after case folding, preserving original basename variants.
- Package identity mirrors the new source-record summaries so basename-only directory layout changes are visible in metadata-only review packets without exposing package bytes.

Verification covered the focused DOCX ZIP source-record directory-basename test, the adjacent directory-basename-stem test, and `DocxOpenXmlReaderTest.php`.
