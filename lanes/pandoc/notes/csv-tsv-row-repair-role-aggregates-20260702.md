# CSV/TSV Row Repair Role Aggregates

`plib-dicc3` follows up on landed CSV/TSV relaxed row repair provenance without changing parsed table output. `DelimitedTextReader` now adds deterministic aggregate fields to each `rowRepairSummary`:

- `repairCounts` groups rows by relaxed repair action.
- `rowRoleRepairCounts` splits repair actions across header and body rows.
- `changedSourceRows` and `changedRows` expose the source rows whose padded/truncated shape differs from the original field count.

This is metadata-only review provenance for native PHP Pandoc delimited-text imports. It does not invoke upstream Pandoc, spreadsheet applications, browsers, Node tooling, online services, live providers, or external validators.
