# Independent Audit - 2026-05-23T05:45:21Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent Git
history sampled through `b75cdedf`, current dirty-tree status, process/test state, and
PHP shell-out usage in `lanes/`, `tools`, and `scripts`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. I treated bridge/generated/oracle tooling as non-progress unless it was
explicitly temporary fixture or oracle evidence.

## Findings

1. **High - the required root harness is green, but it is still not an accepted integration baseline.**
   - Paths: `tools/run-tests.php`, `audits/run-tests-focused-lock-20260523T0540Z.md`,
     `lanes/*/tests/*Test.php`, `lanes/*/src/*`, and `progress.md:249`-`257`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, `goal.md:49`, and
     `goal.md:52` require small passing slices, verified integration, honest
     repo-wide test recording, and visible passing PHP tests for every lane.
   - Evidence: this audit ran the required command exactly as requested:
     `php tools/run-tests.php` exited `0` with `184` test files, `19365`
     assertions, and `0` failures.
   - Audit judgment: the result is useful evidence, but not an accepted
     baseline. `HEAD` moved during the audit from the prior audit state
     `91b9704a` through `006c18a5`, `569f1f89`, `297a8415`, `228941de`,
     `d54461d5`, `0e312c81`, and audit-only follow-up `b75cdedf` while this
     audit was being written. `006c18a5` changed the root test runner itself,
     and `297a8415` mixed the earlier audit/progress update with libsqlite lane
     files. Capture the next baseline only from a quiesced tree.

2. **High - active agents still exceed the documented cap and contradict the status surface.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`, `scripts/run-team-watchdog.sh`,
     and `scripts/run-evaluator-loop.sh`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`, `goal.md:48`, and
     `goal.md:49` require a practical concurrency cap, current owner/session
     state, deliberate integration, and periodic repo-wide verification.
   - Evidence: `progress.md` still says the launch target is two
     implementation lanes plus one auditor, and the Active Lanes table still
     reports stopped sessions. Process sampling during this audit showed at
     least 20 `scripts/run-tmux-agent.sh` sessions; the final sample still had
     23 matching active agent/test processes, including lane workers,
     auditor/integrator/capacity agents, and a duplicate root test.
   - Audit judgment: the supervisor surface cannot be used to accept work
     until active writers are frozen or explicitly reconciled.

3. **High - the working tree is a broad dirty aggregate, not reviewable accepted slices.**
   - Paths: representative dirty paths include `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `lanes/difftastic/src/*`,
     `lanes/dolt/src/*`, `lanes/gitoxide/src/*`, `lanes/libsqlite/src/*`,
     `lanes/lightningcss/src/*`, `lanes/markerpdf/src/*`,
     `lanes/pandoc/src/*`, `lanes/quadrable/src/*`, `lanes/rclone/src/*`,
     `lanes/readability/src/*`, `lanes/syncthing/src/*`, `porting.html`,
     and `porting-summary.json`.
   - Requirement at risk: `goal.md:29`, `goal.md:36`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, correct integration,
     cleanup of accidental unrelated changes, and repo-wide verification.
   - Evidence: final status sampling reported `699` `git status --short`
     entries, `95` tracked changed files, and `95 files changed, 17398
     insertions(+), 540 deletions(-)` in `git diff --shortstat`.
   - Audit judgment: even with a green root harness, this is not a reviewable
     integration checkpoint. Accept or reject dirty lane batches one lane at a
     time before publishing dashboard/status.

4. **High - `porting.html` and `porting-summary.json` are stale and still flatten required columns.**
   - Paths: `porting.html:32`-`65`, `porting-summary.json:1`-`212`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while the
     latest sampled `HEAD` is `b75cdedf`. The table combines benchmark source and denominator
     under `Benchmark`, and combines PHP pass/fail with mapped count under
     `Mapped`, instead of using the separate columns required by `goal.md:45`.
   - Evidence: current manifest mapped counts disagree with the page:
     difftastic `176` vs `160`, Dolt `271` vs `242`, esbuild `169` vs `164`,
     Gitoxide `1440` vs `1432`, libsqlite `165` vs `149`, LightningCSS `816`
     vs `773`, markerPDF `166` vs `159`, Pandoc `481` vs `426`, rclone `316`
     vs `291`, Readability `1115` vs `1031`, and Syncthing `250` vs `235`.
   - Audit judgment: the dashboard is an old publication snapshot, not the
     source of truth for current lane state.

5. **Medium - manifest denominator and runner evidence schemas remain mixed.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require a real upstream denominator, explicit slices for
     huge suites, and dashboard fields that distinguish denominator, mapped
     tests, and PHP pass/fail.
   - Evidence: denominator units mix repository paths, test functions,
     fixtures, BATS cases, inspected behavior artifacts, supplied-boundary
     examples, benchmark excerpts, and assertion-like counts. Runner status is
     also inconsistently shaped: some manifests use typed objects, while
     Gitoxide, markerPDF, and Quadrable still use long prose/string fields.
   - Audit judgment: normalize manifest units and runner status before using
     percentages or average progress for portfolio decisions.

6. **Medium - bounded/static evidence is still easy to read as full upstream parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require upstream tests as source of truth, meaningful fixture
     parity, hard-feature blockers, and no credit for non-native/generated or
     bridge-style progress.
   - Evidence: Gitoxide remains high-scoring without full Cargo workspace
     parity; Difftastic and Pandoc are still static or no full upstream runner;
     markerPDF still cannot run the heavy benchmark stack; rclone and Dolt have
     useful bounded evidence but not full provider/mount/full-BATS/full-Go
     parity; Syncthing still lacks full `go test ./...` parity.
   - Audit judgment: make `full-runner`, `bounded-runner`, `static-inventory`,
     `oracle-fixture`, and `supplied-boundary` explicit status fields so
     bounded evidence cannot be mistaken for full upstream acceptance.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only PHP shell-out match is dashboard
coordination tooling.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Exact result for this audit:

```text
exit status: 0
184 test files, 19365 assertions, 0 failures
```

This was captured against a moving dirty tree. Treat it as diagnostic evidence
only until the supervisor freezes writers and reruns the root harness from a
single accepted snapshot.

## Recent Git History

Recent commits reviewed:

```text
b75cdedf Refresh independent audit status
0e312c81 Record esbuild verification counts
d54461d5 Stamp libsqlite lane commit
228941de Refresh independent audit status
297a8415 Update libsqlite lane status commit reference
569f1f89 Port esbuild private static assign semantics
006c18a5 Teach run-tests focused path selection
91b9704a Refresh independent audit status
49e2068b Port esbuild TS decorator and static field slices
37d92ca3 Refresh independent audit status
ee95f909 Port Syncthing receive-encrypted shortcut retry loop
845832f2 rclone: stamp Rmdirs lane status
8d6e1121 rclone: map standalone Rmdirs pruning
af8cb1a5 Port difftastic directory and check-only slices
```

## Recommended Next Intervention

Freeze active writers and duplicate/focused root-test processes, then enforce
the documented cap before accepting more lane work. Capture one quiesced
`php tools/run-tests.php` run from a single accepted snapshot, integrate or
reject dirty lane batches one lane at a time, normalize manifest/status schema
fields, and regenerate `progress.md`, `porting.html`, `porting-summary.json`,
and lane statuses from that same accepted snapshot.
