## B-tree Vacuum Wrapper Consolidation

- Removed remaining numbered production factory methods from the small B-tree vacuum wrapper families by replacing them with descriptive canonical factories.
- Updated direct B-tree vacuum tests and WordPress examples to call the descriptive factories.
- Dependency closure: no new support component needed; this is a naming consolidation only and reuses existing B-tree page, freelist, freeblock, pointer-map, and overflow helpers.
- Non-overlap: this does not add new behavior coverage and does not repeat accepted B-tree page relocation, overflow freelist release, bulk overflow freeblocks, root collapse, or rollback/VFS writer work.
