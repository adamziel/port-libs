# Independent Audit - 2026-05-23T17:05:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent Git history, current worktree
state, process state, and the required duplicate-root test gate. I also sampled
`lanes/*/lane-status.json` and `porting-summary.json` to compare dashboard and
manifest claims.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, copied fixture oracles, generated artifacts, and
shell-outs are treated as non-progress unless they are explicitly temporary
oracle tooling.

Sampled `HEAD`: `9b7db00b676f` (`Refresh independent audit status`). The latest
43 commits before the nearest sampled implementation commit are audit-only
`Refresh independent audit status` commits. `jq empty` passed for every lane
manifest, every lane-status file, and `porting-summary.json`.

## Manifest/Status Snapshot

| Lane | Current manifest denominator | Manifest mapped | Lane PHP pass/fail | Commit field |
| --- | ---: | ---: | ---: | --- |
| difftastic | 614 inspected artifacts | 308 | 308 / 0 | null |
| dolt | 613 executable test files embedded in prose | 591 | 316 / 0 | null |
| esbuild | 2,567 upstream entry points | 263 | 263 / 0 | null |
| gitoxide | 2,877 upstream files | 2,170 | 4,124 / 0 | null |
| libsqlite | 1,589 upstream units | 253 | 251 / 0 | null |
| lightningcss | 3,532 behavior checks | 1,438 | 1,629 / 0 | null |
| markerPDF | 294 static behavior/reference units | 242 | 372 / 0 | null |
| pandoc | 2,276 inspected artifacts | 795 | 238 / 0 | null |
| quadrable | 55 tracked paths plus runner notes | 55 | 163 / 0 | null |
| rclone | 1,601 Go test/benchmark/example units | 541 | 541 / 0 | null |
| readability | 1,984 Mocha tests | 1,900 | 170 / 0 | null |
| syncthing | 658 Go entry points | 453 | 3,160 / 0 | null |

## Findings

1. **Critical - there is still no stable integration snapshot suitable for an accepted root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised lane
     work and a single auditor; `goal.md:29` and `goal.md:49` require small
     committed slices plus honest repo-wide test evidence.
   - Evidence: `progress.md:25` still documents a target of two implementation
     lanes plus one auditor, and `progress.md:31` through `progress.md:42`
     reports all lane sessions as `stopped`.
   - Evidence: active process sampling found 60 matching repo worker/status
     processes, including dashboard updater, team watchdog, evaluator,
     capacity controller, auditor, integrator, Dolt runner, and many lane
     agents.
   - Evidence: the worktree is not quiescent. Latest samples reported 218
     tracked dirty rows, 2,376 total status rows including untracked files, and
     `218 files changed, 96119 insertions(+), 8732 deletions(-)`.
   - Evidence: an early required duplicate-root gate matched transient root PID
     `2712153 php tools/run-tests.php`; the process exited before owner
     sampling. A later pre-commit gate matched active root PID `2758705`, with
     owner evidence `2758705 claude 2758669 00:29 R php tools/run-tests.php`.
     I did not start a duplicate root harness.

2. **Critical - `porting.html` is stale and does not satisfy the required dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require a generated dashboard with current average progress and separate
     per-lane upstream denominator, mapped tests, PHP pass/fail, phase, audit,
     blocker, and commit fields.
   - Evidence: `porting.html:32` says generated `2026-05-23 04:57:16 UTC`,
     and `porting.html:33` through `porting.html:36` still publish snapshot
     `bda83c6b93d4`, while sampled `HEAD` is `9b7db00b676f`.
   - Evidence: published rows disagree with current manifests/status files:
     Difftastic is published as `160 / 417` but current is `308 / 614`;
     markerPDF is `159 / 78` but current is `242 / 294`; rclone is
     `291 / 327` but current is `541 / 1601`; Readability is `1031 / 1984`
     but current is `1900 / 1984`; Syncthing is `235 / 658` but current is
     `453 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks
     separate upstream-denominator and PHP pass/fail columns; `porting.html:54`
     through `porting.html:65` mixes PHP pass/fail and mapped coverage in one
     cell.

3. **High - `progress.md` does not describe the active system.**
   - Paths: `progress.md:14`, `progress.md:25`, `progress.md:31` through
     `progress.md:42`, `progress.md:363` through `progress.md:365`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows all sessions as `stopped`
     with stale estimates from `5%` to `66%`, while active process sampling
     shows lane/watchdog/status publishers running and lane-status files report
     much newer PHP counts.
   - Evidence: `progress.md:14` still leaves the independent auditor loop
     unchecked even though audit refresh commits and a `port-auditor` watchdog
     are active.
   - Evidence: the listed next tasks are stale relative to current lane-status
     text, for example Gitoxide SOCKS/proxy work, LightningCSS grid/CSSOM work,
     and Dolt idle deferral.

4. **High - most claimed lane progress is unaccepted dirty-batch work, not small committed slices.**
   - Paths: `lanes/*/lane-status.json`, recent Git history,
     `progress.md:44` through `progress.md:50`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable commits, verification, progress updates, and
     honest status.
   - Evidence: every sampled lane-status file has `commit: null`.
   - Evidence: the latest 43 commits before the nearest sampled implementation
     commit are audit-only refreshes, while lane-status files describe newer
     uncommitted lane work.
   - Evidence: the dirty tree contains 218 tracked files and 2,376 total status
     rows, so the current native slices cannot be treated as accepted,
     reviewable checkpoints.

5. **High - manifest/status schemas remain non-normalized and internally inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/libsqlite/lane-status.json:6`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:648`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:654`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:659`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, and comparable dashboard fields.
   - Evidence: denominator `total` is numeric in some manifests and long prose
     in others. Dolt's denominator field begins with runner prose and only later
     embeds the `613` executable-test count.
   - Evidence: PHP pass units are not comparable with mapped upstream units:
     Gitoxide maps `2,170` upstream units but reports `4,124` PHP assertions;
     Syncthing maps `453` but reports `3,160`; Readability maps `1,900` but
     reports `170`; markerPDF maps `242` but reports `372`.
   - Evidence: libsqlite currently maps `253` in the manifest but reports
     `251` PHP pass at `lanes/libsqlite/lane-status.json:6`.
   - Evidence: Readability has stale warning summaries at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:648` and `:659` that still
     mention older PHP test/assertion totals, while `:654` now reports
     `phpBehaviorTests: 170`.

6. **High - "no blocker" language understates remaining upstream parity gaps.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`, `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and hard features to be marked as blockers or future slices.
   - Evidence: several blockers start with "No current ... blocker" while also
     admitting major unexecuted or out-of-scope work: Gitoxide full cargo
     workspace parity, markerPDF live Python/model/Streamlit/FastAPI and heavy
     model dependencies, rclone live provider/mount parity, Syncthing full
     `go test ./...`, Pandoc full Haskell runner, and Quadrable full
     sync-fuzzer/benchmark runs.
   - Audit judgment: focused lane tests are useful, but "no blocker" should
     be scoped to the latest local slice and not read as lane parity.

7. **Medium - progress accounting still blends static/copied oracle coverage with native implementation parity.**
   - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:648`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:769`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:589`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1155`.
   - Goal requirement at risk: `goal.md:30` and `goal.md:35` say generated
     fixtures, bridge calls, shell-outs, and shallow fixture parity do not count
     as native implementation progress by themselves.
   - Evidence: Readability maps `1,900` upstream Mocha checks while native PHP
     behavior tests are `170`, and the manifest warns this is not full native
     PHP parity.
   - Evidence: markerPDF reports `242 / 294` mapped static units with
     `runnerStatus: not-executed`; this is a useful inventory but not a native
     benchmark pass.
   - Evidence: Difftastic and Syncthing explicitly warn that their current
     denominators are static or bounded-focused inventories, not full upstream
     runner parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Sampled evidence:

```text
2712153 php tools/run-tests.php
2758705 php tools/run-tests.php
```

The first transient root process exited before owner sampling. The later active
root process had owner evidence:

```text
2758705 claude 2758669 00:29 R php tools/run-tests.php
```

No duplicate root run was started; the repository also remained too unstable
for a trustworthy no-argument root run.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused test loops.
Then validate all manifests from a frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP pass-fail/runner
and commit fields, regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the same accepted snapshot, and only then capture
one quiesced root `php tools/run-tests.php` run.
