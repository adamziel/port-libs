# Independent Audit - 2026-05-23T05:39:33Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent
Git history through `49e2068b8217`, current process/test state, current
dirty-tree status, and PHP shell-out usage in `lanes/`, `tools`, and `scripts`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. I treated bridge/generated/oracle tooling as non-progress unless it was
explicitly temporary fixture or oracle evidence.

## Findings

1. **High - the latest root harness is green, but the result is not an accepted baseline.**
   - Paths: `tools/run-tests.php`, `tools/TestRunner.php`, current dirty
     `lanes/*/tests/*Test.php`, current dirty `lanes/*/src/*`, and
     `progress.md:248`-`256`.
   - Requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52` require small passing slices, meaningful
     parity, verified integration, honest repo-wide test recording, and visible
     passing PHP tests for every lane.
   - Evidence: this audit observed three consecutive root-harness samples while
     agents were active. A full `php tools/run-tests.php` first exited `1` with
     `183` test files, `19240` assertions, and `2` failures. A filtered rerun
     then reported the visible failure in
     `lanes/rclone/tests/DeletePlanningTest.php` (`purge falls back when direct
     provider returns cant purge`) and ended with `183` files, `19242`
     assertions, and `1` failure. The latest captured run executed
     `php tools/run-tests.php` again and ended with `PHP_EXIT=0`, `183` test
     files, `19250` assertions, and `0` failures.
   - Audit judgment: the latest sample is better than the start-of-audit
     baseline, but assertion totals changed and `HEAD` advanced from
     `37d92ca3` to `49e2068b8217` during the audit. Treat the green run as
     diagnostic evidence only until the supervisor freezes writers and captures
     a quiesced run from one accepted snapshot.

2. **High - the supervisor cap/status surface is contradicted by active agents.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `progress.md:248`-`256`,
     `.tmux-team/tmp/*`, `.tmux-team/prompts/*`, and `.tmux-team/logs/*`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`, `goal.md:48`, and
     `goal.md:49` require a practical concurrency cap, current owner/session
     state, deliberate integration, and periodic repo-wide tests.
   - Evidence: the roadmap still says the current launch target is two
     implementation lanes plus one auditor, and the Active Lanes table still
     reports stopped sessions. Current process sampling instead showed active
     `run-tmux-agent.sh` sessions for most lanes plus auditor/integrator/root
     triage and new capacity/focused-lock agents starting during the audit.
   - Audit judgment: the status surface is not trustworthy enough to accept
     dashboard or lane-status stamps without first reconciling active writers.

3. **High - `porting.html` and `porting-summary.json` remain stale and still flatten required fields.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, and `porting-summary.json:11`-`212`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with separate benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit columns.
   - Evidence: the dashboard advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while current
     `HEAD` is `49e2068b8217`. Current manifest mapped counts disagree with
     the page: difftastic `174` vs `160`, Dolt `262` vs `242`, esbuild `168`
     vs `164`, Gitoxide `1439` vs `1432`, libsqlite `163` vs `149`,
     LightningCSS `811` vs `773`, markerPDF `166` vs `159`, Pandoc `473` vs
     `426`, rclone `312` vs `291`, Readability `1085` vs `1031`, and
     Syncthing `246` vs `235`.
   - Evidence: the table still combines denominator under "Benchmark" and PHP
     pass/fail under "Mapped", rather than showing the explicit columns required
     by `goal.md:45`.
   - Audit judgment: the dashboard is an old publication snapshot, not the
     source of truth for current lane state.

4. **High - the tree is a broad dirty aggregate, not reviewable accepted slices.**
   - Paths: representative dirty paths include `tools/run-tests.php`,
     `porting.html`, `porting-summary.json`, `lanes/rclone/src/SyncPlan.php`,
     `lanes/readability/src/ArticleExtractor.php`,
     `lanes/syncthing/src/BepWire.php`, and many
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Requirement at risk: `goal.md:29`, `goal.md:36`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, correct integration,
     progress cleanup, and repo-wide verification.
   - Evidence: after the green root sample, tracked-only status still reported
     `102` changed files, and `git diff --shortstat` reported
     `102 files changed, 16491 insertions(+), 694 deletions(-)`.
   - Audit judgment: the correct intervention is to stop accepting new lane
     edits until existing dirty batches are integrated or rejected one lane at a
     time from a quiesced tree.

5. **Medium - manifest denominator units remain mixed and percentages are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`15`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators, explicit slices for huge
     suites, and dashboard fields that distinguish denominator, mapped tests,
     and PHP pass/fail.
   - Evidence: markerPDF reports `78` tracked repository paths while mapping
     `166` behaviors/supplied boundaries; Quadrable reports `55` tracked paths
     while also citing `34` scenarios and assertion counts; Difftastic counts
     inspected behavior artifacts; Dolt mixes executable files, BATS cases, Go
     functions, and focused references; Pandoc stores runner status only in a
     warning string rather than a typed `runnerStatus` object.
   - Audit judgment: normalize manifests into typed units before using
     percentages for portfolio decisions.

6. **Medium - bounded/static evidence is still too easy to read as full upstream parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`-`20`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:209`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:215`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:305`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:305`.
   - Requirement at risk: `goal.md:35`-`40` require upstream tests as the source
     of truth, fixture parity, edge-case/error behavior, and explicit blockers
     for hard features.
   - Evidence: Gitoxide has a very high dashboard score but no full cargo
     workspace pass; Difftastic and Syncthing explicitly have no full upstream
     runner execution; markerPDF's full runner remains blocked by the ML/PDF
     stack; Pandoc's upstream runner remains unavailable; rclone and Dolt have
     useful bounded evidence but not full provider/mount/full-BATS/full-Go
     parity.
   - Audit judgment: make `full-runner`, `bounded-runner`, `static-inventory`,
     `oracle-fixture`, and `supplied-boundary` first-class status fields.

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

Exact latest captured result for this audit:

```text
exit status: 0
183 test files, 19250 assertions, 0 failures
```

Earlier same-audit samples were not stable: `183` files / `19240` assertions /
`2` failures, then `183` files / `19242` assertions / `1` failure with the
visible rclone failure noted above. The final green result was captured while
implementation agents were still active, so it should not be treated as an
accepted integration checkpoint.

## Recommended Next Intervention

Freeze active writers and duplicate/focused root-test processes, then enforce
the documented cap before accepting more work. Capture one quiesced
`php tools/run-tests.php` run to a log from a single accepted snapshot, accept
or reject dirty batches one lane at a time, and only then regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same snapshot.
