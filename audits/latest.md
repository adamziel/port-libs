# Independent Audit - 2026-05-24T01:33Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD: f266aa9c9ce4
HEAD movement during audit: 2e6463c5c7d6 -> f266aa9c9ce4 via integration-hold status commit
latest visible commits: f266aa9c Record integration hold status; 2e6463c5 Record integration hold status; 1c81ffa0 Refresh independent audit status
recent history: latest 15 sampled commits are audit/status/integration-hold commits
branch sample: main...origin/main [ahead 634, behind 68]
tracked dirty rows: 296
total status rows including untracked: 10921
git diff --shortstat: 296 files changed, 140024 insertions(+), 16852 deletions(-)
tmux sessions: 166
root run by this audit: not started; an exact no-argument root harness started during final validation, and the tree is not stable enough for an audit-owned aggregate
```

## Findings

1. **Critical - the current worktree is still not an acceptable aggregate
   verification or lane-acceptance target.**
   - Paths: `progress.md:39`, `progress.md:52` through `progress.md:71`,
     `audits/integration-status.md:5` through `audits/integration-status.md:43`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:48`, and `goal.md:49` require small committed slices,
     meaningful verification, cleanup, and honest repo-wide test/static-check
     records.
   - Evidence: current status still shows 296 tracked dirty rows, 10,921 total
     rows including untracked files, and a 296-file diff. `tmux list-sessions`
     reports 166 sessions. The latest integration hold says no lane output was
     integrated and all priority lanes remain active or unsafe. Every sampled
     lane `latestCommit` is still pending, uncommitted, or prose handoff text
     instead of an accepted commit. A root run here would measure a moving
     worker queue, not one accepted snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifests/status files.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:77`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     denominator, mapped-test, PHP pass/fail, WordPress scenario, phase, audit,
     current-work, blocker, and commit data in the dashboard.
   - Evidence: the dashboard still advertises source `79768df0c427`,
     generated `2026-05-23 23:43:54 UTC`, while `HEAD` is `f266aa9c9ce4`.
     Current data has moved: Difftastic is now 396 mapped over a 768-artifact
     inventory, not 374/735; Gitoxide is 2810/2877 with 5935 PHP assertions,
     not 2751/2877 and 5634; markerPDF is 289/339 with 426 PHP checks, not
     280/330 and 416; Pandoc is 1150/2276 with 291 PHP checks, not 1061 and
     278; rclone is 729, not 698; Syncthing is 5062 PHP assertions, not 4579.
     The dependency table says 22 items and 12 candidates, while
     `dependency-backlog.json` has 23 items and 13 candidates.

3. **High - `progress.md` active-lane handoff labels are stale relative to
   current lane-status files.**
   - Paths: `progress.md:58` through `progress.md:69`,
     `lanes/dolt/lane-status.json:11`, `lanes/gitoxide/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     active lanes, current owner/session, next task, open blockers, and current
     status.
   - Evidence: `progress.md` still lists Gitoxide "SSH config-options",
     markerPDF "benchmark file-inventory", Syncthing "system log", rclone
     "VFS Statfs/usage", and esbuild "automatic JSX key/spread" handoffs.
     Current lane-status files instead describe Gitoxide gix-ignore,
     markerPDF markdown transition pnum, Syncthing noauth health, rclone VFS
     rc poll-interval, and esbuild decorated anonymous class-expression work.
     The human coordination file is not a reliable current-work source.

4. **High - every primary lane still reports a pending or uncommitted handoff,
   not an accepted committed slice.**
   - Paths: `lanes/dolt/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`, and the same `latestCommit`
     field in the other lane-status files.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable committed slices after tests and cleanup.
   - Evidence: current lane-status handoffs explicitly defer commit selection
     to the supervisor/integrator or root verification. The dirty tree includes
     source, test, fixture, example, note, manifest, and status edits across all
     lanes. Focused green lane results are useful evidence, but they are not
     accepted progress until integrated from a frozen snapshot.

5. **High - markerPDF is still over-crediting plan-only external/runtime
   orchestration as native mapped progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:639` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:642`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:688` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:694`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:899` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:912`,
     `lanes/markerpdf/lane-status.json:5`, and
     `lanes/markerpdf/src/ChunkConversionPlanner.php:136` through
     `lanes/markerpdf/src/ChunkConversionPlanner.php:143`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require a native PDF-to-structured-content port, no wrapper/shell-out
     progress credit, precise blockers, and explicit marking of hard unported
     features.
   - Evidence: the 289/339 mapped count still includes Pandoc/XeLaTeX helper
     planning, `chunk_convert.py` f-string `subprocess.run shell=True`
     metadata, `chunk_convert.sh` `eval`/background lifecycle planning,
     Streamlit/FastAPI/Uvicorn route/app planning, Poetry/package/runtime
     dependency planning, OCR/Tesseract/Ghostscript installer plans, and
     model-stack preflight. Those are useful preflight/oracle metadata, but
     they are not native PDF extraction or conversion progress.

6. **High - essential optional-library coverage is backlog-only, not
   support-library port progress.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:5` through `dependency-backlog.json:23`,
     `porting.html:71` through `porting.html:78`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require rich native behavior, bounded dependency ports, upstream/spec
     denominators, mapped fixtures, malformed/corrupt cases where relevant, and
     no shell-out progress credit.
   - Evidence: `dependency-backlog.json` has 23 gated items and zero active
     implementations. There are no support-library manifests with activation
     owners, dependency-specific denominators, fixture maps, PHP pass/fail
     evidence, malformed/corrupt coverage, or root verification. Rich gaps
     remain for ZIP/package containers, XML/HTML5, DOCX/OpenXML, legacy CFB
     `.doc`, EPUB/ODT, doctemplates, CSL/citations, math/TeX, PDF text/render/
     OCR/layout/table geometry, source maps, protobuf/BEP wire behavior,
     tree-sitter-style grammar behavior, Unicode/charset repair, checksums,
     archive streams, glob/pathspec, SQL/storage codecs, and provider metadata
     normalization.

7. **High - near-complete progress percentages overstate accepted native
   upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`
     through `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:16`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, upstream tests as source of truth, explicit slices, and
     honest blockers.
   - Evidence: the dashboard advertises 97.7% average and 92-99% lane progress
     while major lanes still lack accepted root proof or full upstream parity.
     Difftastic has no full Cargo runner, Gitoxide has no full workspace Cargo
     runner, Pandoc has no Haskell Tasty runner, Syncthing has no full
     `go test ./...`, markerPDF has no full benchmark/model/PDF runner,
     rclone excludes provider/mount/live-service parity, esbuild excludes
     release-extra `make test-all`, and libsqlite excludes full all/release
     SQLite permutations. Readability maps 1984/1984 upstream checks but only
     214 PHP behavior tests; that is useful coverage, not complete native
     parity.

8. **Medium - manifest/status schemas remain non-normalized and hard to
   compare across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require comparable denominator, mapped-test, and PHP
     pass/fail fields.
   - Evidence: `benchmarkDenominator.total` is numeric in some lanes and a
     narrative string in Difftastic, Pandoc, Quadrable, and Dolt. Dolt's
     `mapped` is 613 executable upstream files while `nativeImplementation`
     says 370 behavior tests and lane status says 372 PASS cases. PHP pass
     values mix behavior-test counts, PASS cases, and assertion counts. The
     compact dashboard collapses these into strings, so readers cannot compute
     accepted parity or compare lanes safely.

9. **Medium - blocker fields still lead with local-green wording while real
   acceptance blockers remain unresolved.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`, and equivalent blocker fields in
     the other lane statuses.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and explicit marking of hard or unported features.
   - Evidence: blockers start with "No current", "No focused", or "No
     lane-local" blocker, then later acknowledge pending root verification,
     uncommitted dirty batches, unexecuted full upstream runners, excluded
     live provider/service coverage, model-heavy markerPDF execution, or broad
     hydration/build limits. The unresolved acceptance blocker should be first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required gate was checked before considering a root run:

```text
2026-05-24T01:29:04Z
pgrep -af '^php tools/run-tests\.php( |$)'
no matches
```

A later validation sample found a focused Syncthing PHP runner, which exited
before owner sampling:

```text
2026-05-24T01:32Z
pgrep -af '^php tools/run-tests\.php( |$)'
1976951 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...

owner evidence:
ps -o pid,user,ppid,etime,stat,args -p 1976951
<exited before owner sample>
```

The final validation sample found an active no-argument root harness, so I did
not start a duplicate:

```text
2026-05-24T01:32Z
pgrep -af '^php tools/run-tests\.php( |$)'
1992342 php tools/run-tests.php

owner evidence:
1992342 claude 1934737 00:14 Rs php tools/run-tests.php
```

The tree also failed the stability gate: broad dirty lane edits, active tmux
sessions, stale dashboard/progress artifacts, a moving `HEAD`, and recent
status/audit-only history mean a no-argument root run would not produce
accepted aggregate evidence.

## Next Intervention

Freeze lane/reseed/runner/status writers, wait for no exact root or focused PHP
runner, confirm `HEAD`, tracked dirty count, total status count, shortstat, and
runner state are unchanged across two polls, then accept exactly one
lane-scoped batch. Rerun that lane's focused verification, run one serialized
`php tools/run-tests.php`, run `git diff --check`, regenerate dashboard
artifacts from the accepted snapshot only, and commit or reject that batch.
