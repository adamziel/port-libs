# Independent Audit - 2026-05-23T06:02:30Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent Git
history through `b84cdfacdcbd`, current dirty-tree status, process/test state,
and PHP shell-out usage in `lanes/`, `tools`, and `scripts`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. I treated bridge/generated/oracle tooling as non-progress unless it was
explicitly temporary fixture or oracle evidence.

## Findings

1. **High - active writers still prevent a trustworthy integration baseline, even with the latest green root run.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `.tmux-team/tmp/*`, `.tmux-team/prompts/*`, `.tmux-team/logs/*`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and `tools/run-tests.php`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`, `goal.md:48`, and
     `goal.md:49` require a practical concurrency cap, accurate current
     owner/session state, deliberate integration, and repo-wide verification.
   - Evidence: `progress.md` still says the launch target is two implementation
     lanes plus one auditor, and the Active Lanes table still reports all lanes
     stopped. Process sampling at audit close still found `25` matching
     watchdog/agent/test/dashboard/evaluator processes, including active root
     and focused `php tools/run-tests.php` processes owned by other workers.
   - Evidence: during this audit, observed `HEAD` moved/rebased from an initial
     `96de001a` sample to `b84cdfacdcbd`; dirty status changed from `732`
     entries/`102` tracked files to `743` entries/`107` tracked files.
   - Audit judgment: the green root run is useful diagnostic evidence, but do
     not accept or publish the aggregate until active writers are frozen and one
     quiesced snapshot is tested.

2. **High - `porting.html` and `porting-summary.json` remain stale and still fail the required dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:33`, `porting.html:36`,
     `porting.html:41`-`50`, `porting.html:54`-`65`, and
     `porting-summary.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while current
     `HEAD` is `b84cdfacdcbd`. It combines benchmark source and denominator
     under `Benchmark`, and combines PHP pass/fail with mapped count under
     `Mapped`, instead of exposing the separate columns required by the goal.
   - Evidence: current manifest mapped counts disagree with the page:
     Difftastic `179 / 556` versus dashboard `160 / 417`, Dolt `282 / 613`
     versus `242 / 613`, esbuild `171 / 2567` versus `164 / 2567`, Gitoxide
     `1457 / 2877` versus `1432 / 2877`, libsqlite `167 / 1454` versus
     `149 / 1454`, LightningCSS `820 / 3532` versus `773 / 3532`, markerPDF
     `167` mapped versus `159`, Pandoc `495 / 2028` versus `426 / 2028`,
     rclone `316 / 327` versus `291 / 327`, Readability `1115 / 1984` versus
     `1031 / 1984`, and Syncthing `253 / 658` versus `235 / 658`.
   - Audit judgment: the dashboard is an old publication snapshot, not the
     current status surface.

3. **High - the working tree remains a broad dirty aggregate, not small reviewable slices.**
   - Paths: representative dirty paths include `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, many `lanes/*/src/*`,
     many `lanes/*/tests/*`, `porting.html`, `porting-summary.json`, and
     `progress.md`.
   - Requirement at risk: `goal.md:29`, `goal.md:36`, `goal.md:48`, and
     `goal.md:49` require small correct slices, cleanup of unrelated changes,
     verified integration, and passing repo-wide tests before acceptance.
   - Evidence: latest sampling reported `743` default `git status --short`
     entries, `107` tracked changed files, and `107 files changed, 19804
     insertions(+), 719 deletions(-)` in `git diff --shortstat`.
   - Audit judgment: accept or reject dirty lane batches one lane at a time
     after freezing writers; do not treat this aggregate as a reviewable
     checkpoint just because the root harness currently passes.

4. **High - manifest denominator fields are still too mixed to support portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators, explicit slice mapping
     for huge suites, and dashboard fields that distinguish denominator, mapped
     tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is an integer in some lanes and a
     long prose string in others. Units mix Go test files, BATS files, test
     functions, repository paths, fixtures, inspected behavior artifacts,
     benchmark pairs, and supplied-boundary examples. `runnerStatus` is an
     object in some lanes, a prose string in Gitoxide/markerPDF/Quadrable, and
     null in Pandoc.
   - Evidence: markerPDF currently reports `mapped=167` against a denominator
     described as `78 tracked upstream repository paths` plus benchmark/support
     evidence, which makes the mapped percentage nonsensical without a typed
     denominator unit.
   - Audit judgment: normalize manifest schema and units before using average
     progress or lane percentages for planning.

5. **Medium - bounded/static evidence is still easy to misread as full upstream parity.**
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
   - Evidence: Gitoxide is high-scoring without full Cargo workspace parity;
     Difftastic and Pandoc remain static/no-full-runner lanes; markerPDF cannot
     run the full ML/PDF benchmark stack; rclone and Dolt have bounded runner
     evidence but not full provider/mount/full-BATS/full-Go parity; Syncthing
     still lacks full `go test ./...` parity.
   - Audit judgment: expose `full-runner`, `bounded-runner`,
     `static-inventory`, `oracle-fixture`, and `supplied-boundary` as explicit
     fields so bounded evidence cannot be mistaken for full upstream acceptance.

6. **Medium - `progress.md` still contains stale active-lane and blocker prose.**
   - Paths: `progress.md:31`-`42`, `progress.md:246`,
     `progress.md:254`-`259`.
   - Requirement at risk: `goal.md:44`, `goal.md:48`, and `goal.md:49` require
     current active lanes, open blockers, current owner/session state, and next
     tasks.
   - Evidence: prior progress text still described the libsqlite replacement
     regression as the active blocker, but `cf5fff72`/`b84cdfac` landed after
     that audit and this run's root harness passed. The stale stopped-session
     table also contradicts the current process sample.
   - Audit judgment: update the audit status and next intervention to reflect
     the green-but-unaccepted root run and the remaining freeze/reconcile work.
     Do not edit lane implementation status from this auditor pass.

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
185 test files, 19724 assertions, 0 failures
```

The command initially reported another active root test lock, waited, then ran
to completion. Treat the result as diagnostic evidence from a moving dirty tree
until writers are frozen and the same accepted snapshot is retested.

## Recent Git History

Recent commits reviewed:

```text
b84cdfac Stamp libsqlite large replacement status
cf5fff72 Advance libsqlite large replacement overflow planning
89b251e7 Refresh independent audit status
b75cdedf Refresh independent audit status
0e312c81 Record esbuild verification counts
d54461d5 Stamp libsqlite lane commit
228941de Refresh independent audit status
297a8415 Update libsqlite lane status commit reference
569f1f89 Port esbuild private static assign semantics
006c18a5 Teach run-tests focused path selection
91b9704a Refresh independent audit status
49e2068b Port esbuild TS decorator and static field slices
```

The libsqlite red root result from the prior audit appears addressed by the
new libsqlite commits, but the repository is still moving under active writers.
`297a8415` remains a warning sign because it mixed audit/progress updates with
libsqlite implementation files.

## Recommended Next Intervention

Freeze active writers and duplicate loops, let any in-flight root/focused test
processes finish or stop them intentionally, then capture one quiesced root
`php tools/run-tests.php` run from a single accepted snapshot. After that,
accept or reject dirty lane batches one lane at a time, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same snapshot, and normalize manifest denominator/runner-status fields
before publishing percentages.
