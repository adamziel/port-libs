# Independent Audit - 2026-05-24T01:09Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git history, and
the current root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling. A PHP shell-out scan found only dashboard metadata
collection plus Gitoxide test-oracle `proc_open` usage; no lane implementation
shell-out was credited as native progress.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD: 2f0ccff6d45b
latest visible commits: 2f0ccff6 Record integration hold status; da0a2bc1 Refresh independent audit status; 45156e79 Record integration hold status
branch sample: main...origin/main [ahead 624, behind 68]
tracked dirty rows: 290
total status rows including untracked: 10053
git diff HEAD --shortstat: 291 files changed, 135425 insertions(+), 15392 deletions(-)
tmux sessions: 161
required exact pre-root gate at 2026-05-24T01:08Z matched selected lane harnesses; no no-argument root PID was present
active selected harness owner evidence: 1579683 claude ... php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
active selected harness owner evidence: 1579988 claude ... php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
active selected harness owner evidence: 1580538 claude ... php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
root run by this audit: not started; tree and test queue were not stable enough
```

## Findings

1. **Critical - the current worktree is still not an acceptable aggregate
   verification target.**
   - Paths: `progress.md:39`, `progress.md:49` through `progress.md:67`,
     `lanes/*/lane-status.json`, `.tmux-team/`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-team-watchdog.sh`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:48`, and `goal.md:49` require committed reviewable slices,
     meaningful verification, integration cleanup, and honest repo-wide test
     status.
   - Evidence: the current sample has 290 tracked dirty rows, 10,053 total
     status rows, 291 changed tracked files, and 161 tmux sessions. Recent
     history is still dominated by audit/status/integration-hold commits, not
     accepted lane commits. The exact root gate matched active selected lane
     harnesses, and a no-argument root run here would measure a moving queue
     rather than an accepted snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifests/statuses.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`, `porting.html:75`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:215` through `porting-summary.json:218`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require a current dashboard with denominator, mapped-test,
     PHP pass/fail, WordPress scenario, phase, audit, current work, blocker,
     and commit fields.
   - Evidence: the dashboard still advertises generated
     `2026-05-23 23:43:54 UTC` at source commit `79768df0c427`, while current
     `HEAD` is `2f0ccff6d45b`. Current manifest/status values have moved:
     Difftastic is `393 / 759` while the dashboard shows `374 / 735`;
     esbuild is `321 / 2567` while the dashboard shows `311`; Gitoxide is
     `2785 / 2877` with lane PHP at `5859` assertions while the dashboard
     shows `2751` and `5634`; libsqlite is `294 / 1589` while the dashboard
     shows `286`; LightningCSS is `1882 / 3532` while the dashboard shows
     `1732`; markerPDF is `337` total, `287` mapped, and `424` PHP behavior
     tests while the dashboard shows `330`, `280`, and `416`; Pandoc is
     `1130 / 2276` and `289` focused tests while the dashboard shows `1061`
     and `278`; rclone is `724 / 1601` while the dashboard shows `698`;
     Readability is `213` PHP behavior tests while the dashboard shows `204`;
     Syncthing lane PHP is `4964` assertions while the dashboard shows `4579`.

3. **High - every primary lane remains a dirty handoff, not an accepted
   slice.**
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
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small
     committed slices after verification and integration cleanup.
   - Evidence: lane `latestCommit` fields still say `pending`,
     `uncommitted`, `not committed`, or dirty-batch prose. The dirty tree
     includes lane sources, tests, fixtures, examples, notes, manifests, and
     statuses, so current status files describe worker output awaiting
     supervisor acceptance.

4. **High - optional support-library coverage is still backlog-only, and the
   published backlog is stale.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7`, `dependency-backlog.json:110`,
     `dependency-backlog.json:285`, `dependency-backlog.json:303`,
     `dependency-backlog.json:321`, `dependency-backlog.json:421`,
     `porting.html:71` through `porting.html:115`,
     `porting-summary.json:215` through `porting-summary.json:218`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require rich native behavior, lane-grade denominators, mapped fixtures,
     PHP pass/fail evidence, precise blockers, and no shell-out progress
     credit.
   - Evidence: `dependency-backlog.json` now has 23 rows and includes
     `pandoc-doctemplates-core`, but `porting.html` and
     `porting-summary.json` still publish 22. None of the support rows has a
     support-library manifest, activation owner/session, dependency-specific
     upstream/spec denominator, mapped fixture matrix, PHP pass/fail evidence,
     or malformed/corrupt-case coverage. Rich gaps remain for ZIP/DOCX/DOC/
     EPUB/ODT, doctemplates, CSL/citations, math/TeX, PDF text/render/OCR/
     layout/table geometry, source maps, protobuf/BEP wire compatibility,
     tree-sitter-style grammar behavior, Unicode/charset repair, checksums,
     archive streams, glob/pathspec, and provider metadata normalization.

5. **High - dashboard percentages still overstate native upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:496`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:807`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real denominators,
     upstream tests as source of truth, explicit slices, and clear future
     blockers.
   - Evidence: `porting.html` still reports `92-99%` per-lane progress and
     `97.7%` average, while several lanes remain static or bounded rather than
     near-complete native parity: Difftastic has no full Cargo runner;
     Gitoxide has no full Cargo workspace runner; Syncthing has no full
     `go test ./...`; Pandoc has no Haskell Tasty runner; markerPDF has no
     heavy benchmark/model runner; rclone excludes provider/mount/live-service
     parity; esbuild still lacks release-extra `make test-all`; libsqlite
     still lacks full all/release permutations. Readability maps the full
     upstream Mocha denominator but only 213 native PHP behavior tests; that is
     useful progress, not 99% native parity.

6. **Medium - manifest/status schemas remain non-normalized and hard to
   compare across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2355`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `porting-summary.json:9` through `porting-summary.json:42`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require comparable denominator, mapped-test, and PHP
     pass/fail fields.
   - Evidence: `benchmarkDenominator.total` is sometimes an integer and
     sometimes long prose; Dolt has `mapped: 613` near the top but a later
     `total` field that is a narrative evidence paragraph; runner status is a
     boolean object in some manifests, a string in others, and absent in
     Pandoc. PHP values mix behavior tests, assertions, PASS cases, and
     upstream entries. The compact dashboard collapses these into strings, so
     a reader cannot safely compare lanes or compute accepted parity.

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
     ordering makes acceptance blockers read secondary even though they are
     the reason these slices cannot be counted as accepted progress.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate was checked:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
1579590 php tools/run-tests.php lanes/readability/tests
1579683 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
1579761 php tools/run-tests.php lanes/syncthing/tests/ConfigFoldersTest.php ...
1579988 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
1580454 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
1580538 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
1580638 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests

ps owner sample after short-lived completions:
1579683 claude 1579438 14s R+ php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
1579988 claude 1579721 14s R+ php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
1580538 claude 1580025 13s R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests

pgrep -af '^php tools/run-tests\.php$'
<no matches>
```

Even with no no-argument root PID, the tree was not stable enough for a
meaningful root run: 161 tmux sessions were present, every lane remained a
dirty handoff, current selected lane harnesses were active, and the worktree
contained 290 tracked dirty rows plus 10,053 total status rows.

## Recommended Next Intervention

Freeze intake, choose one narrow lane batch, run exactly one no-argument root
harness on that accepted snapshot, commit or reject that batch, regenerate
`porting.html` and `porting-summary.json` from the same snapshot, then resume
capacity feeding. Do not activate support-library work until each dependency
has a bounded native scope, activation gate, upstream/spec denominator, mapped
fixtures, PHP pass/fail evidence, and malformed/corrupt cases where relevant.
