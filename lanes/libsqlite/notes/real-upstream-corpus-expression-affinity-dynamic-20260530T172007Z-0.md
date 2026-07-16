# Real Upstream Corpus Expression Affinity Dynamic

Base accepted HEAD: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`.

This slice extends `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
with real SQLite upstream `test/numcast.test` coverage:

- `numcast-utf8.1.1` through `numcast-utf8.8.2`
- `numcast-utf16le.1.1` through `numcast-utf16le.8.2`
- `numcast-utf16be.1.1` through `numcast-utf16be.8.2`

Focused delta:

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
  passed `1 test files, 4981 assertions, 0 failures`.
- After: the same command passed `1 test files, 5557 assertions, 0 failures`.
- New focused growth: `+48` PASS lines and `+576` assertions.

Behavior covered:

- REAL casts preserve numeric prefixes, exponent notation, signs, and leading
  ASCII whitespace from upstream `numcast.test`.
- INTEGER casts truncate numeric prefixes toward zero and ignore real/exponent
  tails where SQLite does.
- Non-ASCII leading text after whitespace, matching upstream `{ Ġ 321.5}`,
  casts to `0.0` / `0`.

No new support component is needed. The existing
`SQLiteRealExpressionAffinityCorpusPlan` CAST behavior is reused.
