## real-upstream-corpus-trigger-fkey-dynamic-triggerG-expression-view

Base accepted HEAD: `57904efd88f87abfad6d70c753ea59660958850e`.

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test`.

Ported sections:

- `triggerG-300..310`: trigger subprogram expression errors propagate for an oversized hex literal before side effects are reported.
- `triggerG-400..410`: `INSTEAD OF DELETE` view triggers bind `OLD.a` from the view row and preserve the view's underlying SELECT row.

Focused PHP coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerGExpressionViewTest.php`
- 80 dynamic seeds plus source-citation and malformed-input guards.
- Focused result: `1 test files, 1205 assertions, 0 failures`.

Non-overlap:

- Existing triggerG coverage already covered recursive trigger `OP_Once` reset behavior from `triggerG-100..200`.
- This slice covers the later expression-error and view-trigger sections only.

Dependency closure:

- No new support component is needed. The slice reuses the existing `SQLiteDynamicTriggerForeignKeyPlan` trigger/FK corpus helper.
