# Workflow Queue Refresh - 20260524T223617Z

## Scope

Worker: capacity-queue freshness for `/home/claude/port-libs`.

Touched coordination files only:

- `scripts/run-capacity-queue-feeder.sh`
- `scripts/run-capacity-executor-queue.sh`
- `audits/workflow-queue-refresh-20260524T223617Z.md`

No `lanes/**` files were edited or inspected for implementation. No live-service provider tests were run. Root `php tools/run-tests.php` was not run.

## Existing Queue State

- The queue contained many historical `capacity-feed-clean-*` and `capacity-feed-dirty-*` PHP rows keyed to old accepted commit fragments.
- Ready rows near the tail were still anchored to older heads such as `748e8ca928fd`, `c3da13ff8300`, `ce8f34918d64`, and `28e785481486`.
- During verification, `HEAD` advanced while work was in progress, confirming the queue needs to treat source head as a freshness boundary instead of relying only on scope names.

## Behavior Added

- The feeder now records a queue source header:
  - `# capacity-source head=<12-char-head> dirty_key=<dirty-key-or-clean> stable_dirty_rows=<count>`
- The feeder computes the current source key before indexing existing queue IDs/scopes.
- Existing stale PHP rows no longer suppress current-head PHP row generation:
  - `php-clean:<head>:...` rows only block clean rows for the same current head.
  - `php-dirty:<head>:<dirty-key>:...` and `dirty-root:<head>:<dirty-key>` rows only block dirty rows for the same current head and dirty key.
- Historical queue rows and audits are preserved. The feeder does not delete or rewrite useful evidence.
- The executor reads the current queue source header after the feeder runs.
- Before launching a ready PHP row, the executor blocks stale ready PHP rows with:
  - `blocked:stale source snapshot current_head=<head> current_dirty_key=<dirty-key>`
- Running rows are preserved. The stale-row check applies only to ready rows at launch time.
- Because stale ready PHP rows are blocked before launch, current-head rows later in the queue are no longer starved by old ready root/focused rows.

## Verification

- `bash -n scripts/run-capacity-queue-feeder.sh scripts/run-capacity-executor-queue.sh scripts/run-capacity-controller-loop.sh`: passed.
- Feeder dry-run against a copied queue with `--lock-held --dry-run --max-rows 4`: passed.
  - It reported the new source header.
  - It would append current-head clean rows instead of treating old clean scopes as coverage.
- Executor dry-run could not run because an active capacity executor queue process held `.tmux-team/tmp/port-capacity-executor-queue.lock`.
- No root PHP test was run.

## Remaining Manual Step

Let the active controller/executor complete its current locked pass, or run the executor once after the lock clears. The next non-dry feeder/executor pass will write the `# capacity-source ...` header into the live queue and begin blocking stale ready PHP rows conservatively.
