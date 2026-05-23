# Independent Audit - 2026-05-23T09:18:53Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files needed for alignment
checks, recent Git history through `HEAD` `8d345b5eccf5`, dirty-tree state,
active process state, and the required PHP test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, `progress.md`
     Current Owner / Session, `.tmux-team/prompts/*`,
     `.tmux-team/logs/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped
     supervision, small committed slices with passing tests, integration
     verification, cleanup, and visible current progress.
   - Evidence: the audit started with recent history at `HEAD`
     `3e6be275`, then `HEAD` moved through `a1af8ce1b1ba` to
     `8d345b5eccf5` while this audit was gathering evidence. `progress.md:25`
     still documents a two-worker-plus auditor target, and `progress.md:31`-`42`
     still reports every lane as `stopped`, while active process sampling found
     48 matching watchdog, dashboard, evaluator, auditor, and lane-agent/Codex
     processes.
   - Evidence: latest dirty-tree samples reported `1109` default
     `git status --short` entries, `132` tracked changed files, and
     `132 files changed, 28683 insertions(+), 1027 deletions(-)`.
   - Audit judgment: do not accept any dashboard, manifest percentage,
     lane-status root-test anecdote, or `latestCommit` field as a portfolio
     baseline until active writers/status publishers are frozen and one
     snapshot is tested.

2. **Critical - root-harness evidence is still unusable as an accepted baseline.**
   - Paths: `tools/run-tests.php`, `lanes/esbuild/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`, `lanes/readability/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate
     `pgrep -af '^php tools/run-tests\.php( |$)'` returned active root PID
     `2614219` during the audit. Owner evidence captured without inspecting
     environments: `2614219 claude 2556151 00:33 R php tools/run-tests.php`.
     A later exact gate was clear, but a final exact gate found active root
     PID `2621711` with owner evidence
     `2621711 claude 2611561 00:21 R php tools/run-tests.php`. I did not
     start a root run.
   - Evidence: lane statuses disagree about the aggregate state. Examples:
     esbuild records a red root aggregate with `13` failures, Readability and
     Dolt record green aggregate anecdotes, Quadrable records commit blocked by
     `36` Syncthing failures, and several lanes record root verification
     pending because another root harness was already active.
   - Audit judgment: collapse root-test state to one repo-level record from a
     frozen snapshot. Lane-local aggregate anecdotes are diagnostic only.

3. **High - `porting.html` is stale and still misses the dashboard column contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`45`,
     `porting.html:54`-`65`, `porting-summary.json`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current dashboard fields for benchmark source, upstream
     denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase,
     audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while the
     inspected `HEAD` is `8d345b5eccf5`.
   - Evidence: the table still combines benchmark source and upstream
     denominator in one `Benchmark` column, and combines PHP pass/fail with
     mapped tests in one `Mapped` column. `goal.md:45` asks for separate
     upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: current manifest mapped counts disagree with the dashboard:
     Difftastic `220 / 583` vs `160 / 417`, Dolt `384 / 613` vs `242 / 613`,
     esbuild `201 / 2567` vs `164 / 2567`, Gitoxide `1717 / 2877` vs
     `1432 / 2877`, libsqlite `193 / 1454` vs `149 / 1454`, LightningCSS
     `984 / 3532` vs `773 / 3532`, markerPDF `191 / 247` vs `159 / 78`,
     Pandoc `544 / 2276` vs `426 / 2028`, rclone `392 / 2553` vs
     `291 / 327`, Readability `1353 / 1984` vs `1031 / 1984`, and Syncthing
     `285 / 658` vs `235 / 658`. Quadrable still maps `55 / 55`, but the
     dashboard PHP pass count is stale at `108` while lane status reports
     `125`.

4. **High - manifest/status schemas still cannot produce trustworthy portfolio percentages.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json` and all
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, `goal.md:44`, and `goal.md:45` require real denominators,
     meaningful fixture parity, explicit slices for huge suites, and current
     coordination fields.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
     `benchmarkDenominator.runnerStatus` is an object in seven lanes, a string
     in Gitoxide, markerPDF, and Quadrable, and absent from Pandoc.
   - Evidence: `nativeImplementation.phpBehaviorTests` is absent/null in
     Difftastic, Gitoxide, libsqlite, LightningCSS, Pandoc, Quadrable, and
     Syncthing, but present in Dolt, esbuild, markerPDF, rclone, and
     Readability. Lane status PHP pass counts are separate again, so mapped
     upstream units and PHP behavior tests remain easy to cross-display.
   - Evidence: counts moved during the audit: Dolt's manifest mapped count was
     observed at `377` and later at `384`, while its lane status text still
     says `377 focused upstream behavior mappings now recorded`.

5. **Medium - high progress percentages over-credit bounded, supplied, generated, or shell-backed evidence.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` prohibit crediting bridge/shell/generated
     artifacts as native implementation progress and require hard gaps to be
     explicit.
   - Evidence: Gitoxide and Difftastic still lack full Cargo runner parity,
     Pandoc remains static inventory without the Haskell runner, markerPDF is
     still largely supplied-document/OCR/plan coverage without a full upstream
     benchmark run, rclone excludes live provider/mount/FUSE/docker surfaces,
     Syncthing full `go test ./...` remains unrun, and Quadrable's strong
     C++ runner evidence still leaves heavy sync-fuzzer and LMDB/oracle breadth
     outside normal CI. These gaps are incompatible with treating 90%+ lane
     estimates as near-complete native parity.

6. **Medium - `progress.md` is stale against lane status files and current ownership.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, and
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, and percentage estimates.
   - Evidence: `progress.md:31`-`42` lists all lanes as `stopped` with
     estimates from `5%` to `66%`, while lane statuses now report
     Difftastic `74`, Dolt `75`, esbuild `65`, Gitoxide `98`, libsqlite `94`,
     LightningCSS `76`, markerPDF `77`, Pandoc `88`, Quadrable `96`,
     rclone `93`, Readability `76`, and Syncthing `93`.
   - Evidence: many `latestCommit` values are pending/prose dirty-batch text
     rather than a single accepted commit id for the current lane batch.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Active root sample observed during the audit:

```text
2614219 php tools/run-tests.php
```

Owner evidence:

```text
2614219 claude 2556151 00:33 R php tools/run-tests.php
```

Later exact duplicate-root gate:

```text
2621711 php tools/run-tests.php
```

Owner evidence:

```text
2621711 claude 2611561 00:21 R php tools/run-tests.php
```

No root run was started because the required final gate was active and the
repository remained non-quiescent.

Dirty/process snapshot:

```text
initial observed HEAD: 3e6be275
intermediate observed HEAD: a1af8ce1b1ba
latest observed HEAD: 8d345b5eccf5
git status --short: 1109 entries
git status --short --untracked-files=no: 132 tracked entries
git diff --shortstat: 132 files changed, 28683 insertions(+), 1027 deletions(-)
active automation matches: 48
```

Recent history reviewed:

```text
8d345b5e Advance quadrable proof command parity
a1af8ce1 Record difftastic lane implementation commit
3e6be275 Advance difftastic TOML and highlight mapping
c90ef906 Record active root audit handoff
b92b6b8a Refresh independent audit status
75bddbff Record latest moving-head audit gate
c6fad4e2 Record audit handoff after moving head
7902f910 libsqlite add composite replacement root split
```

## Next Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
rerun the exact duplicate-root gate and capture one quiesced
`php tools/run-tests.php` result from one accepted snapshot. Only after that,
accept or reject dirty lane batches one lane at a time, normalize manifest and
lane-status schemas, and regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the same snapshot.
