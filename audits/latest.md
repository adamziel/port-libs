# Independent Audit - 2026-05-23T11:15:30Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate.

Initial audit sample saw `HEAD` at `5dddc1ed717c` (`Refresh independent audit
status`). During the audit, active implementation work advanced `HEAD` to
`3c0421690eea` (`Advance libsqlite replacement planning`). Recent history
reviewed includes `3c042169`, `5dddc1ed`, `b529b1ee`, `c9254a88`,
`0319eb91`, `64f06d33`, `3227da76`, `ab141f82`, `873879be`, `5f2ae4bd`,
`37f77f2e`, `64e9fcf1`, `6c135b81`, `24837bc2`, `f03f1473`, `d656fc47`,
and `e9c15a9a`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:278`-`304`, `porting.html:30`-`65`,
     `porting-summary.json`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `scripts/run-team-watchdog.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:20` require
     supervised lane/auditor coordination, `goal.md:44` requires current
     owners/sessions and estimates, and `goal.md:49` requires honest repo-wide
     test recording.
   - Evidence: `progress.md:25` still says the current launch target is 2
     implementation lanes plus 1 auditor, and `progress.md:31`-`42` still
     marks all 12 lanes `stopped`. Process sampling instead found active
     watchdog, capacity-controller, dashboard-updater, evaluator, integrator,
     auditor, primary lane agents, and capacity jobs. Active samples included
     `2347911` team-watchdog, `2424048` evaluator, `2452997`
     capacity-controller, `2479222` dashboard-updater, `3216504` Pandoc,
     `3225399` LightningCSS, `3226723` markerPDF, `3249610` Gitoxide,
     `3249808` rclone, `3249891` Dolt runner, `3251683` Syncthing,
     `3251838` esbuild, `3259095` Difftastic, `3275065` auditor, `3288763`
     Dolt, `3305436` Quadrable, and `3305651` integrator.
   - Evidence: `HEAD` moved during this audit from `5dddc1ed717c` to
     `3c0421690eea`. Latest dirty samples reported `1325` default
     `git status --short --untracked-files=all` rows, `137` tracked changed
     files, and `137 files changed, 35284 insertions(+), 2509 deletions(-)`.
   - Audit judgment: do not accept current percentages, root-test anecdotes,
     blockers, or commit fields until active writers/status publishers are
     frozen and one regenerated snapshot is validated.

2. **High - the public dashboard is stale and still does not meet the required
   status contract.**
   - Paths: `porting.html:30`-`65`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require
     `porting.html` to show current benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while current `HEAD` sampled
     as `3c0421690eea`.
   - Evidence: `porting.html:41`-`50` collapses benchmark source/upstream
     denominator and PHP pass/fail/mapped tests into combined `Benchmark` and
     `Mapped` columns instead of the separate columns required by `goal.md:45`.
   - Evidence: current manifest/status numbers disagree with the published
     dashboard across the portfolio: Difftastic dashboard `160/417` vs current
     `245/587`; Dolt `242/613` vs `438/613`; esbuild `164/2567` vs
     `221/2567`; Gitoxide `1432/2877` vs `1881/2877`; libsqlite `149/1454`
     vs `205/1589`; LightningCSS `773/3532` vs `1140/3532`; markerPDF
     `159/78` vs `205/259`; Pandoc `426/2028` vs `614/2276`; rclone
     `291/327` vs `421/2553`; Readability `1031/1984` vs `1488/1984`;
     Syncthing `235/658` vs `317/658`. Quadrable still maps `55/55`, but the
     dashboard PHP count `108` is behind lane status `133`.

3. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`, `porting.html`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:44`, and
     `goal.md:45` require real upstream denominators, meaningful fixture
     parity, precise blockers, current owner/session state, and percentage
     estimates.
   - Evidence: `benchmarkDenominator.total` is prose rather than a normalized
     number in Difftastic, Dolt, esbuild, Pandoc, and Quadrable manifests
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`). Other lanes use
     numeric totals, so dashboard math still depends on ad hoc parsing.
   - Evidence: runner status placement/type remains inconsistent:
     Difftastic, Dolt, esbuild, libsqlite, LightningCSS, rclone, Readability,
     and Syncthing use object-shaped `runnerStatus`; Gitoxide, markerPDF, and
     Quadrable use string `runnerStatus`; Pandoc currently has no
     `benchmarkDenominator.runnerStatus` value.
   - Evidence: PHP count units remain mixed or missing. Some manifests expose
     `nativeImplementation.phpBehaviorTests` such as Dolt, esbuild, markerPDF,
     rclone, and Readability; other manifests omit a comparable native PHP
     count and rely on `lane-status.json` `phpPass`. Several status fields mix
     behavior-boundary counts, test-file counts, assertion counts, and root-run
     anecdotes.
   - Evidence: sampled `latestCommit` fields are not normalized commit IDs.
     Difftastic, Dolt, esbuild, Gitoxide, libsqlite, LightningCSS, markerPDF,
     Pandoc, Quadrable, rclone, Readability, and Syncthing all contain
     pending/prose/status text or commit descriptions rather than a single
     accepted commit hash.

4. **High - root-test evidence remains non-comparable and should not be used
   as an accepted baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:278`-`304`,
     `lanes/*/lane-status.json`, and `audits/latest.md`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide failures to be
     recorded honestly, and `goal.md:52` requires visible progress only after
     passing PHP tests.
   - Evidence: the required exact duplicate-root gate initially found an active
     root harness and a focused lane run:
     `3283821 php tools/run-tests.php` and
     `3284143 php tools/run-tests.php lanes/syncthing/tests`. Owner evidence
     captured before the root process exited:
     `3283821 claude 3249626 00:23 Rs php tools/run-tests.php`.
   - Evidence: a later exact root gate returned no output, but no root run was
     started because the stability gate failed: `HEAD` had moved during the
     audit, active writers/status publishers persisted, and the dirty tree was
     broad. This means a new root result would still not represent one
     accepted snapshot.
   - Evidence: lane statuses still describe mutually incompatible root states:
     some claim green root runs (Gitoxide, libsqlite, Pandoc, rclone), others
     record root pending due active duplicate PIDs (Difftastic, LightningCSS,
     markerPDF, Readability, Syncthing), Quadrable records red root failures
     outside its lane, and esbuild now records aggregate root blocked by an
     unrelated rclone example failure.

5. **Medium - high progress language still over-credits bounded, static, or
   runner-incomplete evidence.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, and
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and `goal.md:37`
     require upstream tests as source of truth, meaningful fixture parity, and
     clear treatment of generated fixtures, bridge calls, shell-outs, and
     oracle tooling.
   - Evidence: Gitoxide still lacks full Cargo workspace runner parity;
     libsqlite is bounded to veryquick/focused Tcl slices, not full SQLite
     all/release permutations; rclone excludes live providers, mount/FUSE,
     Docker, and `fstest/test_all`; Syncthing still lacks broad `go test ./...`;
     Pandoc lacks the Haskell runner; markerPDF keeps the full benchmark suite
     as not executed; Difftastic lacks full Cargo runner parity.
   - Evidence: Dolt's manifest mixes runner-only evidence, direct CLI probes,
     and native mapping metadata in the same denominator prose. That evidence
     may be useful as temporary oracle tooling, but it should not inflate
     native PHP progress without a normalized mapped-test contract and accepted
     native behavior slices.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed initially:

```text
3283821 php tools/run-tests.php
3284143 php tools/run-tests.php lanes/syncthing/tests
```

Owner evidence captured for the active root process:

```text
3283821 claude 3249626 00:23 Rs php tools/run-tests.php
```

A later exact duplicate-root sample returned no output, but no root run was
started because the stability gate failed: active writer/status loops persisted,
`HEAD` moved during the audit, and the dirty tree remained broad.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
pgrep -af '^php tools/run-tests\.php( |$)'
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest exact
root gate was clear, but the tree was still unstable. Latest dirty-tree samples
reported `1325` default status rows, `137` tracked changed files, and
`137 files changed, 35284 insertions(+), 2509 deletions(-)`.

Recent history reviewed:

```text
3c042169 Advance libsqlite replacement planning
5dddc1ed Refresh independent audit status
b529b1ee Port rclone OneDrive child ListP pagination
c9254a88 Refresh independent audit status
0319eb91 Record active root harness audit evidence
64f06d33 Refresh independent audit status
3227da76 Port rclone OneDrive ListR pagination slices
ab141f82 Refresh independent audit status
873879be Advance libsqlite auto-vacuum pointer maps
5f2ae4bd Refresh independent audit status
37f77f2e readability: record tmz lane status
64e9fcf1 Refresh independent audit status
6c135b81 readability: map tmz legacy post envelope
24837bc2 difftastic: stamp highlight status
f03f1473 difftastic: map parser highlight captures
d656fc47 Refresh independent audit status
e9c15a9a Update readability lane status
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
