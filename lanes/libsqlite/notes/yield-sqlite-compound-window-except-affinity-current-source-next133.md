# Compound Window EXCEPT Affinity Current Source Next133

- Slice: `compound-select-window-except-affinity-current-source-next133`
- Base: `f04a2e06641da7b8156e045fbf02ee56810922b7`
- Added behavior: `SQLiteCompoundWindowExceptAffinityCurrentSourceNextPlan` records parser-level compound `EXCEPT` over windowed SELECT arms, current/next row signatures, removed EXCEPT rows, window metadata, and SQLite storage-class affinity class changes.
- WordPress smoke: `examples/wordpress-compound-window-except-affinity-current-source-next133.php` models copied `wp_options` rows subtracting network options while preserving text-vs-numeric differences.
- Focused test evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowExceptAffinityCurrentSourceNext133Test.php` passed with `1 test files, 120 assertions, 0 failures` and 54 PASS lines.
- Expected dashboard movement if accepted: `phpPass` `55029 -> 55083` (+54 PASS lines). Mapped coverage unchanged at `606 / 1589`; this is focused PHP behavior growth, not a new manifest-backed upstream row.
- Non-overlap: avoids accepted compound recursive/window next129, grouped SELECT text, SQL expression ORDER BY, compound row-value RETURNING batch130, JSON table source/cursor/hidden/visible constraints, WAL/VFS transaction/write/lock clusters, and B-tree page/overflow/root-collapse clusters.
- Dependency closure: reuses existing native PHP SELECT compound/window/query helpers; no new support component is needed.
- Root harness: not run - isolated micro-slice.
