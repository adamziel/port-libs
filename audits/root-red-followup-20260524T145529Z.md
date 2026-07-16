# Root Red Follow-up - 20260524T145529Z

- Prior dirty-root run: `capacity-feed-dirty-root-d5ffc5970af0-cfa21eb31ef1`
- Prior gate sample: `2026-05-24T14:52:44Z`
- Prior HEAD: `6b6bbffe5d423ee3526867767f98d87c47bd3041`
- Prior command: `php tools/run-tests.php`
- Prior result: exit `1`; `379 test files, 60827 assertions, 2 failures`
- Prior audit read: `audits/capacity-feed-dirty-root-d5ffc5970af0-cfa21eb31ef1.md`
- Prior stdout read: `.upstream-cache/capacity-feed-dirty-root-d5ffc5970af0-cfa21eb31ef1/run/stdout.txt`

## Prior Failures

1. `lanes/esbuild/tests/MetafileAnalyzerTest.php`
   - Test: `keeps generated dataurl loader css output imports non-external`
   - Prior mismatch: expected the generated `data:image/svg+xml,<svg xmlns=...>` URL token as a non-external import; actual output omitted that first import and only kept the external data URL plus CDN URL.

2. `lanes/gitoxide/tests/GitIndexUntrackedCacheTest.php`
   - Test: `exact upstream UNTR bitmap fuzz artifact is ignored with diagnostics`
   - Prior mismatch: expected status `hash-valid`; actual status was `valid`.

The supervisor-provided focused rerun for these two files passed:

```text
php tools/run-tests.php lanes/esbuild/tests/MetafileAnalyzerTest.php lanes/gitoxide/tests/GitIndexUntrackedCacheTest.php
2 test files, 139 assertions, 0 failures
```

## Root Retry

- Preflight command: `pgrep -af '^php tools/run-tests\.php( |$)'`
- Preflight result: exit `1`; no exact root `php tools/run-tests.php` process was active.
- Retry command: `php tools/run-tests.php`
- Retry result: exit `0`; `379 test files, 60904 assertions, 0 failures`

The original two failures did not reproduce in the root retry. The earlier dirty-root red result is contradicted by the later passing focused rerun plus this later passing no-argument root retry.

## Integrator Action

No lane fix is assigned for the two named failures on this evidence. Treat this as a flaky or stale dirty-root red follow-up only; it does not claim acceptance for unrelated dirty worker changes in the worktree.
