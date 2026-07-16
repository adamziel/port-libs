# DOCX Relationship Target Case-Fold Parts - 2026-07-01

- Added `packageProvenance.summary.relationshipTargetCaseFoldPart*` and `duplicateRelationshipTargetCaseFoldPart*` fields for internal relationship target paths whose full package part names collide after case folding.
- The summary keeps exact duplicate relationship targets separate from case-only target variants, tracks existing vs missing targets, content type/source buckets, relationship ids, target roles, and unique existing target byte rollups.
- Covered with an in-memory DOCX fixture. No external validators, office/Pandoc subprocesses, ZIP shell tools, or Node tooling are used.
