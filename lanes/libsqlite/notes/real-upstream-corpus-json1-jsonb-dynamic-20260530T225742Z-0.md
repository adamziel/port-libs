# real-upstream-corpus-json1-jsonb-dynamic-20260530T225742Z-0

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`

## Ported Behavior

Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorDynamicCorpusTest.php`, a focused dynamic port of real upstream `json101.test` constructor and mutation-boundary behavior:

- `json101-1.1`: `json_array()` and `jsonb_array()` preserve SQL scalar values, text quoting, JSON subtype insertion, and JSONB value insertion.
- `json101-1.3`: raw non-JSONB BLOB values are rejected by array constructors.
- `json101-2.1`: `json_object()` and `jsonb_object()` preserve label order and JSONB parity.
- `json101-2.2`: object labels must be text and object constructors require even argument counts.
- `json101-3.1` through `json101-3.4`: SQL text replacements remain strings, while JSON subtype and JSONB replacements become JSON objects/arrays.
- `json101-4.5` through `json101-4.8`: no-edit `json_remove()`, `json_replace()`, `json_set()`, and `json_insert()` return the canonical input, with JSONB parity.

## Evidence

Focused verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorDynamicCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorDynamicCorpusTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorDynamicCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS real upstream json101 1.1 constructors preserve SQL scalars and JSON subtype values
PASS real upstream json101 2.1 object constructors preserve labels order and JSONB parity
PASS real upstream json101 3.1 json text versus JSON value insertion boundaries
PASS real upstream json101 4.5 no edit mutation functions return canonical input
PASS real upstream json101 1.3 and 2.2 constructor error boundaries
PASS real upstream json101 constructor dynamic source citations

1 test files, 5621 assertions, 0 failures
```

Focused assertion count: `5621`.
Focused PASS-line growth: `+6`.

## Non-Overlap

This is not metadata-only admission and does not add fabricated upstream script ids. It widens real upstream `json101.test` constructor, value-boundary, no-edit mutation identity, and JSONB parity coverage. It avoids accepted JSON table cursor/source/hidden/visible constraints, JSON105 reverse-index path mutation, JSON109 array insertion, JSON103 aggregate/window behavior, JSON106/108 invariant stress coverage, JSON501/502 escaped JSON5 coverage, and JSON107 legacy BLOB text behavior.

Mapped coverage remains unchanged because `json101.test` is already hydrated and mapped.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON helpers: `SQLiteJsonConstructor`, `SQLiteJsonMutation`, `SQLiteJsonRemove`, `SQLiteJsonValidity`, `SQLiteJsonCanonical`, `SQLiteJsonSubtypeValue`, and `SQLiteJsonB`.
