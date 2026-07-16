# FTS5 prefix ranking current

Date: 2026-05-30

## Scope

Adds per-token FTS5 prefix query handling to `SQLiteFts5Corpus::search()`.
The bounded corpus bridge now treats a query term ending in `*` as a prefix
term while keeping neighboring terms exact. Ranking, document frequency,
match counts, phrase checks, and snippets all use the same per-term prefix
metadata.

The public `queryTokens()` helper intentionally keeps returning plain tokens
without exposing the `*` suffix, matching the older observable API.

## Evidence

Red-first focused command before the fix:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteFts5CorpusTest.php
```

Result before implementation: `1 test files, 33 assertions, 5 failures`.
The failing cases were mixed exact/prefix `plugin cach*` and phrase
`cache refresh*` queries.

Focused command after the fix:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteFts5CorpusTest.php
```

Result after implementation: `1 test files, 33 assertions, 0 failures`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-fts5-option-search.php --self-test
```

Result: `application-fts5-option-search self-test passed`.

## Non-Overlap

This stays inside the existing FTS5 corpus bridge and does not repeat parser
level JSON table SELECT source/cursor wiring, root-gate suite evidence,
window GROUPS/RANGE validation, VFS/WAL/B-tree storage clusters, or FTS5
schema import parsing. The new behavior is mixed exact/prefix MATCH ranking
for current copied row arrays.

## Dependency Closure

No new support component is needed. The slice reuses native PHP tokenization,
BM25-style ranking, snippet rendering, and row-array corpus search.
