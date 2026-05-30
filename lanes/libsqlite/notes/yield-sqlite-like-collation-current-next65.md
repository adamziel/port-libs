# SQLite LIKE Collation Current-Next65

## Scope

This slice adds a bounded native PHP LIKE prefix planner for SQLite collation
rules around Application `wp_options.option_name` scans. It does not repeat the
accepted malformed UTF-8 pattern splitter, Unicode GLOB ranges, SELECT ORDER BY
collation handling, or expression-index collation cursor work.

## Behavior

- Default SQLite LIKE can use a `NOCASE` index prefix range.
- `PRAGMA case_sensitive_like`-style matching can use a `BINARY` index prefix
  range.
- `RTRIM` and the wrong BINARY/NOCASE pairing are rejected for range use while
  direct LIKE matching still preserves SQLite matcher semantics.
- Non-ASCII prefixes are rejected for default `NOCASE` prefix planning because
  SQLite NOCASE folding is ASCII-only in this native slice.
- Parser-level `COLLATE` operands on LIKE/GLOB predicates are covered to prove
  collation metadata does not silently turn LIKE/GLOB into equality collation
  matching.

## Evidence

Focused command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteLikeCollationCurrentNext65Test.php`

Expected dashboard movement: `phpPass +50` for the 50 newly verified PASS lines
after clean integration.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
LIKE/GLOB matching and SELECT SQL parsing primitives.
