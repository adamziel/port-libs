# Capacity Feeder Schema/Path Repair - 20260525T1412Z

## Scope

Tooling-only repair for capacity candidate feeder/executor intake. No lane implementation files were edited, no secrets were inspected, and no live-service/provider tests were run.

## Exact Skip Reasons Identified

- rclone Go exact rows from `.tmux-team/tmp/capacity-candidates/rclone-go-20260525T0830Z.tsv` were skipped because `scripts/run-capacity-queue-feeder.sh` only accepted TSV kind `go_test_local`; the current bounded rows use kind `rclone_go_exact`.
- Dolt BATS rows from `.tmux-team/tmp/capacity-candidates/dolt-bats-20260525T0830Z.tsv` were skipped because the feeder only accepted selector scratch paths shaped like `.upstream-cache/capacity-selector-dolt-bats-*/$scope_name`; the current marker rows use `.upstream-cache/capacity-dolt-bats-index-20260525T0830Z/<scope-suffix>`.

## Files Changed

- `scripts/run-capacity-queue-feeder.sh`
  - Accepts both legacy `go_test_local` and current `rclone_go_exact` rclone TSV kinds.
  - Builds distinct rclone exact queue ids/scopes from package + selector + expected-test hash, because the current TSV `scope_key` is shared by all rows in the candidate index.
  - Accepts current Dolt marker scratch paths under `.upstream-cache/capacity-dolt-bats-index-*`.
  - Accepts current Dolt source-only local-engine marker wording and marker-only candidate status.
  - Keeps `candidate_marker_only_not_enqueued_manual_review` excluded with a concrete bounded-selection safety reason.

## Artifacts Created

- `.tmux-team/tmp/capacity-feeder-schema-test.tsv`
  - Temporary deterministic queue fixture created with `--queue .tmux-team/tmp/capacity-feeder-schema-test.tsv`.
  - Contains 4 `rclone_go_exact` ready rows and 2 `dolt_bats_exact` ready rows.
  - Rows use owned prompt/log/audit/scratch paths under `.tmux-team/prompts/capacity-*.md`, `.tmux-team/logs/port-capacity-*.log`, `audits/capacity-*.md`, and `.upstream-cache/capacity-*`.

## Rows Accepted

- rclone Go exact:
  - `./fs` selector `^(TestSizeSuffixString|TestSizeSuffixByteUnit|TestSizeSuffixBitRateUnit)$`
  - `./fs` selector `^(TestSizeSuffixSet|TestSizeSuffixScan|TestSizeSuffixUnmarshalJSON)$`
  - `./fs` selector `^(TestCountSuffixString|TestCountSuffixUnit)$`
  - `./fs` selector `^(TestCutoffModeString|TestCutoffModeSet|TestCutoffModeUnmarshalJSON|TestTerminalColorModeString|TestTerminalColorModeSet|TestTerminalColorModeUnmarshalJSON)$`
- Dolt BATS exact:
  - `index-on-writes.bats index-on-writes-2.bats`
  - `commit_verification.bats`

## Rows Still Excluded

- `stash.bats` remains excluded because the candidate status is `candidate_marker_only_not_enqueued_manual_review`; the feeder now reports: `manual-review marker row is excluded until a bounded exact BATS selection is provided`. The concrete safety reason is that this marker row was explicitly produced as manual-review only and warns that whole-file conflict/merge-family coverage needs a bounded/manual selection before dispatch.

## Verification Commands

```text
bash -n scripts/run-capacity-queue-feeder.sh scripts/run-capacity-executor-queue.sh
```

Result: exit `0`.

```text
rm -f .tmux-team/tmp/capacity-feeder-schema-test.tsv && CAPACITY_EXECUTOR_QUEUE_FILE=.tmux-team/tmp/capacity-feeder-schema-test.tsv bash scripts/run-capacity-queue-feeder.sh --lock-held --queue .tmux-team/tmp/capacity-feeder-schema-test.tsv --max-rows 6 --clean-only
```

Result: exit `0`; queued 4 `rclone_go_exact` rows and 2 `dolt_bats_exact` rows; excluded `stash.bats` for manual-review bounded-selection safety.

```text
awk -F '\t' 'BEGIN{bad=0} /^ready/ { if ($3 !~ /^(rclone_go_exact|dolt_bats_exact)$/) bad=1; if ($5 !~ /^port-capacity-/) bad=1; if ($6 !~ /^\.tmux-team\/prompts\/capacity-.*\.md$/) bad=1; if ($7 !~ /^\.tmux-team\/logs\/port-capacity-.*\.log$/) bad=1; if ($8 !~ /^audits\/capacity-.*\.md$/) bad=1; if ($9 !~ /^\.upstream-cache\/capacity-/) bad=1; count[$3]++ } END { printf "rclone_go_exact=%d dolt_bats_exact=%d bad=%d\n", count["rclone_go_exact"]+0, count["dolt_bats_exact"]+0, bad; exit bad }' .tmux-team/tmp/capacity-feeder-schema-test.tsv
```

Result: `rclone_go_exact=4 dolt_bats_exact=2 bad=0`, exit `0`.

```text
bash scripts/run-capacity-executor-queue.sh --once --dry-run --no-feed --queue .tmux-team/tmp/capacity-feeder-schema-test.tsv --max-launch 6 --target 99 --audit-always
```

Result: exited without validation because the live persistent executor already held `.tmux-team/tmp/port-capacity-executor-queue.lock`. No launch was attempted.

## Remaining Blockers

- Full executor dry-run validation of the temporary queue could not run while the live persistent executor held the shared executor lock. The feeder-side deterministic queue rows validate against the executor path/kind conventions, and the persistent executor will read the repaired feeder on its next normal feed pass.
