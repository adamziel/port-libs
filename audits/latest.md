# Independent Audit - 2026-05-24T00:32Z

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
HEAD: 4fdf9d7c0214 Record integration hold status
latest visible commits: 4fdf9d7c Record integration hold status; 5ca9cdc3 Refresh independent audit status; 9f4b38b3 Record integration hold status
commits since 2026-05-24 00:00 UTC: 15
tracked dirty rows: 284
total status rows including untracked: 9476
git diff HEAD --shortstat: 284 files changed, 129148 insertions(+), 14707 deletions(-)
tmux sessions: 150
active repo worker/test-control processes sampled: 51
exact pre-root gate: matched focused PHP harnesses 1172305 and 1201910
owner evidence: 1172305 claude 1083060 78 Rs php tools/run-tests.php lanes/syncthing/tests
owner evidence: 1201910 claude 1061731 2 Rs php tools/run-tests.php lanes/quadrable/tests
```

No root run was started by this audit. The required duplicate-root probe
matched active `php tools/run-tests.php ...` processes, and the stability gate
failed independently because active lane, dashboard, watchdog, evaluator,
integrator, capacity, auditor, BATS, and focused PHP processes were present.

## Findings

1. **Critical - the repository is not stable enough for an accepted aggregate
   root result.**
   - Paths: `progress.md:34`, `progress.md:40` through `progress.md:51`,
     `.tmux-team/`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still says the launch target is two
     implementation lanes plus one auditor and lists every lane as `stopped`,
     but live sampling found 150 tmux sessions and 51 repo worker/test-control
     processes. The exact test gate matched focused PHP harnesses
     `1172305 php tools/run-tests.php lanes/syncthing/tests` and
     `1201910 php tools/run-tests.php lanes/quadrable/tests`; broad Dolt BATS
     was also active. The worktree has 284 tracked dirty rows and 9476 total
     status rows.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifests/statuses.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json`, `dependency-backlog.json:3` through
     `dependency-backlog.json:5`, `dependency-backlog.json:110` through
     `dependency-backlog.json:120`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current denominator, mapped-test, PHP pass/fail,
     WordPress scenario, phase, audit, current work, blocker, and commit
     fields in `progress.md` and `porting.html`.
   - Evidence: the dashboard still publishes `main 79768df0c427` generated at
     `2026-05-23 23:43:54 UTC`, while current `HEAD` is `4fdf9d7c0214`.
     Current manifests/statuses now report Difftastic `382` mapped vs
     dashboard `374`, esbuild `318` vs `311`, Gitoxide `2772` vs `2751`,
     libsqlite `290` vs `286`, LightningCSS `1736` mapped and `2226` PHP pass
     vs dashboard `1732` and `2197`, markerPDF `285` mapped and `420` PHP pass
     vs dashboard `280` and `416`, Pandoc `1097` mapped and `284` PHP pass vs
     dashboard `1061` and `278`, rclone `713` vs `698`, Readability `208` PHP
     pass vs dashboard `204`, and Syncthing `4814` PHP pass vs dashboard
     `4579`. The auxiliary table still shows 22 dependency items while
     `dependency-backlog.json` has 23.

3. **High - every lane is still a pending or uncommitted dirty-batch handoff,
   not an accepted implementation slice.**
   - Paths: `lanes/*/lane-status.json`, dirty lane paths under
     `lanes/*/{src,tests,examples,fixtures,notes}`,
     `porting.html:56` through `porting.html:67`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small
     reviewable slices with passing tests, then verification, commit, progress
     update, cleanup, and reassignment.
   - Evidence: current `latestCommit` fields say `pending`, `uncommitted`,
     `not committed`, or dirty-batch prose across all lanes. Recent history is
     dominated by audit/status/integration-hold commits while lane source,
     tests, fixtures, examples, manifests, and statuses remain mixed in the
     dirty aggregate.

4. **High - near-complete percentages still overstate accepted native
   upstream parity.**
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
     libsqlite `290 / 1589`, Pandoc `1097 / 2276`, rclone `713 / 1601`,
     LightningCSS `1736 / 3532`, markerPDF `285 / 335`, and Difftastic
     `382 / 746`. Gitoxide still lacks full Cargo workspace pass, Syncthing
     still lacks full upstream `go test ./...` parity, and markerPDF remains
     dominated by static/reference evidence for heavy PDF/model/server
     workflows.

5. **High - essential optional-library coverage is backlog-only and some rows
   are too broad to receive progress credit.**
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
     Cross-lane rows such as Unicode, charset, checksum, archive/compression,
     SQL storage codec, tree-sitter, and glob/pathspec need bounded
     spec/algorithm/provider fixture manifests before they count as progress.

6. **Medium - blocker fields lead with local-green language while full-port
   blockers remain unresolved.**
   - Paths: `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: many blocker fields start with "No current", "No focused", or
     "No lane-local" blockers, then later mention unexecuted full upstream
     runners, live provider suites, broad dependency graphs, pending root
     verification, external runtimes, or excluded hard features. That wording
     makes acceptance blockers read like secondary notes.

7. **Medium - manifest/status schemas remain too non-normalized for reliable
   dashboard comparison.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     long prose; Dolt stores latest-evidence narrative where a comparable
     total should be; PHP counts mix behavior tests, assertions, PASS cases,
     selected files, and lane-local checks; and lane-status fields remain
     mostly prose. This makes automated dashboard comparisons fragile.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate matched active focused PHP harnesses, so this
audit did not start a duplicate root run:

```text
1172305 php tools/run-tests.php lanes/syncthing/tests
1201910 php tools/run-tests.php lanes/quadrable/tests
1172305 claude 1083060 78 Rs php tools/run-tests.php lanes/syncthing/tests
1201910 claude 1061731 2 Rs php tools/run-tests.php lanes/quadrable/tests
```

The stability gate also failed: active lane, dashboard, watchdog, evaluator,
integrator, capacity, auditor, BATS, and focused PHP processes were present;
`tmux list-sessions` reported 150 sessions; and the worktree had 284 tracked
dirty rows plus 9476 total status rows.

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
