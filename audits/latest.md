# Independent Audit - 2026-05-24T01:16Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, `audits/integration-status.md`,
recent Git history, and the required root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, plan-only wrappers, and
shell-outs are treated as non-progress unless they are explicitly temporary
oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD: 2a7bb6acda6c
latest visible commits: 2a7bb6ac Record integration hold status; ec2242ac Refresh independent audit status; c25b9a01 Record integration hold status
branch sample: main...origin/main [ahead 627, behind 68]
tracked dirty rows: 292
total status rows including untracked: 10361
git diff --shortstat: 292 files changed, 136157 insertions(+), 15433 deletions(-)
tmux sessions: 163
required exact pre-root gate at 2026-05-24T01:16:45Z: no matches for pgrep -af '^php tools/run-tests\.php( |$)'
final pre-finish recheck at 2026-05-24T01:19:06Z: active root PID 1758599 (claude) plus focused Syncthing PID 1759023 (claude)
root run by this audit: not started; no duplicate root harness was launched and the tree was not stable enough for a meaningful root aggregate
```

## Findings

1. **Critical - the current worktree is still not an acceptable aggregate
   verification target.**
   - Paths: `progress.md:39`, `progress.md:51` through `progress.md:68`,
     `audits/integration-status.md:5` through `audits/integration-status.md:47`,
     `lanes/*/lane-status.json`, `.tmux-team/`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:48`, and `goal.md:49` require small committed slices, honest
     repo-wide tests/static checks, and supervisor acceptance before progress
     is counted.
   - Evidence: this sample has 292 tracked dirty rows, 10,361 total status
     rows, 292 changed tracked files, and 163 tmux sessions. Recent history is
     still dominated by audit/status/integration-hold commits, not accepted
     lane commits. The latest integration hold also says no lane output was
     integrated and that dirty lane rows span every priority lane. A root run
     on this checkout would measure a moving queue rather than an accepted
     snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict the current manifests/statuses.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require a current dashboard with denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit fields.
   - Evidence: the dashboard still advertises source commit `79768df0c427`
     generated at `2026-05-23 23:43:54 UTC`, while current `HEAD` is
     `2a7bb6acda6c`. Current lane-status/manifest values have moved: Difftastic
     is `395 / 762`, not dashboard `374 / 735`; esbuild is `322`, not `311`;
     Gitoxide is `2785 / 2877` with lane PHP `5859`, not `2751` and `5634`;
     LightningCSS is `1882 / 3532`, not `1732`; markerPDF is `338` total and
     `288` mapped, not `330` and `280`; Pandoc is `1130 / 2276`, not `1061`;
     rclone is `727 / 1601`, not `698`; Syncthing lane PHP is `5012`, not
     `4579`.

3. **High - markerPDF is over-crediting plan-only external/runtime
   orchestration as native mapped progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:893` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:910`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php:136` through
     `lanes/markerpdf/src/ChunkConversionPlanner.php:143`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:24`,
     `goal.md:29` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require native PHP implementation, no wrapper progress, precise blockers,
     and no shell-out/external converter credit.
   - Evidence: the markerPDF denominator reports `288` mapped semantics out of
     `338`, but counted items include Pandoc/XeLaTeX helper command planning,
     `chunk_convert.py` shell command assembly, `subprocess.run shell=True`,
     `chunk_convert.sh` `eval/background` lifecycle, Streamlit/FastAPI server
     planning, Poetry/package workflow planning, model-runtime dependency
     planning, and supplied callback handoffs. The PHP planner also emits
     `command` plus `shell_execution: eval`. Those may be useful preflight
     metadata, but they are not a native PDF extraction pipeline and should not
     be counted as rich-function port progress.

4. **High - every primary lane still reports a pending or uncommitted handoff,
   not an accepted slice.**
   - Paths: `progress.md:51` through `progress.md:66`,
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
     reviewable committed slices after verification and cleanup.
   - Evidence: latestCommit fields still say `pending`, `uncommitted`, `not
     committed`, or dirty-batch prose. The dirty tree includes lane sources,
     tests, fixtures, examples, notes, manifests, and statuses, so current
     status files describe worker output awaiting supervisor/integrator
     acceptance.

5. **High - optional support-library coverage is still backlog-only, and the
   checked-in dashboard publishes the wrong backlog count.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:5`, `dependency-backlog.json:7`,
     `dependency-backlog.json:110`, `dependency-backlog.json:285`,
     `dependency-backlog.json:321`, `dependency-backlog.json:337`,
     `porting.html:75`, `porting-summary.json:1` through
     `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require rich native behavior, dependency-grade denominators, mapped
     fixtures, malformed/corrupt cases where relevant, and no shell-out
     progress credit.
   - Evidence: `dependency-backlog.json` has 23 gated items, including
     `pandoc-doctemplates-core`, but `porting.html` still shows `Items: 22`.
     None of the support rows has a support-library manifest, activation
     owner/session, dependency-specific upstream/spec denominator, mapped
     fixture matrix, PHP pass/fail evidence, or malformed/corrupt-case
     coverage. Rich gaps remain for ZIP/DOCX/DOC/EPUB/ODT, doctemplates,
     CSL/citations, math/TeX, PDF text/render/OCR/layout/table geometry,
     source maps, protobuf/BEP wire compatibility, tree-sitter-style grammar
     behavior, Unicode/charset repair, checksums, archive streams,
     glob/pathspec, SQL/storage codecs, and provider metadata normalization.

6. **High - dashboard percentages still overstate native upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:32`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:18`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, upstream tests as source of truth, explicit slices, and
     honest blockers.
   - Evidence: `porting.html` still reports `92-99%` per-lane progress and
     `97.7%` average while several lanes remain static, bounded, or plan-only
     rather than near-complete native parity. Difftastic has no full Cargo
     runner; Gitoxide has no full Cargo workspace runner; Syncthing has no full
     `go test ./...`; Pandoc has no Haskell Tasty runner; markerPDF has no
     heavy benchmark/model runner and counts many plan-only wrapper boundaries;
     rclone excludes provider/mount/live-service parity; esbuild still lacks
     `make test-all`; libsqlite still lacks full all/release permutations.

7. **Medium - manifest/status schemas remain non-normalized and hard to compare
   across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2356` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2357`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:828` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:831`,
     `porting-summary.json:11` through `porting-summary.json:25`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require comparable denominator, mapped-test, and PHP
     pass/fail fields.
   - Evidence: `benchmarkDenominator.total` is sometimes a number, sometimes
     a long prose paragraph, and in Dolt a later `total` field is narrative
     evidence rather than a stable denominator. PHP pass values mix behavior
     tests, assertions, PASS cases, and upstream entries. The compact dashboard
     collapses these into strings, so a reader cannot safely compute accepted
     parity or compare lanes.

8. **Medium - blocker fields still lead with local-green language while the
   real acceptance blockers remain unresolved.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: many blockers begin with "No current", "No focused", or "No
     lane-local" blocker, then later acknowledge pending root verification,
     uncommitted dirty batches, unexecuted full upstream runners, excluded
     provider/live-service coverage, model-heavy markerPDF execution, or broad
     hydration/build limits. The unresolved acceptance blocker should be first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate was checked before considering a root run:

```text
2026-05-24T01:16:45Z
pgrep -af '^php tools/run-tests\.php( |$)'
<no matches>

pgrep -af '^php tools/run-tests\.php$'
<no matches>
```

A final pre-finish recheck found an active no-argument root harness, so I did
not start a duplicate:

```text
2026-05-24T01:19:06Z
pgrep -af '^php tools/run-tests\.php( |$)'
1758599 php tools/run-tests.php
1759023 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ... lanes/syncthing/tests/SystemStatusTest.php

pgrep -af '^php tools/run-tests\.php$'
1758599 php tools/run-tests.php

owner evidence:
1758599 claude 1758547 00:23 R+ php tools/run-tests.php
1759023 claude 1758822 00:21 R+ php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ... lanes/syncthing/tests/SystemStatusTest.php
```

The root run was not launched by this audit. The tree was also not stable
enough for a meaningful no-argument root run: 163 tmux sessions were present,
every lane remained a dirty handoff, current `HEAD` is status-only, and the
worktree contained 292 tracked dirty rows plus 10,361 total status rows.

## Recommended Next Intervention

Freeze intake and stop status churn long enough for a coherent acceptance
window. Accept or reject exactly one narrow lane batch, run one serialized
no-argument `php tools/run-tests.php` from that same snapshot, run
`git diff --check`, regenerate `porting.html` and `porting-summary.json` from
the accepted inputs, and commit that small batch. Do not count markerPDF
external app/shell/model orchestration or support-library backlog rows as
native port progress until each has a bounded PHP component, activation gate,
dependency-specific denominator, mapped fixtures, PHP pass/fail evidence, and
malformed/corrupt cases where relevant.
