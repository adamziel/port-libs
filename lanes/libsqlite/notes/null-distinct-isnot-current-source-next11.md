# NULL DISTINCT / IS NOT Current Source Next11

## Behavior

- Resolves unique unqualified column references against qualified current-source
  joined rows, so predicates such as `autoload IS NOT DISTINCT FROM meta_value`
  work after `wp_options AS w JOIN option_meta AS m`.
- Keeps ambiguous unqualified columns rejected, for example `option_id` when
  both joined sources expose `w.option_id` and `m.option_id`.
- Covers `IS`, `IS NOT`, `IS DISTINCT FROM`, `IS NOT DISTINCT FROM`, row-value
  operands, scalar expressions, grouped filters, JOIN predicates, and left-join
  NULL-extension cases.

## Focused Evidence

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNullDistinctIsNotCurrentSourceTest.php
```

Result:

```text
1 test files, 43 assertions, 0 failures
43 PASS lines
```

Expected dashboard movement: `phpPass` increases by the verified +43 focused
PASS lines from `3796` to `3839`. Mapped upstream denominator is unchanged.

## Non-Overlap

This slice avoids accepted `SQLiteSelectDistinctFromPredicateTest.php` direct
null-safe operator coverage, SELECT subqueries, expression `ORDER BY`, grouped
SELECT SQL text, JSON table source/cursor/constraint work, Unicode GLOB, WAL,
VFS writer/lock/sync, B-tree page-move/root-collapse/overflow, and
release-runner evidence. It targets only current-source column resolution for
joined qualified rows used by null-safe predicates.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
SELECT SQL, predicate, projection, expression, JOIN, and grouped aggregate
paths.
