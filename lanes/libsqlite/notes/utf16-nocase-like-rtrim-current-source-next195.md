# UTF-16 NOCASE LIKE RTRIM current-source next195

Status: focused PHP behavior growth for escaped-literal LIKE tails over UTF-16
`rtrim(option_name) COLLATE NOCASE` expression keys.

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`. It reuses
the accepted UTF-16 decode, ASCII NOCASE prefix range, and RTRIM expression
key machinery, then adds the current/next residual fence needed when an escaped
literal LIKE pattern has no trailing wildcard. The index range can contain
longer keys such as `plugin_%_cache_alpha`, but those rows are residual false
positives for `LIKE 'plugin!_!%!_cache' ESCAPE '!'`; a current cursor must
recheck false-positive transitions before it is reused on the next source.

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next195.php --self-test
```

Evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext195Test.php
# Focused test run: 1 selected test files (root lock skipped)
# ...
# 1 test files, 86 assertions, 0 failures
```

Expected dashboard delta: `phpPass` moves from `93683` to `93751` from 68 newly
passing focused PASS lines. Mapped upstream coverage remains `618 / 1589`; this
is current-source PHP behavior over existing encoding/collation inventory, not
a newly hydrated upstream manifest row.

Non-overlap: avoids accepted next158/next191 UTF-16 prepared pattern rebind,
next182 escape replay, next170 resume-token, Unicode GLOB range handling, and
malformed UTF-16 insert guard clusters. The new behavior is specifically
escaped literal LIKE-tail residual false-positive transitions for UTF-16
RTRIM/NOCASE current-source cursors.

Dependency closure: no new support component is needed; this reuses native
UTF-16 decode, escaped LIKE prefix planning, RTRIM residual matching, and
current-source invalidation metadata already present in the lane.
