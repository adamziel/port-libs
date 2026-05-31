# Real Upstream Expression Affinity Dynamic CAST Lossiness

Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T034849Z-0`

Base accepted HEAD: `1d87a6fc2cf9c016da25d4e727af365cff780442`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `e_expr-27.1.1` and `e_expr-27.1.2`

Behavior covered:

- Typed-column insert affinity for `TEXT`, `REAL`, `INTEGER`, `NUMERIC`, and `BLOB` columns preserves or converts values according to column-affinity rules.
- `CAST(... AS TEXT|REAL|INTEGER|NUMERIC|BLOB)` always performs the requested conversion even when it is lossy or irreversible.
- Dynamic inputs cover `NULL`, integers, reals, numeric-prefix text, exponent text, overflow boundary text, empty/sign/dot-only text, nonnumeric text, and ASCII BLOB values.
- A `sqlite3` oracle is generated at test load time and compared against both bounded PHP insert-affinity helpers and parser-level `SQLiteSelectSql` CAST execution.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCastLossy20260531T034849ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCastLossy20260531T034849ZTest.php`
  - `1 test files, 869 assertions, 0 failures`

Expected dashboard movement:

- PASS-line growth only: `+211` focused TestRunner PASS cases.
- Mapped denominator movement: none; mapped upstream inventory is already complete at `1589 / 1589`.

Dependency closure:

- No new support component is needed. This slice reuses `SQLiteRealExpressionAffinityCorpusPlan`, `SQLiteSelectSql`, `SQLiteBlobValue`, and the local `sqlite3` oracle.

Exclusions:

- Invalid-byte BLOB-to-TEXT `quote()` parity is excluded after the oracle showed a distinct quoting mismatch for `X'00CEFF'`. That is a separate malformed-text behavior blocker, not part of this ASCII e_expr-27 CAST-lossiness batch.
- No domain-specific APIs, classes, methods, examples, or scenarios were added.
