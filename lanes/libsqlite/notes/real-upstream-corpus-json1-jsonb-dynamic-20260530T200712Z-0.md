# real-upstream-corpus-json1-jsonb-dynamic-20260530T200712Z-0

Lane: `libsqlite`
Base accepted HEAD: `ab0d9bc9baa20e0418309c1ec67c0447e4a67962`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`

## Ported Upstream Sections

- `json502-1.1`: JSON5 object input through `json_tree()` preserves fullkey chain `$`, `$.a`, `$.a.b`, `$.a.b.c`.
- `json502-2.1` through `json502-2.3`: malformed JSON5 reports byte position `9`, rejects `json()` canonicalization, and rejects extraction.
- `json502-3.1` through `json502-3.4`: escaped object labels compare equal across source labels, path labels, insert paths, and merge patch labels.
- `json502-4.1`: quoted control-character root path can address the matching JSON member through `json_tree()` and extraction.
- `json502-5.1` through `json502-5.3`: embedded quote labels work in unquoted and quoted path forms and in `json_set()` / `jsonb_set()`.

## Behavior Change

`SQLiteJsonPath::decodeBareMember()` now preserves doubled backslash path text as a literal two-backslash member instead of collapsing it to the same label as a trailing one-backslash path. This matches upstream `json502-3.3`, where `$.a\` finds the inserted one-backslash label and `$.a\\` does not.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedPathDynamicTest.php
1 test files, 3682 assertions, 0 failures
```

Adjacent JSON501/JSON502 corpus:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501502DynamicBulkTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedPathDynamicTest.php
2 test files, 14157 assertions, 0 failures
```

Syntax:

```text
php -l lanes/libsqlite/src/SQLiteJsonPath.php
php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedPathDynamicTest.php
```

Both changed PHP files reported no syntax errors.

## Non-Overlap

This is real upstream `json502.test` escaped-label/path behavior. It does not add metadata-only rows, generated fake script ids, WordPress-shaped APIs, or duplicate accepted JSON table cursor/source/hidden/visible constraint work. It also avoids the accepted bulk `json106`, `json101`, `json102`, `json105`, `json109`, and `jsonb01` slices except where the adjacent JSON501/JSON502 family was run for regression evidence.

## Dependency Closure

No new support component is needed. The slice reuses native JSON5 parsing, JSON path parsing, JSONB encode/decode, JSON mutation, JSON patch, JSON tree, and JSON error-position helpers already present in `lanes/libsqlite/src`.
