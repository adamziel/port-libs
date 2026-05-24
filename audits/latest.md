# Independent Audit - 2026-05-24T00:55Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD moved during audit: 375b8e2bf40b -> 7efe0af399c2
latest visible commits: 7efe0af3 Record integration hold status; b1adfb65 Record integration hold status; 541a0b49 Record integration hold status
branch sample: main...origin/main [ahead 620, behind 68]
tracked dirty rows: 292
total status rows including untracked: 9819
git diff HEAD --shortstat: 292 files changed, 134147 insertions(+), 15377 deletions(-)
tmux sessions: 160
required pre-root gate: earlier matched active root PID 1389725 plus focused Syncthing PID 1389878; later matched transient focused LightningCSS PID 1427286; latest pre-commit sample matched active root PID 1444333 plus focused shards
owner evidence: 1389725 claude 1389675 78s R+ php tools/run-tests.php
owner evidence: 1389878 claude 1276603 77s Rs php tools/run-tests.php lanes/syncthing/tests
final focused PID 1427286 exited before owner sampling
latest root owner evidence: 1444333 claude 1444253 17s R+ php tools/run-tests.php
```

No root run was started by this audit. The required exact gate
`pgrep -af '^php tools/run-tests\.php( |$)'` matched an active no-argument root
harness earlier in the audit and matched a transient focused LightningCSS
harness later; the latest pre-commit exact gate matched active root PID
`1444333` plus focused shards. The broader stability gate failed independently:
`HEAD` moved repeatedly during the audit, 160 tmux sessions were present,
active lane/watchdog/dashboard/evaluator/integrator/capacity/test-control loops
were visible, and the worktree remained a large dirty aggregate.

## Findings

1. **Critical - the tree is not stable enough for any accepted aggregate root
   result.**
   - Paths: `progress.md:35`, `progress.md:39` through `progress.md:66`,
     `lanes/*/lane-status.json`, `.tmux-team/`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: `HEAD` moved from `375b8e2bf40b` to `7efe0af399c2` while this
     audit was running. The required pre-root gate matched active no-argument
     root PID `1389725` and focused Syncthing PID `1389878`, both owned by
     `claude`; the latest pre-commit sample matched active no-argument root
     PID `1444333` owned by `claude` plus focused shards. `tmux list-sessions`
     reported `160` sessions, and the worktree held `292` tracked dirty rows,
     `9819` total status rows, and `292 files changed`. A repo-wide result
     here would measure a moving integration
     queue, not an accepted baseline.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and
   materially contradict current manifests/statuses.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75`, `porting-summary.json:1` through
     `porting-summary.json:18`, `dependency-backlog.json:1` through
     `dependency-backlog.json:5`, `dependency-backlog.json:110` through
     `dependency-backlog.json:123`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current denominator, mapped-test, PHP pass/fail,
     WordPress scenario, phase, audit, current work, blocker, and commit fields
     in `progress.md` and `porting.html`.
   - Evidence: the dashboard still publishes snapshot `main 79768df0c427`
     generated `2026-05-23 23:43:54 UTC`, while current `HEAD` is
     `7efe0af399c2`. Current manifests/statuses have advanced beyond the page:
     Difftastic dashboard `374 / 735` vs manifest `390 / 755`, Gitoxide
     dashboard `2751 / 2877` vs manifest `2777 / 2877`, libsqlite dashboard
     `286 / 1589` vs manifest `293 / 1589`, LightningCSS dashboard `1732 /
     3532` vs manifest `1811 / 3532`, markerPDF dashboard `280 / 330` vs
     manifest `286 / 336`, Pandoc dashboard `1061 / 2276` vs manifest `1114 /
     2276`, Readability dashboard `204` PHP pass vs manifest `211` PHP
     behavior tests, and Syncthing dashboard `4579` PHP pass text vs lane
     status `4928` assertions. The dashboard auxiliary table still shows `22`
     dependencies while `dependency-backlog.json` has `23`, including
     `pandoc-doctemplates-core`.

3. **High - every lane is still a pending dirty handoff, not an accepted
   implementation slice.**
   - Paths: `progress.md:49` through `progress.md:66`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable slices with passing tests, then verification, commit, progress
     update, cleanup, and reassignment.
   - Evidence: lane `latestCommit` fields still say `pending`,
     `uncommitted`, `not committed`, or dirty-batch prose. Current history is
     dominated by audit/status/integration-hold commits, while lane sources,
     tests, examples, fixtures, manifests, and statuses remain mixed in the
     dirty aggregate.

4. **High - near-complete dashboard percentages overstate accepted native
   upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:775`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:279`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15` through
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:416`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:677`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:32`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1161`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1415`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real denominators,
     meaningful fixture parity, upstream tests as source of truth, explicit
     slices, and blockers for hard features.
   - Evidence: `porting.html` reports `92-99%` per lane and `97.7%` average,
     but current native PHP mapping remains bounded: esbuild maps `319` of
     `2,567` upstream entries, libsqlite `293 / 1589`, LightningCSS `1811 /
     3532`, markerPDF `286 / 336` with `runnerStatus: not-executed`, Pandoc
     `1114 / 2276`, rclone `721 / 1601`, and Difftastic `390 / 755`.
     Gitoxide still lacks full Cargo workspace pass parity; Syncthing still
     lacks full `go test ./...`; rclone still excludes provider/mount parity;
     markerPDF is still blocked on the heavy Python/PDF/model/server stack.

5. **High - essential optional-library coverage remains backlog-only, and
   broad shared-infra activation would be too coarse without manifests.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:123`,
     `dependency-backlog.json:168` through `dependency-backlog.json:215`,
     `dependency-backlog.json:236` through `dependency-backlog.json:257`,
     `dependency-backlog.json:285` through `dependency-backlog.json:355`,
     `porting.html:71` through `porting.html:78`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require rich
     document/runtime behavior, native implementation, real denominators, no
     shell-out progress credit, and explicit hard blockers.
   - Evidence: `dependency-backlog.json` has `23` candidate/deferred rows, but
     none has a support-library `UPSTREAM_TEST_MANIFEST.json`, activation
     owner/session, dependency-specific upstream/spec denominator, mapped
     fixture matrix, PHP pass/fail evidence, malformed/corrupt-case coverage,
     or dashboard lane. Rich gaps remain for ZIP/DOCX/DOC/EPUB/ODT,
     doctemplates, citation/CSL, math/TeX, PDF text/render/OCR/layout/table
     geometry, source maps, protobuf/BEP wire compatibility, tree-sitter-style
     grammar behavior, charset/Unicode repair, checksum/hash behavior, archive
     streams, glob/pathspec, and provider metadata normalization. Candidate
     rows like `shared-infra-after-base-green` span many lanes; activating
     those as broad batches would violate the same bounded, native,
     denominator-backed granularity required of primary lanes.

6. **Medium - manifest and status schemas are still non-normalized and in some
   cases internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2352` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2359`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:2619`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:819`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: denominator totals alternate between integers and long prose;
     Dolt places latest-evidence narrative in `benchmarkDenominator.total`
     while the comparable mapped count remains `613`; LightningCSS reports
     manifest `mapped: 1811` while warning prose still says native PHP maps
     `1,735`; Readability reports `mapped: 1984` while PHP behavior tests are
     `211`; PHP counts mix behavior tests, assertions, selected files, and PASS
     cases; dashboard rows collapse denominator/mapped/PHP pass-fail into
     strings that cannot be reliably compared across lanes.

7. **Medium - blocker fields still lead with slice-local green language while
   full-port blockers remain unresolved.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: many blocker fields start with "No current", "No focused", or
     "No lane-local" blocker, then later mention pending aggregate root
     verification, unexecuted full upstream runners, excluded live providers,
     external runtimes, broad hydration/build requirements, or hard feature
     gaps. That ordering makes acceptance blockers read like secondary notes
     instead of the primary integration state.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate matched an active no-argument root harness and
a focused Syncthing harness earlier in the audit:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
1389725 php tools/run-tests.php
1389878 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,user,ppid,etimes,stat,args -p 1389725,1389878
1389725 claude 1389675 78 R+ php tools/run-tests.php
1389878 claude 1276603 77 Rs php tools/run-tests.php lanes/syncthing/tests

final sample:
pgrep -af '^php tools/run-tests\.php( |$)'
1427286 php tools/run-tests.php lanes/lightningcss/tests

ps -o pid,user,ppid,etimes,stat,args -p 1427286
<process exited before owner sampling>

latest pre-commit sample:
pgrep -af '^php tools/run-tests\.php( |$)'
1444333 php tools/run-tests.php
1445262 php tools/run-tests.php lanes/readability/tests
1445317 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
1445593 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
1446043 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
1446096 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests
1446131 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests

ps -o pid,user,ppid,etimes,stat,args -p 1444333,1445317,1445593,1446131
1444333 claude 1444253 17 R+ php tools/run-tests.php
1445317 claude 1445044 15 R+ php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
1445593 claude 1445368 14 R+ php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
1446131 claude 1445637 13 R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

The tree was also not stable enough for a root run even without the active
root PID: `HEAD` moved during the audit, 160 tmux sessions were present, active
writers/test-control loops were visible, and the dirty aggregate remained very
large.

Verification performed by this audit:

```text
read goal.md, progress.md, porting.html, porting-summary.json
read every lanes/*/UPSTREAM_TEST_MANIFEST.json and every lanes/*/lane-status.json
read dependency-backlog.json and recent git history
sampled live process/worktree/tmux state without reading process environments
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active writers/status publishers, focused PHP shards, upstream runners,
capacity jobs, dashboard/evaluator/auditor/integrator/watchdog loops, and
duplicate root harnesses. Then accept or reject dirty lane batches one lane at
a time, normalize manifest/status denominator, mapped, runner, PHP pass/fail,
blocker, and commit schemas, split optional dependency candidates into bounded
manifest-backed support-library ports only behind concrete base-lane blockers,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from one accepted commit, and only then run a quiesced root
`php tools/run-tests.php` if the exact duplicate-root gate is empty.
