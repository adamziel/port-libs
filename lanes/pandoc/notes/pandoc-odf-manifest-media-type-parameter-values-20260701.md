# ODF Manifest Media-Type Parameter Values

Hook: `plib-l8xvy`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

## Scope

ODF/ODT package ingestion now carries metadata-only manifest media-type
parameter value rollups through both compact `OpenDocumentPackage` summaries
and rich `OdfReader` import reports.

The new fields preserve the existing per-entry parameter maps and add aggregate
review data:

- distinct parameter value counts;
- values grouped by parameter name;
- occurrence counts for each parameter name/value pair;
- affected manifest parts and raw media-type declarations;
- package identity aliases for quick compact/rich comparison.

This remains native PHP package metadata review. It does not inspect or expose
package payload bytes, and it does not invoke Pandoc, office suites, TeX/browser
engines, Typst, Jupyter, Node, zip/unzip, validators, or live services.

## Evidence

- Extended `OdfManifestMediaTypeSummaryCompactParityTest.php` to compare the new
  summary fields across compact and rich ODF paths.
- The fixture now asserts `charset=UTF-8`, `profile="review cover"`, and
  `role="preview"` value rollups, and confirms profile value changes affect the
  package identity.

## Non-Overlap

This slice does not repeat existing ODF manifest media-type name summaries,
preferred-view-mode review, declared-size role buckets, manifest coverage,
package path inventories, ZIP source-record provenance, CRC32, timestamp,
comment, extra-field, platform, or name-hygiene slices. It only adds aggregate
parameter value provenance for manifest media-type declarations.
