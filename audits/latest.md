# Independent Audit - 2026-05-23T14:25:31Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
all 12 `lanes/*/lane-status.json` files, recent Git history, dirty tree
state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `0f754e8b6dcf` (`Refresh independent audit
status`). Recent history reviewed includes `0f754e8b`, `8bfcb551`,
`ac948ba0`, `4ba8b4f4`, `37bbfa36`, `005fd686`, `79a7e66a`, `9dfec34f`,
`1c06a555`, `85dcd312`, `66798317`, `be7cf14f`, `c1f67cda`, `d52cc007`,
`24260634`, `51867989`, `b75226d1`, `30be5e3c`, `90d1fa3b`, `81419ac3`,
`69405063`, `0f1444c1`, `efa4e0c2`, `f8bd46e4`, `09995598`, `a04f2c8b`,
`5b6d5a84`, `957f8587`, `f40a591b`, `e6182a8c`, `9f4f13f`, `e77d40c2`,
`13c0daf8`, `55605cb0`, and `07221e76`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:10`-`13`, `porting.html:32`-`36`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped active
     work, current owner/session tracking, small committed slices, supervisor
     verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` still documents a two-implementation-lane plus
     one-auditor target, and `progress.md:31`-`42` still reports every lane
     session as `stopped`.
   - Evidence: active process sampling found implementation/status/audit loops
     across nearly the whole portfolio plus extra review agents, broad Dolt
     BATS, and SQLite Tcl runners. Examples include Dolt BATS PIDs
     `1036684`/`1036713`/`1036720`/`1036721`, SQLite `testrunner.tcl --jobs
     10` PIDs `1057838`/`1057839`, lane agents for Gitoxide, markerPDF,
     libsqlite, readability, Quadrable, rclone, Dolt, Syncthing, Difftastic,
     esbuild, LightningCSS, Pandoc, auditor, and integrator, plus
     `run-team-watchdog.sh`, `run-evaluator-loop.sh`,
     `run-capacity-controller-loop.sh`, and `run-dashboard-updater-loop.sh`.
   - Evidence: the tree moved while being audited. Latest samples reported
     `1796` default `git status --short` rows, `185` tracked changed files,
     and `185 files changed, 65672 insertions(+), 5349 deletions(-)`.
     Gitoxide lane status changed during the read from the prior credential
     helper slice (`3680` PHP assertions) to the external filter-driver slice
     (`3717` PHP assertions).
   - Audit judgment: aggregate acceptance remains blocked until active writers
     and broad runners are frozen and one snapshot is accepted or rejected lane
     by lane.

2. **High - the published dashboard is stale and still violates the dashboard
   contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, current `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     current `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard fields for benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC`, source commit
     `bda83c6b93d4`, and average progress `68.8%`, while sampled `HEAD` is
     `0f754e8b6dcf`.
   - Evidence: dashboard rows disagree with current lane files. Rclone is
     published as `327` denominator, `291` mapped, and `291 pass`; current
     files report `1601` total and `494` mapped at
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`, with `494` PHP pass
     at `lanes/rclone/lane-status.json:5`-`7`. markerPDF is published as
     `78` denominator, `159` mapped, and `264 pass`; current files report
     `281` total and `229` mapped at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`, with `342` PHP
     pass at `lanes/markerpdf/lane-status.json:5`-`7`. Gitoxide is published
     as `1432 / 2877` mapped and `2646 pass`; current files report `1972`
     mapped at `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15` and
     `3717` PHP pass at `lanes/gitoxide/lane-status.json:5`-`7`.
   - Evidence: `porting.html:41`-`50` still collapses required dashboard
     fields into combined `Benchmark` and `Mapped` cells instead of exposing
     upstream denominator, mapped tests, and PHP pass/fail as first-class
     columns.

3. **High - progress/status is reporting near-complete lanes without accepted
   commits.**
   - Paths: `progress.md:31`-`42`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:44`, and `goal.md:48` require small committed slices, current
     owner/session/progress tracking, and supervisor verification before
     advancing.
   - Evidence: `progress.md:31`-`42` still shows old estimates from `5%` to
     `66%`, while lane status files now claim high completion: Difftastic
     `92%`, Dolt `93%`, esbuild `74%`, Gitoxide `98%`, libsqlite `98%`,
     LightningCSS `85%`, markerPDF `92%`, Pandoc `96%`, Quadrable `99%`,
     rclone `98%`, Readability `94%`, and Syncthing `98%`.
   - Evidence: every sampled `latestCommit` field is pending, prose, or an
     unaccepted dirty-batch handoff rather than a clean accepted commit ID.
     Examples include Difftastic "pending in shared dirty worktree", Dolt "not
     committed", esbuild "uncommitted port-esbuild lane batch", Gitoxide
     "pending", libsqlite "uncommitted lane-scoped changes", markerPDF
     "uncommitted lane batch", rclone "pending lane-local changes",
     Readability "uncommitted", and Syncthing "pending".

4. **High - manifest/status count schemas remain non-normalized and internally
   inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/*/lane-status.json:5`-`7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require a real upstream denominator,
     mapped upstream tests, PHP passing/failing counts, and comparable
     dashboard fields.
   - Evidence: several `benchmarkDenominator.total` values are prose strings
     instead of numeric denominators: Difftastic, Dolt, esbuild, Pandoc, and
     Quadrable. Other lanes use numbers, so automated portfolio math mixes
     schemas.
   - Evidence: mapped and pass units are still different measures. Current
     examples: Gitoxide reports `1972` mapped units but `3717` PHP assertions;
     LightningCSS reports `1266` mapped units but `1431` PHP assertions;
     Readability reports `1714` mapped units but `156` PHP behavior tests;
     Pandoc reports `685` mapped units but `222` PHP tests.
   - Evidence: some files now disagree internally. Difftastic manifest reports
     `281` mapped at `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15` while
     status reports `282` PHP pass at `lanes/difftastic/lane-status.json:5`-`7`.
     Dolt manifest reports `532` mapped at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`, while status still says
     `526` focused mappings at `lanes/dolt/lane-status.json:5`. markerPDF
     manifest reports `281` total and `229` mapped at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`, while status says
     `280` total and `228` mapped at `lanes/markerpdf/lane-status.json:5`.

5. **High - root-test evidence is still non-comparable for the current dirty
   tree.**
   - Paths: `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `progress.md:328`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:49` require precise
     blockers and honest repo-wide test/static-check recording.
   - Evidence: LightningCSS records an accidental no-argument root harness
     from before its current slice as green (`231` files, `28030` assertions,
     `0` failures), but its post-edit root remains pending because broad Dolt
     BATS and SQLite runners were active. Readability records root pending
     because another root harness PID `1389717` was active. Syncthing and
     rclone also record root pending behind active focused/broad runners.
   - Evidence: the prior progress audit at `progress.md:328` already recorded
     green, red, pending, and duplicate-gated lane root-test records as
     conflicting. The current read did not produce a quiesced root run for
     sampled `HEAD` plus the dirty tree, so none of those lane snippets should
     be treated as the accepted aggregate baseline.

6. **Medium - hard upstream-runner and hard-feature gaps are softened by
   92%-99% status claims.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require meaningful fixture parity, upstream tests as source
     of truth, and hard gaps marked as blockers or future slices.
   - Evidence: Gitoxide is `98%` while full cargo workspace parity, sparse
     index writing, broader pack/ref/object integration, and full merge
     semantics remain open. markerPDF is `92%` while full Python benchmarks,
     model-heavy conversion, OCR/Pandoc/Nougat tooling, multiprocessing, and
     GitHub Actions execution remain unexecuted. Pandoc is `96%` while the
     Haskell Tasty runner is unexecuted. rclone and Syncthing are `98%` while
     live-provider/mount/full `go test ./...` coverage remains open.
     libsqlite is `98%` while checkpoint mutation, rollback journals, general
     SQL execution, triggers/upserts, broader writes, and broader corruption
     validation remain open.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed exact-gate samples were clear until post-edit validation briefly
returned focused Quadrable PID `1456030` (`php tools/run-tests.php
lanes/quadrable/tests`), which exited before owner sampling; a later exact gate
returned no rows. No root run was started because the tree was not stable
enough: active writers/status loops, broad Dolt BATS, SQLite
`testrunner.tcl --jobs 10`, and changing lane status files were present.

Active process evidence included:

```text
1036684 claude timeout 90m bats verify-constraints.bats ...
1036713 claude /usr/bin/bash /usr/libexec/bats-core/bats ...
1036720 claude /usr/bin/bash /usr/libexec/bats-core/bats-exec-suite ...
1057838 claude timeout 7200 ./testfixture ../src/test/testrunner.tcl --jobs 10 --stop-on-error all
1057839 claude ./testfixture ../src/test/testrunner.tcl --jobs 10 --stop-on-error all
1277936 claude bash ... run-tmux-agent.sh port-gitoxide ...
1278036 claude bash ... run-tmux-agent.sh port-markerpdf ...
1278178 claude bash ... run-tmux-agent.sh port-libsqlite ...
1278407 claude bash ... run-tmux-agent.sh port-readability ...
1278858 claude bash ... run-tmux-agent.sh port-quadrable ...
1279108 claude bash ... run-tmux-agent.sh port-rclone ...
1279335 claude bash ... run-tmux-agent.sh port-dolt ...
1289923 claude bash ... run-tmux-agent.sh port-syncthing ...
1344547 claude bash ... run-tmux-agent.sh port-difftastic ...
1344619 claude bash ... run-tmux-agent.sh port-esbuild ...
1352224 claude bash ... run-tmux-agent.sh port-lightningcss ...
1394210 claude bash ... run-tmux-agent.sh port-auditor ...
1394255 claude bash ... run-tmux-agent.sh port-integrator ...
1404953 claude bash ... run-tmux-agent.sh port-pandoc ...
2347911 claude bash scripts/run-team-watchdog.sh
2424048 claude bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
2452997 claude bash scripts/run-capacity-controller-loop.sh
2479222 claude bash scripts/run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
rg --files lanes -g 'UPSTREAM_TEST_MANIFEST.json'
git log --oneline --decorate --no-abbrev-commit -n 35
git status --short --untracked-files=no | wc -l
git status --short | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,stat,args | rg 'run-tmux-agent|run-capacity-controller-loop|run-dashboard-updater-loop|run-evaluator-loop|run-team-watchdog|run-tests\.php|testrunner\.tcl|bats |go test|cargo test|npm test|artifact'
```

Results: all lane upstream manifests, all lane status files, and
`porting-summary.json` parsed as valid JSON. All 12 lane manifests were found.
Latest samples reported `1796` default status rows, `185` tracked changed
files, and `185 files changed, 65672 insertions(+), 5349 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator,
mapped, PHP pass/fail, runner, progress, scenario, and commit fields,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same accepted snapshot, rerun the exact duplicate-root
gate, and capture one quiesced no-argument `php tools/run-tests.php` result.
