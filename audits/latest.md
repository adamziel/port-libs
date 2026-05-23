# Independent Audit - 2026-05-23T14:31:47Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
all 12 `lanes/*/lane-status.json` files, recent Git history, dirty tree
state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `d7602dae56e3` (`Refresh independent audit
status`). Recent history reviewed includes `d7602dae`, `0f754e8b`,
`8bfcb551`, `ac948ba0`, `4ba8b4f4`, `37bbfa36`, `005fd686`, `79a7e66a`,
`9dfec34f`, `1c06a555`, `85dcd312`, `66798317`, `be7cf14f`, `c1f67cda`,
`d52cc007`, `24260634`, `51867989`, `b75226d1`, `30be5e3c`, `90d1fa3b`,
`81419ac3`, `69405063`, `0f1444c1`, `efa4e0c2`, and `f8bd46e4`.

## Findings

1. **Critical - the repository is not a stable integration checkpoint, and the
   coordination files are now describing a different system than the running
   one.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:12`-`13`, `porting.html:32`-`36`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped active work,
     current owner/session tracking, small committed slices, supervisor
     verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` still documents a target of two
     implementation lanes plus one auditor, while `progress.md:31`-`42`
     reports every lane session as `stopped`.
   - Evidence: active process sampling found live lane/watchdog/status loops
     for Dolt runner, Pandoc, Quadrable, Syncthing, Readability, libsqlite,
     Dolt, esbuild, rclone, Gitoxide, Difftastic, integrator, auditor,
     LightningCSS, plus `run-team-watchdog.sh`,
     `run-evaluator-loop.sh`, `run-capacity-controller-loop.sh`, and
     `run-dashboard-updater-loop.sh`.
   - Evidence: root PHP runners also started during this audit. The required
     duplicate-root gate returned no-argument root PIDs `1485799`, `1485927`,
     and `1485936`, all owned by `claude`.
   - Evidence: latest dirty-tree samples reported `1800` default
     `git status --short` rows, `183` tracked changed files, and
     `183 files changed, 65718 insertions(+), 5198 deletions(-)`.
   - Audit judgment: no aggregate baseline should be accepted until writers,
     status publishers, and duplicate root/focused runners are frozen and one
     snapshot is accepted or rejected lane by lane.

2. **High - the public dashboard is stale and still does not satisfy the
   dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, current
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard fields for benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC`, source commit
     `bda83c6b93d4`, and average progress `68.8%`, while sampled `HEAD` is
     `d7602dae56e3`.
   - Evidence: dashboard rows disagree with current lane files. Rclone is
     published as `327` denominator, `291` mapped, and `291 pass`
     (`porting.html:63`), while current files report `1601` total and `494`
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

3. **High - progress and lane status are claiming near-complete work without
   accepted commits or a comparable root test.**
   - Paths: `progress.md:31`-`42`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:12`-`13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require small committed
     slices with passing tests, current progress tracking, and supervisor
     verification.
   - Evidence: `progress.md:31`-`42` still shows old estimates from `5%` to
     `66%`, while current lane status files claim much higher progress:
     Difftastic `92%`, Dolt `93%`, esbuild `74%`, Gitoxide `98%`, libsqlite
     `98%`, LightningCSS `85%`, markerPDF `93%`, Pandoc `96%`, Quadrable
     `99%`, rclone `98%`, Readability `94%`, and Syncthing `98%`.
   - Evidence: every sampled `latestCommit` field is pending, prose, or an
     unaccepted dirty-batch handoff rather than a clean accepted commit ID.
     Examples include `pending in shared dirty worktree`, `not committed`,
     `uncommitted port-esbuild lane batch`, `pending - current Gitoxide
     external filter-driver batch`, `uncommitted lane-scoped changes`,
     `HEAD 0f754e8b at status update`, and `pending lane-local changes`.
   - Evidence: many blockers say there is no lane-local blocker while the
     same lines record root aggregate pending behind active runners. That is
     not an accepted integration state.

4. **High - manifest/status count schemas remain non-normalized and some files
   still contradict themselves.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
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
     `1714` mapped units but `156` PHP tests; Pandoc reports `693` mapped
     units but `223` PHP tests.
   - Evidence: some manifest warnings are stale relative to their own mapped
     fields. esbuild's manifest maps `237` at
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`, while its warning still
     says `236` at `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:200`.
     Syncthing maps `364` at
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, while its warning
     still says `359` at `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1059`.
     Readability maps `1714` at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, while its warning
     says native PHP maps only `156` local behavior tests at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:580` and repeats the same
     warning at line `591`.

5. **High - root-test evidence for the current dirty tree is invalid because
   duplicate no-argument root runs are already active.**
   - Paths: `lanes/*/lane-status.json:12`, `progress.md:329`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:49` require precise
     blockers and honest repo-wide test/static-check recording.
   - Evidence: this run did not start `php tools/run-tests.php` because the
     required gate returned active no-argument root harnesses:
     `1485799 claude 1435555 00:24 Rs php tools/run-tests.php`,
     `1485927 claude 1418047 00:16 Ss php tools/run-tests.php`, and
     `1485936 claude 1422269 00:14 Ss php tools/run-tests.php`.
   - Evidence: prior `progress.md:329` already recorded no root run because
     the stability gate failed. The current audit saw duplicate root runners
     after that, so any green snippet produced by those processes cannot be
     treated as a controlled aggregate baseline for this dirty tree.

6. **Medium - hard upstream-runner and hard-feature gaps are softened by
   92%-99% status claims.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require meaningful fixture parity, upstream tests as source
     of truth, and hard gaps marked as blockers or future slices.
   - Evidence: Gitoxide is `98%` while full cargo workspace parity, long-lived
     external filter execution, sparse-index writing, broader pack/ref/object
     integration, and full merge semantics remain open. markerPDF is `93%`
     while full Python benchmarks, model-heavy conversion, OCR/Pandoc/Nougat
     tooling, multiprocessing, and GitHub Actions execution remain
     unexecuted. Pandoc is `96%` while the Haskell Tasty runner is unexecuted.
     rclone and Syncthing are `98%` while live-provider/mount/full `go test
     ./...` coverage remains open. libsqlite is `98%` while checkpoint
     mutation, rollback journals, general SQL execution, triggers/upserts,
     broader writes, and broader corruption validation remain open.
     Quadrable is `99%` while the manifest still records the full sync fuzzer
     and 100,000-record sync benchmark as outside the fast suite.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed gate evidence:

```text
1484601 php tools/run-tests.php lanes/lightningcss/tests
1485799 php tools/run-tests.php
1485927 php tools/run-tests.php
1485936 php tools/run-tests.php
```

Owner evidence for the active no-argument root harnesses:

```text
1485799 claude 1435555 00:24 Rs php tools/run-tests.php
1485927 claude 1418047 00:16 Ss php tools/run-tests.php
1485936 claude 1422269 00:14 Ss php tools/run-tests.php
```

Active process evidence also included:

```text
1021826 claude bash ... run-tmux-agent.sh port-dolt-runner ...
1404953 claude bash ... run-tmux-agent.sh port-pandoc ...
1414982 claude bash ... run-tmux-agent.sh port-quadrable ...
1418011 claude bash ... run-tmux-agent.sh port-syncthing ...
1422186 claude bash ... run-tmux-agent.sh port-readability ...
1434849 claude bash ... run-tmux-agent.sh port-libsqlite ...
1435444 claude bash ... run-tmux-agent.sh port-dolt ...
1435500 claude bash ... run-tmux-agent.sh port-esbuild ...
1443523 claude bash ... run-tmux-agent.sh port-rclone ...
1459360 claude bash ... run-tmux-agent.sh port-gitoxide ...
1462854 claude bash ... run-tmux-agent.sh port-difftastic ...
1470380 claude bash ... run-tmux-agent.sh port-integrator ...
1480566 claude bash ... run-tmux-agent.sh port-auditor ...
1485328 claude bash ... run-tmux-agent.sh port-lightningcss ...
1486097 claude timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest ...
1486127 claude go test -p 1 ./libraries/doltcore/sqle/enginetest ...
2347911 claude bash scripts/run-team-watchdog.sh
2424048 claude bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2452997 claude bash scripts/run-capacity-controller-loop.sh
2479222 claude bash scripts/run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
find lanes -path '*/UPSTREAM_TEST_MANIFEST.json' -print | sort
git log --oneline --decorate --no-abbrev-commit -n 25
git show --stat --oneline --decorate -n 8
git status --short | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,stat,args | rg 'run-tmux-agent|run-capacity-controller-loop|run-dashboard-updater-loop|run-evaluator-loop|run-team-watchdog|run-tests\.php|testrunner\.tcl|bats |go test|cargo test|npm test|artifact|run-php'
```

Results: all lane upstream manifests, all lane status files, and
`porting-summary.json` parsed as valid JSON. All 12 lane manifests were found.
Latest samples reported `1800` default status rows, `183` tracked changed
files, and `183 files changed, 65718 insertions(+), 5198 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator,
mapped, PHP pass/fail, runner, progress, scenario, and commit fields,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same accepted snapshot, rerun the exact duplicate-root
gate, and capture one quiesced no-argument `php tools/run-tests.php` result.
