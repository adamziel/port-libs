# SQLite B-tree Vacuum Pointer-map Freeblock Current Source Next927-942

- Extends the existing consolidated B-tree vacuum pointer-map/freeblock current-source plan with direct wrappers for `next927` through `next942`.
- Keeps `next926` as the predecessor handoff and reuses the same freelist/current-source variant instead of adding a numbered source class.
- Focused coverage checks current-source page order, freeleaf publication, token chaining, tail-page exclusion, and batch-size row-count behavior for all sixteen follow-on slices.
