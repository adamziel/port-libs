# SELECT Correlated Derived Current Next25

Status delta: added parser-level `SQLiteSelectSql` support for correlated
subqueries whose `FROM` source is a derived table. The planner now records the
base source alias and correlated subquery execution expands each current outer
row with inferred table-qualified column names before filtering. Derived table
materialization inside a correlated subquery receives the current outer row, so
inner predicates such as `import_option_meta.option_id = wp_options.option_id`
can be evaluated before the derived rows are yielded to the parent subquery.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCorrelatedDerivedCurrentNext25Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 54 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-correlated-derived-current-next25.php
[
    {
        "option_name": "siteurl"
    },
    {
        "option_name": "home"
    },
    {
        "option_name": "blogname"
    }
]
```

Dashboard delta: `lane-status.json` `phpPass` moves from `8739` to `8793`
for the 54 newly verified focused PASS lines. The upstream mapped denominator
is unchanged at `461 / 1589` because this is a fresh focused PHP behavior
cluster, not a newly mapped upstream inventory unit.

Non-overlap: this does not repeat accepted batch23 standalone derived-table
materialization for Application import staging, accepted SELECT EXISTS/IN
subquery execution, JSON table cursor/source/constraint work, expression
ORDER BY, grouped SELECT text, VFS/WAL/B-tree storage clusters, or suite
countability evidence. The narrower behavior is current-row visibility and
alias-qualified derived columns while a correlated subquery yields its derived
source rows.

Dependency closure: no new support component is needed. The patch reuses the
existing native PHP `SQLiteSelectSql`, `SQLiteSelectQuery`,
`SQLiteSelectPredicate`, and `SQLiteSelectExpression` components.
