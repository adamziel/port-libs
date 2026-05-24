# Independent Audit - 2026-05-24T00:38Z

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
HEAD at close: 51240f1ab7ef Record integration hold status
HEAD moved during audit: 87818d73 -> 51240f1a
latest visible commits: 51240f1a Record integration hold status; 87818d73 Refresh independent audit status; 0e3378aa Record integration hold status
tracked dirty rows: 284
total status rows including untracked: 9486
git diff HEAD --shortstat: 284 files changed, 129858 insertions(+), 14713 deletions(-)
tmux sessions: 160
active repo worker/test-control processes sampled: 55
exact pre-root gate at final sample: clear, no rows from pgrep -af '^php tools/run-tests\.php( |$)'
```

No root run was started by this audit. The required exact duplicate-root probe
was clear at the final sample, but the stability gate failed independently:
`HEAD` moved during the audit, broad Dolt BATS remained active, worker/status
loops were active, and the dirty aggregate is still too large for a trustworthy
repo-wide baseline.

## Findings

1. **Critical - the repository is still not stable enough for an accepted
   aggregate root result.**
   - Paths: `progress.md:34`, `progress.md:40` through `progress.md:51`,
     `.tmux-team/`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still says the launch target is two
     implementation lanes plus one auditor and lists every lane as `stopped`,
     but live sampling found 160 tmux sessions and 55 repo worker/test-control
     processes. `HEAD` moved from `87818d73` to `51240f1a` while the audit was
     running. Broad Dolt BATS PIDs including `840968`, `840969`, `840979`,
     `840980`, `840981`, `1271830`, and `1282602` were active. The worktree
     has 284 tracked dirty rows and 9486 total status rows.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and
   contradict current manifests/statuses.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `dependency-backlog.json:3` through `dependency-backlog.json:5`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current denominator, mapped-test, PHP pass/fail,
     WordPress scenario, phase, audit, current work, blocker, and commit
     fields in `progress.md` and `porting.html`.
   - Evidence: the dashboard still publishes `main 79768df0c427` generated at
     `2026-05-23 23:43:54 UTC`, while current `HEAD` is `51240f1ab7ef`.
     Current manifest/status samples now report Difftastic `382 / 746` vs
     dashboard `374 / 735`, esbuild status `319` PHP pass and manifest `318`
     mapped vs dashboard `311`, Gitoxide `2772` mapped and `5805` PHP pass vs
     dashboard `2751` and `5634`, libsqlite `290` vs dashboard `286`,
     LightningCSS `1774` mapped and `2265` PHP pass vs dashboard `1732` and
     `2197`, markerPDF `285` mapped and `421` PHP pass vs dashboard `280` and
     `416`, Pandoc `1105` mapped and `285` PHP pass vs dashboard `1061` and
     `278`, rclone manifest `717` mapped/status `719` PHP pass vs dashboard
     `698`, Readability `209` PHP pass vs dashboard `204`, and Syncthing
     `4865` PHP pass vs dashboard `4579`. The dashboard auxiliary table still
     shows 22 dependency items while `dependency-backlog.json` has 23.

3. **High - every lane remains a pending or uncommitted dirty-batch handoff,
   not an accepted implementation slice.**
   - Paths: `lanes/*/lane-status.json`, dirty lane paths under
     `lanes/*/{src,tests,examples,fixtures,notes}`,
     `porting.html:56` through `porting.html:67`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small
     reviewable slices with passing tests, then verification, commit, progress
     update, cleanup, and reassignment.
   - Evidence: current `latestCommit` fields say `pending`, `uncommitted`,
     `not committed`, `pending lane-local changes`, or dirty-batch prose
     across all lanes. Recent history is dominated by audit/status/integration
     hold commits, while lane source, tests, fixtures, examples, manifests,
     and statuses remain mixed in the dirty aggregate.

4. **High - near-complete percentages still overstate accepted native upstream
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, meaningful fixture parity, upstream tests as source of
     truth, explicit slices, and blockers for hard features.
   - Evidence: `porting.html` reports 92-99% lane progress and 97.7% average,
     but current manifests show bounded slices: esbuild maps `318 / 2567`,
     libsqlite `290 / 1589`, LightningCSS `1774 / 3532`, Pandoc `1105 /
     2276`, rclone `717 / 1601`, markerPDF `285 / 335`, and Difftastic
     `382 / 746`. Gitoxide still lacks full Cargo workspace pass, Syncthing
     still lacks full upstream `go test ./...` parity, markerPDF remains
     dominated by static/reference evidence for heavy PDF/model/server
     workflows, and Quadrable's upstream pass is still a pending dirty-batch
     handoff rather than an accepted aggregate checkpoint.

5. **High - essential optional-library coverage is backlog-only, not
   manifest-backed support-library progress.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:435`, `porting.html:71` through
     `porting.html:78`, `progress.md:17` through `progress.md:24`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require rich
     document/runtime behavior, native implementation, real denominators, no
     shell-out progress credit, and explicit hard blockers.
   - Evidence: `dependency-backlog.json` has 23 candidate/deferred rows, but
     none has a dependency-specific manifest, accepted upstream/spec
     denominator, mapped fixture matrix, PHP pass/fail record, owner/session,
     latest commit, malformed/corrupt evidence, or dashboard lane. Rich gaps
     remain for Pandoc ZIP/DOCX/DOC/EPUB/ODT/doctemplates/citation/math,
     markerPDF PDF text/render/OCR/layout/table geometry, esbuild source maps,
     Syncthing protobuf/BEP wire compatibility, Difftastic tree-sitter/charset
     behavior, and rclone provider metadata/checksum/archive boundaries.
     Whole applications and external converter/model wrappers remain correctly
     out of scope, but the current backlog does not yet provide the same
     granularity required of lanes.

6. **Medium - manifest/status schemas remain too non-normalized for reliable
   dashboard comparison.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2348`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numeric values
     and long prose; Dolt stores latest evidence narrative where a comparable
     denominator should be; PHP counts mix behavior tests, assertions, PASS
     cases, and selected files; and manifest/status counts can disagree within
     the same lane, as seen with esbuild manifest `318` vs status `319`, and
     rclone manifest `717` vs status `719`.

7. **Medium - blocker fields still lead with slice-local green language while
   full-port blockers remain unresolved.**
   - Paths: `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: many blocker fields start with "No current", "No focused", or
     "No lane-local" blockers, then later mention unexecuted full upstream
     runners, live provider suites, broad dependency graphs, pending root
     verification, external runtimes, or excluded hard features. That wording
     makes acceptance blockers read like secondary notes.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate was clear at the final sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
<no output>
```

The tree was still not stable enough for a root run: `HEAD` moved during the
audit, broad Dolt BATS was active, live sampling found 160 tmux sessions and 55
repo worker/test-control processes, and the worktree had 284 tracked dirty rows
plus 9486 total status rows.

Verification performed by this audit:

```text
read goal.md, progress.md, porting.html, porting-summary.json
read every lanes/*/UPSTREAM_TEST_MANIFEST.json and every lanes/*/lane-status.json
read dependency-backlog.json and recent git history
sampled live process/worktree/tmux state without reading process environments
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, focused PHP shards, broad upstream runners, BATS jobs,
and duplicate root harnesses. Then accept or reject dirty lane batches one lane
at a time, normalize manifest/status denominator, mapped, runner, PHP
pass/fail, blocker, and commit schemas, split optional dependency candidates
into manifest-backed bounded ports only behind concrete base-lane blockers,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from one accepted commit, and only then run a quiesced root
`php tools/run-tests.php` if the exact duplicate-root gate remains clear.
