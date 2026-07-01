# ODF Package Case-Fold Basename Stems

2026-07-01 plib-8r74r adds metadata-only package basename stem fold inventories to the ODF/ODT package ingestion path.

- `OpenDocumentPackage::summarize()` now carries `packageCaseFoldedBasenameStemCounts`, `entryNamesByPackageCaseFoldedBasenameStem`, and duplicate folded-stem summaries through compact `packageInventory` and `packageIdentity`.
- `OdfReader` carries the same fields through rich `packageProvenance`, `packageIdentity`, and document manifest metadata.
- Per-entry package metadata now includes `packageBasenameStem` and `packageCaseFoldedBasenameStem` so case-only basename stem collisions such as `HERO.PNG` and `hero.png` remain visible without exposing package bytes.
- Focused evidence: `OdfPackageCaseFoldBasenameStemInventoryTest.php` maps 1 ODF/ODT package-ingestion case with 45 assertions. No Pandoc, Office, ZIP CLI, browser, Node, or external validator is invoked.
