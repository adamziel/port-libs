# Independent Audit - 2026-05-24T07:56Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and the required PHP harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T07:51Z through 2026-05-24T07:56Z
HEAD moved during audit validation: 28df8c698ace -> eeddb549a19b
recent commits: eeddb549 Record integration hold status; 28df8c69 Record integration hold status; 342a5639 Refresh independent audit status
branch divergence: main...origin/main [ahead 766, behind 68]
tracked dirty rows before audit edits: 319 -> 322
default status rows including untracked before audit edits: 15758 -> 15759 -> 15830
git diff --shortstat before audit edits: 319 files changed, 196497 insertions(+), 28511 deletions(-) -> 319 files changed, 196557 insertions(+), 28498 deletions(-) -> 322 files changed, 197247 insertions(+), 28699 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
Initial pgrep -af '^php tools/run-tests\.php( |$)':
3483372 php tools/run-tests.php
3484209 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
3484279 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
3484428 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests
3485596 php tools/run-tests.php lanes/markerpdf/tests/SpanTextNormalizerTest.php ...
3485771 php tools/run-tests.php lanes/pandoc/tests
3485915 php tools/run-tests.php lanes/quadrable/tests

Later pgrep -af '^php tools/run-tests\.php( |$)':
3483372 php tools/run-tests.php

Owner evidence:
3483372 claude 3483324 Sun May 24 07:51:01 2026 01:21 R+ php tools/run-tests.php

Final handoff pgrep -af '^php tools/run-tests\.php( |$)':
3520955 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php ...

Final focused-shard owner sample:
3520955 claude 3520849 Sun May 24 07:55:52 2026 00:44 R+ php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php ...
```

I did not start `php tools/run-tests.php`. The required gate matched an active
no-argument root harness owned by `claude`, so starting another root harness
would have violated the audit instruction. By handoff the no-argument root PID
had exited, but the checkout was still moving and a focused Syncthing PHP shard
was active, so this audit still did not create a new aggregate root anecdote.

Additional checks run by this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
passed
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `audits/integration-status.md:1`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:29` requires small, reviewable slices with
     passing tests; `goal.md:48` requires finished agents to be verified,
     committed, and integrated cleanly.
   - Evidence: recent history still alternates audit/status commits
     (`eeddb549`, `28df8c69`, `342a5639`) rather than accepted lane slices.
     The working tree remains broad and moving: `HEAD` moved from
     `28df8c698ace` to `eeddb549a19b`, tracked dirty rows moved
     `319 -> 322`, default status rows moved `15758 -> 15759 -> 15830`, and
     shortstat changed during audit sampling. Every lane-status file still reports
     `pending`, `uncommitted`, or `not committed` handoff state.

2. **Critical - no acceptable root-harness result exists for the current dirty
   snapshot.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`,
     `audits/integration-status.md:35`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: this audit did not run the root harness because
     `pgrep -af '^php tools/run-tests\.php( |$)'` found active no-argument root
     PID `3483372` owned by `claude`. The latest integration status records a
     clean scratch-clone root pass at committed `HEAD` `2b00e6a2`, but also
     states it does not cover the current dirty aggregate. That evidence cannot
     accept the current working tree.

3. **Critical - `porting.html` and `porting-summary.json` are stale publication
   artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json`.
   - Requirement at risk: `goal.md:45` requires the dashboard to show current
     average progress, denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, work, blocker, and commit.
   - Evidence: the dashboard still reports average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, source snapshot `main 79768df0c427`, and `22`
     dependency items. Current `HEAD` is `eeddb549a19b`, and
     `dependency-backlog.json` has `23` items including
     `pandoc-doctemplates-core`.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require comparable
     upstream denominators, mapped tests, and PHP pass/fail status.
   - Evidence: current manifest/status values versus dashboard include:
     Difftastic `899 total / 618 mapped / 2941 PHP assertions` vs dashboard
     `735 / 374 / 374`; Dolt prose `total` / `613 mapped / 405 pass` vs
     `inventory / 613 / 356`; esbuild `2567 / 386 / 385` vs
     `2567 / 311 / 311`; Gitoxide `2877 / 2877 / 6510` vs
     `2877 / 2751 / 5634`; libsqlite `1589 / 327 / 327` vs
     `1589 / 286 / 286`; LightningCSS `3532 / 2629 / 3807` vs
     `3532 / 1732 / 2197`; markerPDF `368 / 319 / 456` vs
     `330 / 280 / 416`; Pandoc `2276 / 1676 / 331` vs `2276 / 1061 / 278`;
     Quadrable `55 / 55 / 216` vs `55 / 55 / 190`; rclone
     `1601 / 840 / 840` with manifest PHP behavior count `838` vs
     dashboard `1601 / 698 / 698`; Readability `1984 / 1984 / 236` vs
     `1984 / 1984 / 204`; Syncthing `658 / 658 / 6978` vs
     `658 / 658 / 4579`.

5. **High - manifest schema remains non-normalized and Dolt is internally
   inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2475`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2481`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`.
   - Requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:3` requires durable coordination fields.
   - Evidence: Dolt has `benchmarkDenominator.mapped = 613`, but the canonical
     `benchmarkDenominator.total` is a long FIND_IN_SET narrative string.
     The same manifest's latest slice names a newer SOUNDEX boundary, while
     lane status still describes a REVERSE slice and reports `405` PHP passes.
     The manifest reports `nativeImplementation.phpBehaviorTests = 396`.
     These are not machine-comparable denominator/pass fields.

6. **High - `progress.md` active-lane handoffs are stale.**
   - Paths: `progress.md:98`, `progress.md:104` through `progress.md:115`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:44` requires current active lanes, blockers,
     owners/sessions, next task, and percentage estimates.
   - Evidence: the active-lanes table still names older handoffs such as
     Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
     file-inventory, Readability negative header cleanup, Pandoc NativeWriter
     figure/citation, Syncthing system log, rclone VFS Statfs/usage, and
     esbuild automatic JSX key/spread fallback. Current lane-status files now
     report different work: Gitoxide sparse-index mode options,
     LightningCSS SVG stroke dasharray, markerPDF indirect PDF font encoding,
     Readability Firefox Nightly parity, Pandoc DOCX table gridBefore,
     Syncthing ignored-device restart-state, rclone WebDAV COPY descendant,
     Difftastic PHP/Hack method control blocks, Dolt REVERSE/SOUNDEX metadata,
     and esbuild template literal lowering.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:35` says passing tests are not enough and
     requires meaningful upstream parity, fixture parity, edge cases, error
     behavior, docs/examples, and honest blockers.
   - Evidence: dashboard rows report `92%` to `99%`, while lane-status
     blockers still say root aggregate verification is pending for every lane,
     current changes are uncommitted, and full upstream runners remain
     unexecuted or bounded for Gitoxide, Difftastic, markerPDF, Pandoc,
     Syncthing, rclone provider/mount coverage, SQLite all/release
     permutations, and esbuild release-extra `make test-all`.

8. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:4`, `dependency-backlog.json:7`,
     `dependency-backlog.json:111`, `dependency-backlog.json:169`,
     `porting.html:75`.
   - Requirement at risk: this audit requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: the backlog has `23` items, all `candidate` or `deferred`, and
     no active support-library port. Rich-function gaps still need gated,
     manifest-backed work for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy
     DOC/CFB, EPUB, ODT, doctemplates, CSL/citations, math/TeX, PDF text,
     PDF render planning, OCR/layout, table geometry, Unicode repair, charset
     decoding, source maps, tree-sitter subsets, protobuf, checksums/hashes,
     SQL/storage codecs, archive/compression streams, glob/pathspecs, and
     provider metadata normalization.

9. **High - rclone's dependency expansion is too broad and lane-local to count
   as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:8`,
     `lanes/rclone/lane-status.json:9`, `lanes/rclone/lane-status.json:11`,
     `lanes/rclone/lane-status.json:12`, `dependency-backlog.json`.
   - Requirement at risk: support-library expansion must be bounded, gated,
     tested, shared where appropriate, and backed by a dependency-specific
     denominator.
   - Evidence: rclone status now includes lane-local WebDAV XML/PROPFIND/
     PROPPATCH/LOCK/If handling, COPY/MOVE, gzip compression, HTTP middleware,
     auth-proxy directory reads, custom directory templates, URL decoding,
     VFS, and provider metadata. These may be useful rclone slices, but they
     should not be credited as shared ZIP/XML/WebDAV/gzip/provider support
     libraries without separate manifests, malformed cases, activation gates,
     and cross-lane ownership.

10. **High - markerPDF still mixes native extraction evidence with
    external/runtime orchestration plans.**
    - Paths: `lanes/markerpdf/lane-status.json:5`,
      `lanes/markerpdf/lane-status.json:8`,
      `lanes/markerpdf/lane-status.json:9`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:483`.
    - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
      wrappers, bridge calls, shell-outs, or external converter/runtime
      execution as native port progress.
    - Evidence: markerPDF has real native PDF text/font/filter work, but its
      status and manifest still include marker_server, marker_app,
      chunk_convert, convert.py, pdftext dictionary output, Tesseract,
      Ghostscript, OCRMyPDF, Pandoc/XeLaTeX, Poetry, Streamlit,
      FastAPI/Uvicorn, Torch, Surya, Texify, Nougat, and multiprocessing
      lifecycle plans. Those are preflight/oracle metadata unless a bounded
      native PHP component owns the behavior.

11. **Medium - current runner evidence is dominated by focused green anecdotes,
    not accepted aggregate parity.**
    - Paths: `lanes/gitoxide/lane-status.json:10`,
      `lanes/lightningcss/lane-status.json:10`,
      `lanes/markerpdf/lane-status.json:10`,
      `lanes/readability/lane-status.json:10`,
      `lanes/syncthing/lane-status.json:10`.
    - Requirement at risk: `goal.md:37` requires upstream tests as source of
      truth whenever possible, and `goal.md:49` requires repo-wide checks.
    - Evidence: focused lane runs report green assertions, but the active root
      harness is external to this audit and does not yet correspond to a frozen
      accepted dirty snapshot. Until the supervisor freezes writers, accepts one
      lane batch, reruns focused checks, runs one serialized no-argument root
      harness from that same source, and regenerates dashboard artifacts, these
      focused results should remain handoff evidence only.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, tracked/default status rows, shortstat, exact PHP runner state, focused
runner state, Dolt/rclone upstream runner state, dashboard state, and relevant
log mtimes; accept or reject one lane-scoped batch; normalize schema and count
fields for that batch; run focused verification plus `git diff --check`; run
exactly one serialized no-argument `php tools/run-tests.php` from that same
frozen snapshot if the process gate is empty; regenerate `porting.html` and
`porting-summary.json` from the accepted commit; then commit or reject.
