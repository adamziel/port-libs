# Independent Audit - 2026-05-24T00:27Z

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
observed HEAD movement during audit: d5ffc5970af0 -> 9f4b38b3362d
latest visible commits: 9f4b38b3 Record integration hold status; d5ffc597 Refresh independent audit status; 9d7cc81a Record integration hold status
commits since 2026-05-23 00:00 UTC: 781
tracked dirty rows: 284
total status rows including untracked: 9540
git diff HEAD --shortstat: 284 files changed, 128323 insertions(+), 14800 deletions(-)
tmux sessions: 147
active repo worker/test-control processes sampled: 46
exact pre-root gate: initially clear; later matched focused PHP PID 1087120 (`php tools/run-tests.php lanes/readability/tests`); final sample clear
owner evidence for PID 1087120: unavailable because it exited before `ps` sampling; no no-argument root harness was confirmed by this audit
```

No root run was started by this audit. The required exact duplicate-root probe
was not consistently clear, and the stability gate failed independently: `HEAD`
moved during the run, broad lane/control processes were active, and the dirty
tree remains too large for a trustworthy aggregate result.

## Findings

1. **Critical - the repository is still not stable enough for an accepted
   aggregate root result.**
   - Paths: `progress.md:34`, `progress.md:40` through `progress.md:51`,
     `.tmux-team/`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still says the launch target is two
     implementation lanes plus one auditor and lists all lanes as `stopped`,
     while sampling found 147 tmux sessions, 46 active repo worker/test-control
     processes, 284 tracked dirty rows, and 9540 total status rows. `HEAD`
     moved from `d5ffc5970af0` to `9f4b38b3362d` during this audit. The exact
     pre-root gate briefly matched `1087120 php tools/run-tests.php
     lanes/readability/tests`; it exited before owner sampling and the final
     exact sample was clear, but this is still a moving, non-quiescent tree.

2. **Critical - `porting.html` and `porting-summary.json` are stale relative
   to current manifests, lane statuses, and the dependency backlog.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75`, `porting-summary.json`,
     `dependency-backlog.json:3` through `dependency-backlog.json:5`,
     `dependency-backlog.json:110` through `dependency-backlog.json:120`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current denominator, mapped-test, PHP pass/fail,
     WordPress scenario, phase, audit, current work, blocker, and commit
     fields in `progress.md` and `porting.html`.
   - Evidence: `porting.html` still publishes `main 79768df0c427` generated
     at `2026-05-23 23:43:54 UTC`, while current `HEAD` is `9f4b38b3362d`.
     Dashboard rows lag current files: Difftastic is published as `374 / 735`
     mapped but the current manifest reports `379 / 739`; esbuild `311` vs
     current `316`; Gitoxide `2751` vs `2768`; libsqlite `286` vs `289`;
     markerPDF `280 / 330` vs `284 / 334`; Pandoc `1061` vs `1097`;
     rclone `698` vs `713`; and Syncthing PHP pass count `4579` vs `4814`.
     The dashboard auxiliary table still says 22 dependency items, while
     `dependency-backlog.json` now has 23 after `pandoc-doctemplates-core`.

3. **High - every lane still reports pending, uncommitted, or dirty-batch
   handoff state instead of a clean implementation commit.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`, dirty lane paths under
     `lanes/*/{src,tests,examples,fixtures,notes}`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small
     reviewable slices with passing tests, then verification, commit,
     progress update, cleanup, and reassignment.
   - Evidence: current `latestCommit` fields include `pending`,
     `uncommitted`, `not committed`, `HEAD ... at status update`, or
     lane-batch prose. Recent history is dominated by audit/status/integration
     hold commits while lane source, test, fixture, example, manifest, and
     status files remain mixed across the dirty aggregate.

4. **High - near-complete percentages continue to overstate accepted native
   upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, meaningful fixture parity, upstream tests as source of
     truth, explicit slices, and blockers for hard features.
   - Evidence: the dashboard reports 92-99% per lane and 97.7% average, but
     several lanes remain bounded-slice ports: esbuild maps `316 / 2567`,
     libsqlite `289 / 1589`, Pandoc `1097 / 2276` without Cabal runner parity,
     rclone `713 / 1601` while live provider/mount suites are excluded,
     Gitoxide `2768 / 2877` without full Cargo workspace pass, Difftastic
     still lacks full upstream runner parity, and markerPDF uses a static
     behavior/reference denominator because upstream has no committed Python
     tests and its heavy PDF/model/server workflows remain unexecuted.

5. **High - essential optional-library coverage is still backlog-only, not
   lane-grade work.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:120`, `porting.html:71` through
     `porting.html:78`, `progress.md:17` through `progress.md:24`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require rich
     document/runtime behavior, native implementation, real denominators, no
     shell-out progress credit, and explicit hard blockers.
   - Evidence: the backlog has 23 rows, but none has a dependency-specific
     manifest, accepted upstream/spec denominator, mapped fixture matrix, PHP
     pass/fail record, owner/session, latest commit, malformed/corrupt
     evidence, or dashboard lane. Rich gaps remain for Pandoc
     ZIP/DOCX/DOC/EPUB/ODT/doctemplates/citation/math; markerPDF PDF text,
     render planning, OCR/layout results, and table geometry; esbuild source
     maps; Syncthing protobuf/BEP wire compatibility; Difftastic tree-sitter,
     charset, and encoding behavior; and rclone provider metadata, checksum,
     archive, and live-provider boundaries. Broad shared rows such as
     XML/HTML5 DOM, Unicode repair, checksum suite, archive/compression,
     tree-sitter, SQL storage codecs, and glob/pathspec must be split into
     bounded spec, algorithm, provider, or fixture-family manifests before
     they receive progress credit.

6. **Medium - blocker fields still lead with local-green language while
   burying full-port blockers.**
   - Paths: `lanes/*/lane-status.json`, especially blocker fields beginning
     with "No current", "No focused", or "No lane-local".
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: blocker fields often start by saying there is no local PHP
     blocker, then list unexecuted full upstream runners, live provider suites,
     external model/runtime stacks, broad dependency graphs, or pending root
     verification. That framing makes acceptance blockers read like secondary
     notes.

7. **Medium - manifest/status schemas remain too non-normalized for reliable
   dashboard comparison.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     prose; Dolt stores latest-evidence narrative where a comparable total
     should be; PHP counts mix behavior tests, assertions, PASS cases,
     selected files, and lane-local checks; `porting-summary.json` does not
     expose the generated/snapshot fields queried during this audit; and
     lane-status fields remain mostly prose.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact duplicate-root probe was sampled before considering a root
run. It was initially clear, later matched a focused lane harness, and was
clear again at final sample:

```text
1087120 php tools/run-tests.php lanes/readability/tests
```

`1087120` exited before owner sampling, so no owner line was available. I still
did not start a root run because the tree was not stable enough: active lane,
dashboard, watchdog, evaluator, integrator, capacity, auditor, and BATS
processes were present; `HEAD` moved during the audit; and the worktree had
284 tracked dirty rows plus 9540 total status rows.

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
