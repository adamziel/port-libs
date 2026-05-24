# Independent Audit - 2026-05-24T01:27Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, plan-only wrappers, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD: 2ea9a6010fed
HEAD movement during audit: e194c05f -> b40f800b -> 2ea9a601 via status/audit commits
latest visible commits: 2ea9a601 Record integration hold status; b40f800b Record integration hold status; e194c05f Refresh independent audit status
branch sample: main...origin/main [ahead 631, behind 68]
tracked dirty rows: 294
total status rows including untracked: 10730
git diff --shortstat: 294 files changed, 139011 insertions(+), 16713 deletions(-)
tmux sessions: 166
recent accepted implementation distance: 214 commits since b75226d1, with recent history dominated by audit/status/dependency coordination commits
root run by this audit: not started; an active no-argument root harness was already running and the tree was not stable enough for an audit-owned aggregate
```

## Findings

1. **Critical - the current worktree is still not an acceptable aggregate
   verification or lane-acceptance target.**
   - Paths: `progress.md:39`, `progress.md:52` through `progress.md:69`,
     `audits/integration-status.md:5` through `audits/integration-status.md:77`,
     `lanes/*/lane-status.json`, `.tmux-team/`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:48`, and `goal.md:49` require small committed slices, meaningful
     verification, cleanup, and honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved during this audit, the tree has 294 tracked dirty
     rows and 10,730 total status rows, the diff spans 294 tracked files, and
     `tmux list-sessions` reports 166 sessions. Recent history is status/audit
     dominated: the sampled nearest implementation commit `b75226d1` is 214
     commits behind `HEAD`. The latest integration hold says no lane output was
     integrated and dirty lane rows still span every priority lane. A root run
     here measures a moving worker queue, not an accepted snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifests and lane statuses.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:11` through `porting-summary.json:77`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current progress/dashboard fields for denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still advertises source commit `79768df0c427`,
     generated `2026-05-23 23:43:54 UTC`, while current `HEAD` is
     `2ea9a6010fed`. Current lane data has moved materially: Difftastic is
     `395 / 762`, not dashboard `374 / 735`; Gitoxide is `2799 / 2877` with
     lane PHP `5896`, not `2751` and `5634`; LightningCSS is `1890 / 3532`,
     not `1732`; markerPDF is `288 / 338` with PHP `425`, not `280 / 330`
     and `416`; Pandoc is `1144 / 2276` with PHP `290`, not `1061` and
     `278`; rclone is `729 / 1601`, not `698`; Readability PHP is `214`, not
     `204`; Syncthing PHP is `5012`, not `4579`.

3. **High - `progress.md` active-lane handoff labels are stale relative to the
   lane-status files.**
   - Paths: `progress.md:52` through `progress.md:67`,
     `lanes/difftastic/lane-status.json:11`,
     `lanes/esbuild/lane-status.json:11`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/readability/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     active lanes, current owner/session, next task, open blockers, and current
     status.
   - Evidence: `progress.md` still lists older handoffs such as Gitoxide
     "SSH config-options", LightningCSS "trig/math minifier", markerPDF
     "benchmark file-inventory", Pandoc "NativeWriter figure/citation",
     Syncthing "system log", and rclone "VFS Statfs/usage". Current
     `lane-status.json` files instead describe mailmap, gradient target
     prefixing, markerPDF block-type precedence, Pandoc Native structural
     fixtures, Syncthing system paths, rclone VFS rc registry, Readability
     share-class cleanup, and esbuild JSX multiline attribute work. The human
     coordination file is not a reliable current-work source.

4. **High - every primary lane still reports a pending or uncommitted handoff,
   not an accepted committed slice.**
   - Paths: `progress.md:52` through `progress.md:69`,
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
     reviewable committed slices after tests and cleanup.
   - Evidence: `latestCommit` fields still say `pending`, `uncommitted`, `not
     committed`, or lane-batch prose. The dirty tree includes sources, tests,
     fixtures, examples, notes, manifests, and statuses across all lanes, so
     current lane-status claims remain worker handoffs awaiting
     supervisor/integrator acceptance.

5. **High - markerPDF is still over-crediting plan-only external/runtime
   orchestration as native mapped progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:891` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:930`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php:136` through
     `lanes/markerpdf/src/ChunkConversionPlanner.php:143`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require a native PDF-to-structured-content port, no wrapper/shell-out
     progress credit, precise blockers, and honest unported feature marking.
   - Evidence: markerPDF reports `288` mapped units out of `338`, but counted
     items include Pandoc/XeLaTeX helper command planning, `chunk_convert.py`
     f-string shell command assembly, `subprocess.run shell=True`,
     `chunk_convert.sh` `eval/background` lifecycle, Streamlit app launch
     planning, Poetry/package workflows, OCR/model runtime dependency planning,
     and supplied callback handoffs. The PHP planner also emits `command` plus
     `shell_execution: eval`. These can be useful preflight/oracle metadata, but
     they are not a native PDF extraction pipeline and should not count as rich
     port progress.

6. **High - essential optional-library coverage is backlog-only, not
   manifest-backed port progress, and the checked-in dashboard publishes the
   wrong backlog count.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:5` through `dependency-backlog.json:22`,
     `dependency-backlog.json:303` through `dependency-backlog.json:355`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json:1` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require rich native behavior, bounded dependency ports, upstream/spec
     denominators, mapped fixtures, malformed/corrupt cases where relevant, and
     no shell-out progress credit.
   - Evidence: `dependency-backlog.json` has 23 items, with current status split
     `candidate:13, deferred:10`, while `porting.html` still shows `Items: 22`
     and `candidate: 12 | deferred: 10`. None of the support rows has its own
     support-library manifest, activation owner/session, dependency-specific
     upstream/spec denominator, mapped fixture matrix, PHP pass/fail evidence,
     or malformed/corrupt-case coverage. Rich gaps remain for ZIP/DOCX/DOC/EPUB/
     ODT, doctemplates, CSL/citations, math/TeX, PDF text/render/OCR/layout/
     table geometry, source maps, protobuf/BEP wire compatibility,
     tree-sitter-style grammar behavior, Unicode/charset repair, checksums,
     archive streams, glob/pathspec, SQL/storage codecs, and provider metadata
     normalization. Treat whole applications, converter wrappers, model/OCR
     engines, parser generators, and hidden shell-outs as non-progress.

7. **High - near-complete progress percentages overstate accepted native
   upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:34`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:18`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, upstream tests as source of truth, explicit slices, and
     honest blockers.
   - Evidence: the dashboard advertises `97.7%` average and `92-99%` lane
     progress while major lanes still lack full upstream parity or accepted
     root proof. Difftastic has no full Cargo runner; Gitoxide has no full Cargo
     workspace runner; Pandoc has no Haskell Tasty runner; Syncthing has no
     full `go test ./...`; markerPDF has no full benchmark/model/PDF runner and
     counts plan-only boundaries; rclone excludes provider/mount/live-service
     parity; esbuild lacks release-extra `make test-all`; libsqlite lacks full
     all/release permutations. Readability maps `1984 / 1984` upstream Mocha
     checks but only 214 PHP behavior tests; that is useful coverage, not
     complete native parity.

8. **Medium - manifest/status schemas remain non-normalized and hard to
   compare across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2357` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2359`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`,
     `porting-summary.json:11` through `porting-summary.json:77`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require comparable denominator, mapped-test, and PHP pass/fail
     fields.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric, sometimes a
     prose paragraph, and in Dolt a late `total` field is narrative evidence
     rather than a stable denominator. PHP pass values mix behavior tests,
     assertions, PASS cases, and upstream entries. The compact dashboard then
     collapses these into strings, so a reader cannot compute accepted parity or
     compare lanes safely.

9. **Medium - blocker fields still lead with local-green wording while real
   acceptance blockers remain unresolved.**
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
     blockers and explicit marking of hard/unported features.
   - Evidence: many blockers start with "No current", "No focused", or
     "No lane-local" blocker, then later acknowledge pending root verification,
     uncommitted dirty batches, unexecuted full upstream runners, excluded live
     provider/service coverage, model-heavy markerPDF execution, or broad
     hydration/build limits. The unresolved acceptance blocker should be first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required gate was checked before considering any root run. The first broad
sample found a focused lane harness only:

```text
2026-05-24T01:20Z
pgrep -af '^php tools/run-tests\.php( |$)'
1794580 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ... lanes/syncthing/tests/SystemStatusTest.php
```

A later exact no-argument root gate found an active root harness, so I did not
start a duplicate:

```text
2026-05-24T01:22:45Z
pgrep -af '^php tools/run-tests\.php$'
1823123 php tools/run-tests.php

owner evidence:
1823123 claude 1822968 00:16 R+ php tools/run-tests.php
1824119 claude 1823861 00:14 R+ php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
1824433 claude 1824135 00:14 R+ php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
1824899 claude 1824440 00:13 R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
1826531 claude 1761432 00:04 Rs php tools/run-tests.php lanes/markerpdf/tests
```

A final pre-commit recheck found a new active no-argument root harness, so I
still did not start a duplicate:

```text
2026-05-24T01:26:49Z
pgrep -af '^php tools/run-tests\.php$'
1877955 php tools/run-tests.php

owner evidence:
1877955 claude 1877902 00:55 R+ php tools/run-tests.php
1880497 claude 1880135 00:51 R+ php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
```

The root run was not launched by this audit. The tree was also not stable
enough for a meaningful audit-owned no-argument root run: active root/focused
PHP harnesses were present, 166 tmux sessions were present, every lane remained
a dirty handoff, `HEAD` moved during the audit, and the worktree contained 292
to 294 tracked dirty rows plus up to 10,730 total status rows.

## Recommended Next Intervention

Freeze active writers/status publishers and duplicate PHP loops long enough to
create one coherent acceptance window. Accept or reject exactly one narrow
lane-scoped batch, run focused inspection/tests for that batch, run one
serialized no-argument `php tools/run-tests.php` from the same snapshot only
after the exact duplicate-root gate is empty, run `git diff --check`,
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted inputs, then commit or reject. Do not count markerPDF external
app/shell/model orchestration or support-library backlog rows as native port
progress until each has a bounded PHP component, activation gate,
dependency-specific denominator, mapped fixtures, PHP pass/fail evidence, and
malformed/corrupt cases where relevant.
