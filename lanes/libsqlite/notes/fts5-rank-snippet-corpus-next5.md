# FTS5 rank/snippet corpus next5

Date: 2026-05-27

## Scope

Adds bounded native PHP FTS5-style corpus behavior for copied row arrays:

- `SQLiteFts5Corpus::search()` filters rows with MATCH-style AND token semantics.
- `phrase` mode requires adjacent query tokens.
- `prefix` mode supports prefix token matching.
- Result rows include `fts5_rank`, `fts5_snippet`, `fts5_match_count`, and `fts5_source_index`.
- Ranking uses a bounded bm25-like ascending score with per-column weights.
- Snippets highlight matched tokens with configurable markers, ellipsis, column, and token window.

This is intentionally not a full FTS5 virtual table implementation. It is a focused corpus bridge for rank/snippet behavior while parser-level virtual-table execution remains incomplete.

## Evidence

Focused test command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteFts5CorpusTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
25 PASS lines
1 test files, 27 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass`: `1684 -> 1709` (`+25`, exact focused PASS-line delta)
- `benchmarkDenominator.mapped`: unchanged; this slice adds native focused PHP corpus coverage, not a newly mapped upstream inventory unit.

## Application Smoke

Added `lanes/libsqlite/examples/application-fts5-option-search.php` for copied `wp_options` full-text diagnostics. It ranks option rows for `search cache`, returns selected option ids, snippets, and bounded ranks without requiring `ext/sqlite`.

## Non-Overlap

Avoids accepted JSON table cursor/source/hidden/visible constraints, Unicode GLOB ranges, VFS file writer/sync/lock clusters, WAL byte truncation/checkpoint/rollback-journal application, B-tree page move/root collapse/overflow freelist release, SQL expression `ORDER BY`, SELECT SQL subqueries, grouped SELECT text, batch4 window EXCLUDE/FILTER, and the existing upstream `fts5aux` sanitizer release-blocker evidence.

## Dependency Closure

No new support component is required. The slice reuses lane-local row arrays and scalar PHP string/token processing. Follow-up activation gate is parser-level FTS5 virtual-table `FROM` wiring or a distinct upstream release/all blocker decision.
