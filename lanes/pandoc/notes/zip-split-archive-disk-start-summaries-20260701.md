# ZIP Split Archive Disk Start Summaries

Slice: `plib-z8i8q` on 2026-07-01.

`ZipPackage::splitArchivePreflight()` now reports metadata-only disk-start buckets before bounded package import:

- `diskStartValues` and `diskStartEntryCounts` for every central-directory entry disk-start value.
- `splitArchiveDiskStartValues` and `splitArchiveDiskStartEntryCounts` for non-zero split-entry markers.
- `diskStartSummaries` and `splitArchiveDiskStartSummaries` with entry names, central-directory indexes, and local-header offsets grouped by disk-start value.

`ZipPackage::rawStrictImportPreflight()` carries the same structure through its existing `splitArchive` result, so DOCX/EPUB/ODF and OPC handoff callers can review split archive markers before package construction fails. The slice is metadata-only: it reads ZIP central-directory fields and names, does not expose package payload bytes, and does not invoke external ZIP tools, office suites, Pandoc, or validators.

Focused parity accounting moved by 1 shared ZIP split-archive disk-start summary case and 27 assertions in `lanes/pandoc/tests/ZipPackageSplitArchiveDiskStartSummaryTest.php`.
