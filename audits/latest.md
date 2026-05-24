# Independent Audit - 2026-05-24T15:39Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `06a6a69e Refresh independent audit status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 15:35:05, 15:35:32, 15:37:13, 15:39:26
observed HEAD: 06a6a69e2b95
recent history: 06a6a69e Refresh independent audit status; c959e048 Refresh independent audit status; 508e35d0 Refresh independent audit status; 47b35a65 Record integration hold status
tracked dirty files: 330
default status rows including untracked: 19225 -> 19226 -> 19226 -> 19288
git diff --shortstat: 330 files changed, 256942 insertions(+), 32225 deletions(-) -> 330 files changed, 256945 insertions(+), 32223 deletions(-) -> 330 files changed, 256976 insertions(+), 32223 deletions(-) -> 330 files changed, 257567 insertions(+), 32327 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
15:35:05Z pgrep -af '^php tools/run-tests\.php$': no rows
15:35:32Z pgrep -af '^php tools/run-tests\.php$': 823066 php tools/run-tests.php
15:35:32Z ps -o pid,user,etime,command -p 823066: 823066 claude 00:22 php tools/run-tests.php
15:37:13Z pgrep -af '^php tools/run-tests\.php$': no rows
15:39:26Z pgrep -af '^php tools/run-tests\.php$': 841424 php tools/run-tests.php
15:39:26Z ps -o pid,user,etime,command -p 841424: 841424 claude 01:54 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
occupied during the audit, then cleared, then was occupied again. The checkout
kept changing throughout. A root result from this source would not be attributable to a frozen
manifest/status/dashboard snapshot.

Current manifest/status sample versus the published dashboard:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 990/1203, status 3480 pass     3245 pass, 851/1077
dolt          status 432 pass, manifest php 430       425 pass, 613/613
esbuild       452 pass, 452/2567 mapped               429 pass, 429/2567
gitoxide      status 7363 pass, 2877/2877 mapped      7152 pass, 2877/2877
libsqlite     365 pass, 365/1589 mapped               348 pass, 349/1589
LightningCSS  4183 pass, 2865/3548 mapped             4065 pass, 2765/3548
markerPDF     503 pass, 366/415 mapped                484 pass, 347/396
pandoc        status 380 pass, manifest 2065/2276     362 pass, 1891/2276
quadrable     243 pass, 55/55 mapped                  232 pass, 55/55
rclone        953 pass, 953/1601 mapped               906 pass, 906/1601
readability   3703 assertions, 1984/1984 mapped       3545 pass, 1984/1984
syncthing     8352 pass, 658/658 mapped               7902 pass, 658/658
```

## Findings

1. **Critical - there is still no valid root acceptance checkpoint.**
   - Paths: `tools/run-tests.php`, `progress.md:50`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the user also forbids duplicate
     no-argument root harnesses.
   - Evidence: the exact root gate matched PID `823066` owned by `claude`
     running `php tools/run-tests.php` at 15:35:32Z, cleared at 15:37:13Z,
     then matched PID `841424` owned by `claude` at 15:39:26Z. The checkout
     still moved from `256942` to `257567` insertions during this audit. No
     audit-owned root run was started.

2. **Critical - the repo remains a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:50`, `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:44`, and `goal.md:48`
     require small reviewable commits, precise progress, and verified agent
     handoffs.
   - Evidence: tracked dirty files stayed at `330`, default status rows moved
     `19225 -> 19288`, and shortstat changed four times from `256942`
     insertions to `257567` insertions. Recent history is audit/integration-status churn, and
     every sampled lane still says `pending`, `uncommitted`, or defers commit
     ownership to the supervisor/integrator.

3. **High - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:38`, `porting.html:56-67`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:45` requires the dashboard to show
     current denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: the dashboard still publishes snapshot `main 89260857cc71`
     generated `2026-05-24 12:29:46 UTC`, while observed `HEAD` is
     `06a6a69e2b95`. Examples: markerPDF is now `366/415` with `503` pass
     while the dashboard says `347/396` with `484`; Pandoc status now reports
     `380` pass and manifest `2065/2276` while the dashboard says `362` and
     `1891/2276`; rclone is now `953/1601` while the dashboard says `906/1601`.

4. **High - manifest/status ledgers are internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2574`,
     `lanes/dolt/lane-status.json:6`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:1167`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:1177`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:6`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:339`,
     `lanes/rclone/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:24-25`, and
     `goal.md:44-45` require machine-checkable denominators, mapped tests, PHP
     pass/fail, blockers, and latest commit.
   - Evidence: Dolt's manifest still records `phpBehaviorTests: 430` while
     status reports `432` pass. Pandoc status moved to `380` pass while the
     manifest still has no `phpBehaviorTests` ledger field in the common
     sample. Difftastic has duplicate warning text with both current
     `990/1203` and stale `980/1193` counts. LightningCSS still names
     `HEAD c959e048ce0b` in `latestCommit` while the repo is at
     `06a6a69e2b95`.

5. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:391`,
     `dependency-backlog.json:413`, `dependency-backlog.json:629`,
     `porting.html:72-75`.
   - Goal requirement at risk: the latest support-library directives require a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases, bounded install attempts where relevant, and as
     much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. Pandoc's
     DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     templates, citations, math, tables, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression categories are visible as gated rows, but none has a
     support-library manifest, PHP ledger, malformed/corrupt evidence,
     accepted activation record, or bounded install-attempt/ruled-out note.

6. **High - rich dependency-adjacent slices are lane-local and cannot count as support-library progress.**
   - Paths: `lanes/markerpdf/lane-status.json:11-14`,
     `lanes/rclone/lane-status.json:11-14`,
     `lanes/pandoc/lane-status.json:11-14`,
     `dependency-backlog.json:25`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`.
   - Goal requirement at risk: support-library expansion must be bounded,
     gated, tested, and reusable across lanes; external converter wrappers,
     whole apps, and hidden shell-outs are non-progress.
   - Evidence: markerPDF continues adding PDF extraction behavior while
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core` remain inactive. Rclone continues WebDAV mutation
     behavior while `webdav-protocol-core` remains candidate-only. Pandoc's
     RawInline handoff explicitly says it does not activate ZIP/OpenXML/ODT,
     PDF, CSL, PlainMath/MathML, Unicode/charset, or syntax-highlighting rows.

7. **High - Pandoc's original conversion-kernel goal remains unproven despite high percentages.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:10-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:339`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:341`,
     `lanes/pandoc/tests/MarkdownReaderTest.php:2157`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel with
     shared AST plus readers/writers for Markdown, HTML, WXR, EPUB/PDF-oriented
     intermediate forms, and WordPress block-oriented output.
   - Evidence: current Pandoc work is a bounded HTML RawInline slice; full
     Haskell runner parity is unexecuted; TeX RawInline math/ref, PlainMath,
     and MathML are explicitly unclaimed; package/PDF support rows remain
     inactive. `rg -n 'WXR|Wxr|wxr' lanes/pandoc` finds only test prose
     (`MarkdownReaderTest.php:2157`), not a visible WXR reader/writer
     capability.

8. **Medium - near-complete percentages overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` says passing tests are not
     enough and hard features must not be silently skipped.
   - Evidence: the dashboard reports `98.3%` average and most lanes at
     `98-99%`, while every lane remains unaccepted in a dirty moving worktree,
     root verification is pending/non-attributable, several full upstream
     runners are static/bounded/unexecuted, and zero support-library rows are
     active.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, tracked/default status counts, shortstat, exact
root gate `pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency
counts, lane status timestamps, and relevant log mtimes. Accept exactly one
owner-free lane batch at a time, first normalizing manifest/status schema,
counts, and commit fields. Promote support libraries only behind an accepted
base-lane gate or true component blocker, each with its own manifest,
malformed-case evidence, PHP ledger, and bounded install-attempt note.
Regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit, then run one serialized no-argument root harness only if the
exact process gate remains empty on that frozen snapshot.
