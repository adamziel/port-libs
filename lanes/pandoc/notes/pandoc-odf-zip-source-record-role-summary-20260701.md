# ODF ZIP Source Record Role Summary

Added role-level ZIP source-record provenance for OpenDocument packages in the Pandoc lane.

The compact package summary and rich ODF reader provenance now expose `packageZipSourceRecordRole*` fields that bucket local record bytes, central directory bytes, compressed data bytes, data descriptor occurrences, review issue occurrences, directory roots, compression methods, byte exposure policies, manifest media metadata, entry names, and the largest source-record entry by package role.

The slice is covered by `OdfZipSourceRecordRolesTest.php`, which exercises compact inventory, compact identity, rich import provenance, rich identity, and document-level package provenance using a small ODT package with core parts, media, thumbnail, signature, sidecar, and undeclared package entries.
