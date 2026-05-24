# Independent Audit - 2026-05-24T03:58Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, root-runner process
state, active coordination processes, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T03:58Z
HEAD observed during audit before this audit-only commit: 4dfb6255ccae -> b94f39a70c18 -> a63636e1bc9e
recent pre-audit commits: a63636e1 Record integration hold status; b94f39a7 Record integration hold status; 4dfb6255 Refresh independent audit status
branch sample: main...origin/main [ahead 685, behind 68]
tracked dirty rows: 306
default status rows including untracked: 13355
git diff --shortstat: 306 files changed, 156711 insertions(+), 19203 deletions(-)
root run by this audit: not started
```

Required root-run gate evidence:

```text
initial pgrep -af '^php tools/run-tests\.php( |$)':
109786 php tools/run-tests.php lanes/readability/tests

later pgrep -af '^php tools/run-tests\.php( |$)':
112308 php tools/run-tests.php
113502 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
114028 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests

owner sampling for those earlier PIDs:
ps later returned no rows; the processes had already exited before owner evidence could be captured.

late pgrep -af '^php tools/run-tests\.php( |$)':
155444 php tools/run-tests.php

late owner evidence:
155444 claude 134044 00:18 Rs php tools/run-tests.php

post-edit validation pgrep -af '^php tools/run-tests\.php( |$)':
167584 php tools/run-tests.php

post-edit owner evidence:
167584 claude 134044 00:19 Rs php tools/run-tests.php

active coordination loops still present:
capacity executor, dashboard updater, team watchdog, evaluator, and capacity controller
```

I did not start `php tools/run-tests.php`. The process gate matched active
root-harness invocations during the audit, late in the audit, and again during
post-edit validation. The checkout also moved `HEAD`, dirty counts, and
shortstat during review, so it is not stable enough for a trustworthy
audit-owned aggregate run.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`, and current Git status.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52`.
   - Evidence: `HEAD` moved during this audit from `4dfb6255ccae` through
     `b94f39a70c18` to `a63636e1bc9e`; recent history is dominated by
     audit/status-only integration-hold commits; tracked dirty rows are still
     306; default status rows are 13,355; and the dirty shortstat grew to
     156,711 insertions. Lane statuses still say their latest batches are
     pending or uncommitted rather than accepted supervisor integration slices.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/markerpdf/lane-status.json:10`
     through `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: the gate matched PID `112308` running the no-argument
     root harness plus focused Syncthing/Rclone harnesses earlier, and final
     sampling matched no-argument root PID `155444` owned by `claude`
     (`php tools/run-tests.php`, elapsed `00:18`). Focused lane-green results
     are intake evidence, not accepted aggregate evidence for `a63636e1bc9e`
     plus the current dirty tree.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   coordination artifacts.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json:215` through `porting-summary.json:227`, and
     `dependency-backlog.json:3` through `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52`.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`, while the
     latest pre-audit `HEAD` is `a63636e1bc9e` with all lane manifests/statuses
     dirty. The dashboard reports 22 dependency backlog items, 12 candidates,
     and 10 deferred rows; `dependency-backlog.json` currently has 23 items, 13
     candidates, and 10 deferred rows.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:212`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`, and
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: current Difftastic manifest says 812 / 443 while the dashboard
     says 735 / 374; markerPDF says 349 / 300 with 437 PHP behavior tests while
     the dashboard says 330 / 280 and 416; Rclone manifest/status says 786
     mapped/pass while the dashboard says 698; Syncthing status says 6,100 PHP
     assertions while the dashboard says 4,579 pass; LightningCSS manifest says
     1,910 mapped and 2,447 local assertions while the dashboard says 1,732 and
     2,197.

5. **High - manifest/status schemas remain too free-form for reliable
   dashboard generation.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`,
     `porting-summary.json:11` through `porting-summary.json:25`, and
     `porting.html:56` through `porting.html:67`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: some denominators are integers, others are long prose strings;
     status values are giant concatenated slugs; PHP counts mix behavior tests,
     selected files, assertions, and pass cases; and commit fields still contain
     `pending`, `uncommitted`, truncated prose, or stale hashes. The generator
     cannot safely decide which numbers are accepted, focused-only, static
     inventory, assertion counts, or real upstream runner parity.

6. **High - focused lane-green evidence is being recorded before supervisor
   acceptance and aggregate verification.**
   - Paths: `lanes/gitoxide/lane-status.json:10` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:10` through
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: these status files report focused PHP, lint, examples, and
     `git diff --check` as green while explicitly stating no-argument root
     verification and commit acceptance are pending. The status boundary should
     stay blocked until one frozen snapshot has focused checks, root checks,
     dashboard regeneration, and a reviewable commit.

7. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:28` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:49`,
     `lanes/markerpdf/lane-status.json:5`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40`.
   - Evidence: markerPDF reports 98% progress, 349 counted units, and 300
     mapped semantics even though the denominator includes GitHub Actions,
     benchmark archive, Poetry/lock, package, runtime, Streamlit/FastAPI,
     chunk shell lifecycle, OCR install readiness, model-runtime graph, and
     other plan-only boundaries. Those are useful blockers or preflight
     records, but they should not count as rich native PDF extraction parity.

8. **High - essential optional-library coverage is still backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `dependency-backlog.json:45` through `dependency-backlog.json:90`,
     `dependency-backlog.json:111`, `dependency-backlog.json:382`, and
     `porting.html:93` through `porting.html:114`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: only the 12 base lane manifests exist. Required rich-function
     support such as ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB,
     EPUB/ODF, doctemplates, PDF text, OCR/layout, table geometry, Unicode,
     source maps, protobuf, archive/compression, glob/pathspec, and provider
     metadata normalization remains backlog text without dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases, or accepted activation gates.

9. **High - archive/compression and provider-metadata work is expanding
   lane-locally when it should be shared or gated.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:382` through `dependency-backlog.json:439`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: Rclone has broad VFS ZIP/WebDAV/OneDrive metadata/upload/
     permission work, while markerPDF has benchmark ZIP/package and model
     runtime metadata planning. The backlog says these should become bounded
     shared dependency ports with their own denominators and corrupt-case tests;
     the current lane-local expansion risks duplicating infrastructure and
     inflating progress without shared acceptance evidence.

10. **High - shell/process boundaries are still present and must remain oracle
    or planning only.**
    - Paths: `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/lane-status.json:12`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php:142`, and
      `lanes/markerpdf/tests/ChunkConversionPlannerTest.php:49`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: Gitoxide tests still call `proc_open()` for upstream/local
      oracle checks, and markerPDF records shell lifecycle metadata. That can be
      acceptable fixture-generation or planning evidence, but any behavior that
      depends on those process launches must be excluded from native progress
      until captured as fixtures or reimplemented in PHP.

11. **High - near-complete percentages overstate accepted upstream parity.**
    - Paths: `porting.html:32`, `porting.html:56` through
      `porting.html:67`, `lanes/gitoxide/lane-status.json:5` through
      `lanes/gitoxide/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:5` through
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/rclone/lane-status.json:5` through
      `lanes/rclone/lane-status.json:12`, and
      `lanes/syncthing/lane-status.json:5` through
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
      `goal.md:38`, and `goal.md:40`.
    - Evidence: the dashboard advertises 97.7% average progress and most lanes
      at 98-99%, while major parity remains static-only, focused-only,
      unexecuted, blocked by disk quota, or pending acceptance: Gitoxide full
      Cargo, markerPDF live benchmark/model runner, Rclone live providers/mount,
      Syncthing full `go test ./...`, Pandoc Haskell runner and rich formats,
      Difftastic full Cargo, esbuild release-extra `make test-all`, and SQLite
      all/release permutations.

12. **Medium - `progress.md` is becoming an audit log rather than a concise
    coordination state, and the current-lane details lag lane files.**
    - Paths: `progress.md:37` through `progress.md:47`,
      `lanes/esbuild/lane-status.json:9` through
      `lanes/esbuild/lane-status.json:12`,
      `lanes/rclone/lane-status.json:9` through
      `lanes/rclone/lane-status.json:12`, and
      `lanes/syncthing/lane-status.json:9` through
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: the current snapshot section now carries many repeated audit
      paragraphs, while readers still need to open every lane file to know the
      current accepted work, owner, blocker, and next task. This makes the
      coordination surface less useful even though the underlying lane files are
      highly detailed.

## Required Intervention

Keep the integration hold. The next best intervention has not changed:

1. Freeze writers, dashboard updater, capacity executor/controller, evaluator,
   watchdog, and lane runners.
2. Take two stable polls of `HEAD`, `git status --short --untracked-files=no`,
   default status count, and `git diff --shortstat`.
3. Accept exactly one lane batch, normalize manifest/status schema fields for
   that batch, and run its focused PHP checks plus `git diff --check`.
4. If `pgrep -af '^php tools/run-tests\.php( |$)'` is clear from that frozen
   snapshot, run exactly one serialized no-argument `php tools/run-tests.php`.
5. Regenerate `porting.html` and `porting-summary.json` from the accepted
   commit, then commit or reject the batch.

No `progress.md` update was made because the blocker, audit status, and next
best intervention are unchanged from the prior entry; only the evidence was
refreshed here.

## Tests

Not run. The duplicate-root gate matched active `php tools/run-tests.php`
processes during the audit, including no-argument root PIDs `155444` and
`167584`.
