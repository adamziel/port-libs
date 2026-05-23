# Independent Audit - 2026-05-23T14:36:35Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
all 12 `lanes/*/lane-status.json` files, recent Git history, dirty tree
state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `f39baaa8db7b` (`Refresh independent audit
status`). Recent history reviewed includes `f39baaa8`, `d7602dae`,
`0f754e8b`, `8bfcb551`, `ac948ba0`, `4ba8b4f4`, `37bbfa36`, `005fd686`,
`79a7e66a`, `9dfec34f`, `1c06a555`, `85dcd312`, `66798317`, `be7cf14f`,
`c1f67cda`, `d52cc007`, `24260634`, `51867989`, `b75226d1`, `30be5e3c`,
`90d1fa3b`, `81419ac3`, `69405063`, `0f1444c1`, and `efa4e0c2`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:12`-`13`, active process state.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped active
     work, current owner/session tracking, small committed slices, supervisor
     verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` documents a target of two implementation lanes
     plus one auditor, while `progress.md:31`-`42` reports every lane session
     as `stopped`.
   - Evidence: active process sampling found live `run-tmux-agent.sh` sessions
     for Dolt runner, Readability, libsqlite, Dolt, esbuild, rclone, Gitoxide,
     Difftastic, LightningCSS, markerPDF, Quadrable, integrator, auditor, and
     multiple capacity PHP agents, plus `run-team-watchdog.sh`,
     `run-evaluator-loop.sh`, `run-capacity-controller-loop.sh`, and
     `run-dashboard-updater-loop.sh`.
   - Evidence: dirty-tree samples reported `1821` default
     `git status --short` rows, `184` tracked changed files, and
     `184 files changed, 66791 insertions(+), 5238 deletions(-)`.
   - Audit judgment: no aggregate baseline should be accepted until writers,
     status publishers, and capacity runners are frozen and one snapshot is
     accepted or rejected lane by lane.

2. **High - the public dashboard remains stale and does not satisfy the
   dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, current
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard fields for benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: `porting.html:30`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC`, source commit
     `bda83c6b93d4`, and average progress `68.8%`, while sampled `HEAD` is
     `f39baaa8db7b`.
   - Evidence: dashboard rows disagree with current lane files. Rclone is
     published as `327` denominator, `291` mapped, and `291 pass`
     (`porting.html:63`), while current files report `1601` total and `497`
     mapped (`lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`) and `494`
     PHP pass (`lanes/rclone/lane-status.json:6`). markerPDF is published as
     `78` denominator, `159` mapped, and `264 pass` (`porting.html:60`),
     while current files report `281` total and `229` mapped
     (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`) and `343` PHP
     pass (`lanes/markerpdf/lane-status.json:6`). Gitoxide is published as
     `1432 / 2877` mapped and `2646 pass` (`porting.html:57`), while current
     files report `1991` mapped
     (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`) and `3717` PHP pass
     (`lanes/gitoxide/lane-status.json:6`).
   - Evidence: `porting.html:41`-`50` still collapses required dashboard
     fields into combined `Benchmark` and `Mapped` cells instead of exposing
     upstream denominator, mapped tests, and PHP pass/fail as first-class
     columns.

3. **High - lane status claims high completion without accepted commits or a
   comparable root baseline.**
   - Paths: `progress.md:31`-`42`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:12`-`13`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require committed passing
     slices, current progress tracking, and supervisor verification.
   - Evidence: `progress.md:31`-`42` still shows old estimates from `5%` to
     `66%`, while current lane statuses claim Difftastic `92%`, Dolt `94%`,
     esbuild `74%`, Gitoxide `98%`, libsqlite `98%`, LightningCSS `85%`,
     markerPDF `93%`, Pandoc `96%`, Quadrable `99%`, rclone `98%`,
     Readability `95%`, and Syncthing `98%`.
   - Evidence: every sampled `latestCommit` field is pending, uncommitted,
     prose, or a stale dirty-batch handoff instead of a clean accepted commit.
     Examples include `pending in shared dirty worktree`,
     `not committed`, `uncommitted port-esbuild lane batch`,
     `pending - current Gitoxide external filter-driver batch`,
     `uncommitted lane-scoped changes`, `HEAD 0f754e8b at status update`,
     and `pending lane-local changes`.
   - Evidence: recent Git history is dominated by audit-only commits:
     the latest eight commits are all `Refresh independent audit status`,
     each changing only `audits/latest.md` and `progress.md`. That is not an
     implementation integration stream despite lane statuses reporting
     74%-99% completion.

4. **High - manifest/status count schemas remain non-normalized and some files
   contradict themselves.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:580`-`591`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1068`,
     `lanes/*/lane-status.json:5`-`7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require a real upstream denominator,
     mapped upstream tests, PHP passing/failing counts, and comparable
     dashboard fields.
   - Evidence: several `benchmarkDenominator.total` values are prose strings
     instead of numeric denominators: Difftastic, Dolt, esbuild, Pandoc, and
     Quadrable. Other lanes use numbers, so portfolio math mixes schemas.
   - Evidence: mapped and pass units are different measures but are reported
     side by side as if comparable. Current examples: Gitoxide reports `1991`
     mapped units but `3717` PHP assertions/tests in status; LightningCSS
     reports `1273` mapped units but `1439` PHP pass; Readability reports
     `1729` mapped units but `157` PHP tests; Pandoc reports `693` mapped
     units but `223` PHP tests.
   - Evidence: some manifest warnings are stale relative to their own mapped
     fields. LightningCSS maps `1273` at
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, while its warning
     still says `1,262` focused checks and `1,396` assertions at line `1645`.
     Syncthing maps `368` at
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, while its warning
     still says `359` focused lane tests at line `1068`. Readability maps
     `1729` at `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, while its
     warning says native PHP maps only `157` local behavior tests at lines
     `580` and `591`.

5. **High - current root-test evidence is non-comparable across lanes.**
   - Paths: `lanes/esbuild/lane-status.json:12`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:49` require precise
     blockers and honest repo-wide test/static-check recording.
   - Evidence: some lane statuses claim a root harness pass after waiting for
     `.upstream-cache/run-tests.lock` (Readability and Syncthing), some claim
     aggregate root is green (esbuild), and several say root was intentionally
     skipped or pending due to broad upstream runners or duplicate root PIDs.
   - Evidence: the required exact duplicate-root gate returned no rows during
     this audit sample, but the tree was not stable enough for a root run:
     active lane/capacity/status loops persisted and the dirty tree remained
     broad. A new no-argument root run from this state would be another
     non-comparable anecdote, not an accepted aggregate baseline.

6. **Medium - hard upstream-runner and hard-feature gaps are softened by
   92%-99% status claims.**
   - Paths: `lanes/gitoxide/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:4`,
     `lanes/quadrable/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require meaningful fixture parity, upstream tests as source
     of truth, and hard gaps marked as blockers or future slices.
   - Evidence: Gitoxide is `98%` while full cargo workspace parity, external
     filter process execution, sparse-index writing, broader pack/ref/object
     integration, and full merge semantics remain open. markerPDF is `93%`
     while full Python benchmarks, model-heavy conversion, OCR/Pandoc/Nougat
     tooling, multiprocessing, and GitHub Actions execution remain unexecuted.
     Pandoc is `96%` while the Haskell Tasty runner is unexecuted. rclone and
     Syncthing are `98%` while live-provider/mount/full `go test ./...`
     coverage remains open. libsqlite is `98%` while checkpoint mutation,
     rollback journal edge cases, general SQL execution, triggers/upserts,
     broader writes, and broader corruption validation remain open. Quadrable
     is `99%` while the status still leaves full 500-trial sync-fuzzer probes
     and the 100,000-record sync benchmark outside the fast suite.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed gate result during this audit:

```text
<no rows>
```

Post-commit handoff gate evidence:

```text
1507426 php tools/run-tests.php
```

Owner evidence for the active no-argument root harness:

```text
1507426 claude 1459380 00:18 Rs php tools/run-tests.php
```

No root run was started by this audit because the stability gate failed, and
the later handoff gate showed duplicate-root prevention was active again.
Active process evidence included:

```text
1021826 claude ... run-tmux-agent.sh port-dolt-runner ...
1422186 claude ... run-tmux-agent.sh port-readability ...
1434849 claude ... run-tmux-agent.sh port-libsqlite ...
1435444 claude ... run-tmux-agent.sh port-dolt ...
1435500 claude ... run-tmux-agent.sh port-esbuild ...
1443523 claude ... run-tmux-agent.sh port-rclone ...
1459360 claude ... run-tmux-agent.sh port-gitoxide ...
1462854 claude ... run-tmux-agent.sh port-difftastic ...
1485328 claude ... run-tmux-agent.sh port-lightningcss ...
1491919 claude ... run-tmux-agent.sh port-markerpdf ...
1496000 claude ... port-capacity-php-fixed-0f754e8b-gitoxide ...
1496010 claude ... port-capacity-php-fixed-0f754e8b-dolt ...
1496020 claude ... port-capacity-php-fixed-0f754e8b-quadrable ...
1496039 claude ... port-capacity-php-fixed-0f754e8b-markerpdf ...
1496043 claude ... port-capacity-php-fixed-0f754e8b-rclone ...
1496074 claude ... port-capacity-php-fixed-0f754e8b-docweb ...
1496104 claude ... port-capacity-php-fixed-0f754e8b-lightningcss ...
1499000 claude ... run-tmux-agent.sh port-auditor ...
1500630 claude ... run-tmux-agent.sh port-quadrable ...
1500652 claude ... run-tmux-agent.sh port-integrator ...
2347911 claude ... run-team-watchdog.sh
2424048 claude ... run-evaluator-loop.sh
2452997 claude ... run-capacity-controller-loop.sh
2479222 claude ... run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
rg -n '"total"|"mapped"|"phpBehaviorTests"|"warning"' lanes/*/UPSTREAM_TEST_MANIFEST.json
rg -n '"estimatedProgress"|"phpPass"|"phpFail"|"latestCommit"|"blocker"' lanes/*/lane-status.json
git log --oneline --decorate --no-abbrev-commit -n 25
git show --stat --oneline --decorate -n 8
git status --short | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,stat,args | rg 'php tools/run-tests\.php|run-tmux-agent|run-capacity-controller-loop|run-dashboard-updater-loop|run-evaluator-loop|run-team-watchdog|testrunner\.tcl|bats |go test|cargo test|npm test|run-php'
```

Results: all lane upstream manifests, all lane status files, and
`porting-summary.json` parsed as valid JSON. All 12 lane manifests were found.
Latest samples reported `1821` default status rows, `184` tracked changed
files, and `184 files changed, 66791 insertions(+), 5238 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and capacity PHP loops first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches
one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, scenario, and commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot, rerun the exact duplicate-root gate, and capture
one quiesced no-argument `php tools/run-tests.php` result.
