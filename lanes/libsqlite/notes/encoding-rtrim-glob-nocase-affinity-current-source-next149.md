# encoding-rtrim-glob-nocase-affinity-current-source-next149

## Behavior

Adds a bounded current-source plan for `rtrim(option_name) COLLATE NOCASE GLOB ?`
with `option_value` NUMERIC affinity filtering where the GLOB pattern contains
SQLite character classes. The NOCASE/RTRIM key can choose candidate rows, but
the final GLOB residual remains bytewise and case-sensitive, including negated
and Unicode ranges.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRtrimGlobNocaseAffinityCurrentSourceNext149Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-rtrim-glob-nocase-affinity-current-source-next149.php --self-test`
- Syntax checks: changed PHP files with `php -l`
- Whitespace: `git diff --check -- lanes/libsqlite`

## Non-overlap

This avoids accepted Unicode GLOB range handling, UTF-16 malformed text guard,
UTF-16 RTRIM/GLOB affinity next145, and accepted LIKE/GLOB range-only clusters.
The next149 behavior is specifically the bytewise GLOB character-class residual
after an RTRIM+NOCASE candidate key and numeric value affinity.

## Dependency closure

No new support component is needed. The slice reuses the existing UTF source
cursor, RTRIM/NOCASE key comparison, bytewise GLOB matcher, and numeric affinity
coercion.
