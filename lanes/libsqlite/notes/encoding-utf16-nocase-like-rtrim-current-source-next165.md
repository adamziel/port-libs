# UTF-16 NOCASE LIKE RTRIM Current-Source Next165

Timestamp: 2026-05-28T11:40:49Z

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, a bounded
current-source/next-source cursor resume plan for Application `wp_options`
`option_name` scans using:

- UTF-16LE/UTF-16BE prepared LIKE pattern and ESCAPE byte normalization.
- `rtrim(option_name)` expression keys that trim ASCII space only.
- ASCII-only SQLite `NOCASE` matching for LIKE residuals.
- A last-yielded `{key,rowid}` token that decides whether the next source can
  continue after the token or must reprepare from the range start.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext165Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 80 assertions, 0 failures
```

The focused run emits 59 PASS lines. The Application smoke is
`lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next165.php`
and covers copied `wp_options` resume behavior after yielding
`plugin_cache_alpha`.

Non-overlap:

- Does not repeat accepted next162 UTF-16 NOCASE/RTRIM pattern byte
  normalization alone.
- Does not repeat accepted Unicode GLOB range behavior or the UTF-16 malformed
  insert guard.
- Adds the distinct resume-token decision edge: byte-only pattern changes may
  remain resumable, while source/schema/pattern semantic changes, malformed
  text, new rows before the token, and retained rows moving across the token
  force reprepare.

Dependency closure: no new support component is needed. The slice reuses native
UTF-16 decode/pattern normalization, ASCII NOCASE LIKE matching, RTRIM
expression keys, and current-source metadata.
