# real-upstream-corpus-json1-jsonb-dynamic-20260601T181726Z-0

## Source truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported sections:
  - `json101-3.5`: `json_tree(json_set('{}','$.x',123,'$.x',456))` walks the final duplicate-path edit only.
  - `json101-3.5b`: the same final-edit tree walk for `jsonb_set()`.

## Patch

- Added `SQLiteRealUpstreamJson101DuplicateSetTreeDynamic20260601T181726ZTest.php`.
- The batch has 1000 dynamic parser-level cases over `SQLiteSelectSql`, each comparing `json_tree(json_set(...))` and `json_tree(jsonb_set(...))` output plus extracted final values.
- This intentionally does not repeat the existing broad json101 constructor/edit SELECT SQL batch, quoted-path coverage, JSON table cursor/source/hidden/visible constraints, JSON aggregate/window coverage, or JSON mutation/path/operator batches. It owns the narrower upstream duplicate-path tree walk from `json101-3.5` and `json101-3.5b`.

## Evidence

- Red-first probe on the accepted base showed the behavior already works through `SQLiteSelectSql`; no source change was needed.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101DuplicateSetTreeDynamic20260601T181726ZTest.php`
  - Result: `1 test files, 9007 assertions, 0 failures`
  - PASS-line delta for this isolated handoff: `+1002`
- Syntax check: `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101DuplicateSetTreeDynamic20260601T181726ZTest.php`
  - Result: no syntax errors.
- Lane status JSON check: `php -r '$data=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`
- Whitespace check: `git diff --check -- lanes/libsqlite`
  - Result: passed.
- API guard: `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is not present in this accepted worktree.

## Dependency Closure

No new support component is needed. The test reuses `SQLiteSelectSql`, existing JSON scalar dispatch for `json_set()`/`jsonb_set()`, and existing `json_tree()` table-valued execution.
