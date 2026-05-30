# PRAGMA Foreign Key Check Corpus Next6

This slice adds a bounded upstream-style `PRAGMA foreign_key_check` corpus that
is disjoint from the accepted deferred cascade/action tests. The new
`SQLitePragmaForeignKeyCheck` reports SQLite-shaped violation rows:

- `table`, `rowid`, `parent`, and `fkid` columns.
- Single-column and composite foreign-key checks.
- SQL NULL child-key short-circuiting.
- child-table filtering via `PRAGMA foreign_key_check(table)`.
- schema-qualified and quoted pragma target parsing.
- WITHOUT ROWID child tables returning a `null` rowid.
- malformed metadata, rows, rowid aliases, and unsupported pragma guards.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyCheckCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures

php lanes/libsqlite/examples/application-pragma-foreign-key-check.php
{
    "pragma": "foreign_key_check",
    "violations": 2,
    "rows": [
        {
            "table": "wp_postmeta",
            "rowid": 102,
            "parent": "wp_posts",
            "fkid": 0
        },
        {
            "table": "wp_comments",
            "rowid": 202,
            "parent": "wp_posts",
            "fkid": 1
        }
    ]
}
```

Dashboard delta: `phpPass` increases by the verified 55 PASS/assertion lines,
from `2017` to `2072`. No new mapped upstream denominator unit is claimed.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local row-array executor style and native PHP test runner; it
does not require ext/sqlite, a live service, or shared dependency activation.

## Deep Next12 Addendum

This slice extends the same bounded `PRAGMA foreign_key_check` helper with
SQLite parent-key comparison behavior that was not covered by the previous
corpus. Foreign-key parent equality now applies optional parent column
affinity and collation metadata before deciding whether a child row is a
violation:

- `INTEGER`, `NUMERIC`, `REAL`, `TEXT`, and `BLOB`/none affinity handling.
- `BINARY`, `NOCASE`, and `RTRIM` parent collation handling.
- integer/real numeric equality after affinity conversion.
- composite foreign keys mixing numeric affinity and text collation.
- NULL child-key short-circuiting before affinity conversion.
- malformed affinity/collation guards.
- Application copied `wp_options` expected/shadow option-name smoke coverage
  where `NOCASE` accepts `SITEURL -> siteurl` while reporting a missing plugin
  option reference.

Focused evidence:

```text
baseline:
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyCheckCorpusTest.php
1 test files, 55 assertions, 0 failures

after:
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyCheckCorpusTest.php
1 test files, 81 assertions, 0 failures
```

Dashboard delta: `phpPass` increases by the verified `+26` PASS/assertion
lines, from `3796` to `3822`. No new mapped upstream denominator unit is
claimed.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local row-array executor style and native PHP test runner; it
does not require ext/sqlite, a live service, or shared dependency activation.
