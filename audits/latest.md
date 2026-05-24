# Independent Audit - 2026-05-24T21:56Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `c201145c Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, shell-outs, whole applications,
external converter wrappers, and hidden process launchers are treated as
non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T21:55:45Z
HEAD: c201145ce3f1
recent history: c201145c Refresh independent audit status; 80e90963 Record markerPDF handoff instability; 3cb7c1f2 Refresh evaluator feedback; c7e35a6c Refresh independent audit status; 2ba8794c Record LightningCSS handoff rejection; 952825c8 Refresh independent audit status
default status rows including untracked: 24430
tracked dirty rows: 229
dirty shortstat: 229 files changed, 189884 insertions(+), 23463 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
exact root process gate: `pgrep -af '^php tools/run-tests\.php$'` returned no rows
root run by this audit: not started; the checkout is still moving and too dirty for a meaningful audit-owned aggregate run
JSON validity: `jq empty` passed for all 12 manifests, all 12 lane-status files, `dependency-backlog.json`, and `porting-summary.json`
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every row still has upstreamDenominator null
```

Live dirty manifest/status samples are not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     latest status
difftastic    1233/1349                 3858/0                     pending Objective-C slice
dolt          613/613                   470/0                      not committed query-diff/expression stack
esbuild       233/2567                  233/0                      uncommitted package exports resolver slice
gitoxide      1481/2877                 7678/0                     pending discover/mailmap/actor stack
libsqlite     232/1589                  232/0                      pending JSON aggregate/table-output slice
LightningCSS  3028/3548                 4418/0                     uncommitted @page validation slice
markerPDF     170/78                    275/0                      pending ToUnicode CMap codespace slice
pandoc        2276/2276                 409/0                      pending HTML reader linebreak slice
quadrable     55/115                    263/0                      pending hex/helper plus broad dirty stack
rclone        516/1601                  516/0                      pending WebDAV lock-integrated mutation slice
Readability   1690/1984                 155/0                      uncommitted mixed fixture/cleanup pile
syncthing     658/658                   9265/0                     pending Windows native-model/BEP slice
```

## Findings

1. **Critical - the repository still has no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit
     small reviewable slices with passing verification from a stable snapshot.
   - Evidence: since the previous audit, `HEAD` moved from `c7e35a6c` to
     `c201145c`; untracked-inclusive status grew to `24430`; tracked dirty
     rows remain `229`; dirty shortstat is now `229 files changed, 189884
     insertions(+), 23463 deletions(-)`. All 12 lane manifests and all 12
     lane-status files are still dirty.

2. **Critical - every live lane status still represents unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as native
     implementation progress until supervisor/integrator acceptance and
     verification occur.
   - Evidence: each lane status says pending, uncommitted, not committed, or
     root/integrator acceptance pending. Recent Git history is dominated by
     audit/evaluator/rejection commits, not accepted implementation commits.

3. **Critical - root harness evidence is still not usable for new acceptance.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md`,
     `progress.md`.
   - Goal requirement at risk: repo-wide root verification must be serialized
     and tied to the same frozen snapshot being accepted.
   - Evidence: the required exact gate is currently clear, but the checkout is
     not frozen and has changed since the last audit. Prior integration/audit
     records still identify root-red Difftastic
     `TokenDiffer::isDartLanguage()` and Syncthing
     `syncthing_session_outbound_frames()` blockers. Starting a root run now
     would test a moving, mixed dirty pile rather than an accept/reject batch.

4. **High - `porting.html` remains a stale publication source.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show accepted current
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: `porting.html` still says it is a verified snapshot of source
     commit `0fa9ecafcd10`, while live dirty manifests now report changed
     values such as Difftastic `1233/1349`, Gitoxide `1481/2877`, libsqlite
     `232/1589`, LightningCSS `3028/3548`, markerPDF `170/78`, Pandoc
     `2276/2276`, Quadrable `55/115`, and Syncthing `658/658`.

5. **High - support-library rows are visible but still not first-class port coverage.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require the same granularity
     as lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has `37` rows, `0` active rows, and
     `upstreamDenominator: null` for every row. These are routing notes, not
     accepted support ports. Missing packages are not final blockers until
     bounded install attempts are recorded, but no row currently records such a
     dependency-specific runner ledger.

6. **High - Pandoc rich-format coverage is still over-credited.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion kernel
     with meaningful readers/writers and upstream-backed rich-format support,
     not just fixture routing.
   - Evidence: the manifest reports `2276/2276` mapped while lane status
     reports `409` PHP behavior tests and full Haskell runner parity remains
     unexecuted. DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression are present as gated backlog rows or reuse paths, but
     none has an active support port denominator or pass/fail ledger.

7. **High - several base lanes are crossing inactive support-library boundaries.**
   - Paths: `dependency-backlog.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: rich dependency work should not count as shared
     reusable progress unless a bounded row is activated, tested, and evidenced.
   - Evidence: esbuild package exports resolution is ahead of deferred
     `js-package-resolution-core`; rclone WebDAV lock/mutation work is ahead of
     inactive `webdav-protocol-core` and `xml-html5-dom-core`; Dolt and
     libsqlite JSON work is ahead of inactive `json-json5-document-core` and
     `sql-expression-semantics-core`; Syncthing BEP/session work is ahead of
     inactive `protobuf-wire-core`; Gitoxide protocol/discovery work remains
     ahead of inactive `git-wire-protocol-core` and URL support rows.

8. **High - markerPDF still counts broad orchestration and adjacent dependency planning against a weak denominator.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native
     PDF-to-structured-content extraction pipeline; external runtime planning,
     supplied converter/model callbacks, benchmark archive probing, and
     dependency inspection cannot count as native conversion progress.
   - Evidence: markerPDF maps `170` semantics against only `78` upstream paths.
     The current ToUnicode CMap codespace slice is pending acceptance, while
     broader PDF text dictionaries, page/layout planning, OCR/layout results,
     table geometry, and archive/package concerns remain inactive support rows.

9. **Medium - manifest/status count units remain non-comparable.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: progress percentages must be comparable and
     tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker,
     and commit.
   - Evidence: markerPDF maps more semantics than its total upstream paths
     (`170/78`); Pandoc maps the full denominator while reporting only `409`
     PHP behavior tests; Syncthing and Gitoxide `phpPass` are assertion-like
     counts (`9265`, `7678`), while markerPDF, rclone, readability, and Dolt
     use behavior-case counts.

10. **Medium - Quadrable regressed from a clean denominator story to a mixed unit.**
    - Paths: `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/quadrable/lane-status.json`.
    - Goal requirement at risk: each lane needs a real upstream denominator and
      mapped tests in comparable units.
    - Evidence: the manifest now reports `55/115`, while status describes the
      upstream runner pass plus a broad dirty stack. The original lane tracked
      a compact upstream `check.cpp` suite; the new denominator needs an
      explicit explanation before `55/115` can drive progress estimates.

11. **Medium - broad dirty piles are blocking reviewable-slice acceptance.**
    - Paths: `lanes/readability/lane-status.json`,
      `lanes/quadrable/lane-status.json`, `lanes/dolt/lane-status.json`,
      `lanes/gitoxide/lane-status.json`, `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: prefer small correct slices over broad shallow
      ports and commit small reviewable slices with passing tests.
    - Evidence: Readability advertises ten interleaved fixture/cleanup slices;
      Quadrable lists many unrelated unaccepted proof/transport/store/helper
      slices; Dolt mixes a long query-diff expression stack; Gitoxide mixes
      discover, mailmap, and actor handoffs; Syncthing has a very large route,
      GUI, discovery, BEP, and native-model history in lane metadata.

12. **Medium - Syncthing URL/query needs are still not routed through the shared URL row.**
    - Paths: `dependency-backlog.json`,
      `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: missing bounded support rows become blockers
      once the base lane is ready for or blocked by the next essential rich
      capability.
    - Evidence: Syncthing status includes discovery lookup URL construction and
      query encoding, but `url-percent-encoding-core` lists `rclone`,
      `gitoxide`, `esbuild`, `lightningcss`, and `readability` only. Either add
      Syncthing as a gated consumer with spec/vector expectations, or keep that
      URL evidence explicitly lane-local and non-reusable.

## Root Harness Decision

The exact no-argument root gate was clear at the final sample, but this audit
did not run `php tools/run-tests.php`. The tree is not stable enough: `HEAD`
has moved again, all lane manifests/status files are dirty, and every lane
status describes unaccepted output. A root run now would not satisfy the goal's
acceptance requirement because it would not be tied to a frozen, reviewable
snapshot.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for
two stable polls; select exactly one owner-free reduced batch, preferably the
Difftastic root-red `TokenDiffer::isDartLanguage()` fix or the Syncthing
`syncthing_session_outbound_frames()` fix if still reproducible; run focused
verification; run one serialized no-argument root harness from that same frozen
snapshot; normalize manifest/status count units; then regenerate
`porting.html` and commit or reject the batch. Do not activate support-library
rows until an accepted base-lane gate or concrete blocker requires them.
