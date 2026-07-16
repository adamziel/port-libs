# DOCX Relationship Target Name Characters - 2026-07-01

- Added `packageProvenance.summary.relationshipTargetNameCharacter*` fields for internal relationship target part names that carry uppercase, whitespace, percent-encoded octets, or non-ASCII bytes after OPC target resolution.
- The summary keeps relationship-count buckets separate from unique target part lists, and includes metadata-only review items with source part, relationship part, relationship id, content type source, and target roles.
- Covered with an in-memory DOCX fixture. No external validators, office/Pandoc subprocesses, ZIP shell tools, or Node tooling are used.
