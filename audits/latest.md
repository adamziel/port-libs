# Independent Audit - 2026-05-24T01:01Z

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
HEAD moved during audit: 297235d12a3e -> 45156e797d6c
latest visible commits: 45156e79 Record integration hold status; 297235d1 Refresh independent audit status; 7efe0af3 Record integration hold status
branch sample: main...origin/main [ahead 622, behind 68]
tracked dirty rows: 290
total status rows including untracked: 9822
git diff HEAD --shortstat: 290 files changed, 134561 insertions(+), 15243 deletions(-)
tmux sessions: 162
required pre-root gate at 2026-05-24T01:01Z: no matches for pgrep -af '^php tools/run-tests\.php( |$)'
root run by this audit: not started; tree was not stable enough
```

## Findings

1. **Critical - the current worktree is not an acceptable aggregate
   verification target.**
   - Paths: `progress.md:39`, `progress.md:49` through `progress.md:66`,
     `lanes/*/lane-status.json`, `.tmux-team/`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-team-watchdog.sh`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices with
     passing tests, meaningful verification, integration cleanup, and honest
     repo-wide status.
   - Evidence: `HEAD` moved from `297235d12a3e` to `45156e797d6c` during
     this audit, recent history is still dominated by audit/status/integration
     hold commits, `tmux list-sessions` reports 162 sessions, and the worktree
     has 290 tracked dirty rows plus 9,822 total status rows. A root run here
     would measure a moving queue, not an accepted baseline.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict the current manifests/statuses.**
   - Paths: `porting.html:32`, `porting.html:34` through `porting.html:35`,
     `porting.html:56` through `porting.html:67`, `porting.html:75`,
     `porting-summary.json:1`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and `goal.md:45`
     require current denominator, mapped-test, PHP pass/fail, WordPress
     scenario, phase, audit, current work, blocker, and commit fields in the
     generated dashboard.
   - Evidence: the dashboard still shows generated
     `2026-05-23 23:43:54 UTC` at snapshot `main 79768df0c427`, while current
     `HEAD` is `45156e797d6c`. Current lane-status/manifest data has moved:
     Difftastic is `393` PHP pass and `759` inspected artifacts while the
     dashboard shows `374 / 735`; esbuild is `321` PHP pass and `320 / 2567`
     mapped while the dashboard shows `311`; Gitoxide is `5852` PHP pass and
     `2783 / 2877` mapped while the dashboard shows `5634` and `2751`;
     libsqlite is `294 / 1589` while the dashboard shows `286`; LightningCSS
     is `1841 / 3532` mapped and `2333` PHP pass while the dashboard shows
     `1732` and `2197`; markerPDF is `337` total, `287` mapped, and `424` PHP
     pass while the dashboard shows `330`, `280`, and `416`; Pandoc is `1126`
     mapped and `288` PHP pass while the dashboard shows `1061` and `278`;
     rclone is `724` mapped/pass while the dashboard shows `698`;
     Readability is `212` PHP pass while the dashboard shows `204`; Syncthing
     is `4963` PHP pass while the dashboard shows `4579`.

3. **High - every primary lane is still a dirty handoff, not an accepted
   slice.**
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
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require
     committed, reviewable slices after verification.
   - Evidence: all lane `latestCommit` fields say `pending`, `uncommitted`,
     `not committed`, or dirty-batch prose. The dirty tree includes lane
     sources, tests, fixtures, examples, notes, manifests, and statuses, so the
     status surface is describing worker handoffs instead of integrated work.

4. **High - dashboard percentages still overstate native upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real denominators,
     upstream tests as source of truth, explicit slices, and clear blockers for
     hard features.
   - Evidence: `porting.html` still reports `92-99%` lane progress and
     `97.7%` average, but current manifests/statuses remain bounded or static:
     Difftastic has no full Cargo runner; Gitoxide has no full Cargo workspace
     runner; Syncthing has no full `go test ./...`; Pandoc has no Haskell
     Tasty runner; markerPDF has no heavy upstream benchmark/model runner;
     rclone excludes provider/mount/live-service parity; esbuild still lacks
     release-extra `make test-all`; libsqlite still lacks full all/release
     permutations. These are valid blockers, not near-complete parity.

5. **High - optional support-library coverage is tracked as a backlog, but no
   support port has lane-grade evidence.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:437`,
     `porting.html:71` through `porting.html:115`,
     `porting-summary.json:1`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require rich
     document/runtime behavior, native implementations, real denominators,
     mapped fixtures, pass/fail evidence, and no shell-out progress credit.
   - Evidence: `dependency-backlog.json` now has 23 rows including
     `pandoc-doctemplates-core` at `dependency-backlog.json:110`, while
     `porting.html:75` and `porting-summary.json:1` still publish 22. None of
     the support rows has a support-library `UPSTREAM_TEST_MANIFEST.json`,
     activation owner/session, dependency-specific upstream/spec denominator,
     mapped fixture matrix, PHP pass/fail evidence, or malformed/corrupt-case
     coverage. Rich gaps remain for ZIP/DOCX/DOC/EPUB/ODT, doctemplates,
     CSL/citations, math/TeX, PDF text/render/OCR/layout/table geometry,
     source maps, protobuf/BEP wire compatibility, tree-sitter-style grammar
     behavior, Unicode/charset repair, checksums, archive streams,
     glob/pathspec, and provider metadata normalization.

6. **Medium - manifest/status schemas remain non-normalized and hard to
   compare across lanes.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2347`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/*/lane-status.json`, `porting-summary.json:1`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require comparable denominator, mapped, and PHP pass/fail
     fields.
   - Evidence: `benchmarkDenominator.total` is sometimes an integer and
     sometimes long prose; Dolt has `mapped: 613` near the top but a later
     `total` field that is a narrative evidence paragraph; Difftastic and
     Pandoc expose prose totals; PHP counts mix behavior tests, assertions,
     PASS cases, and upstream entries. The compact dashboard collapses these
     into strings, so a reader cannot safely compare lanes or compute accepted
     parity.

7. **Medium - blocker fields still lead with local-green language while
   acceptance blockers remain unresolved.**
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
   - Evidence: many blockers begin with "No current", "No focused", or "No
     lane-local" blocker, then later mention pending root verification,
     uncommitted dirty batches, unexecuted full upstream runners, excluded
     provider/live-service coverage, or broad hydration/build limits. The
     ordering makes acceptance blockers read like secondary notes.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate was checked:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
<no matches at 2026-05-24T01:01Z>
```

Even with no matching root harness at that sample, the tree was not stable
enough for a meaningful root run: `HEAD` moved during the audit, 162 tmux
sessions were present, every lane remained a dirty handoff, and the worktree
contained 290 tracked dirty rows plus 9,822 total status rows.

## Recommended Next Intervention

Freeze intake briefly, pick one narrow lane batch, run the root harness once on
that accepted snapshot, commit or reject that batch, regenerate
`porting.html`/`porting-summary.json` from the same snapshot, and only then
resume capacity feeding. Do not activate support-library work until each
dependency has a bounded native scope, activation gate, upstream/spec
denominator, mapped fixtures, PHP pass/fail evidence, and malformed/corrupt
cases where relevant.
