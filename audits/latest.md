# Independent Audit - 2026-05-24T21:42Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
recent `audits/integration-status.md`, and recent Git history through
`e4442a13 Record integration intake status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T21:33:00Z -> 2026-05-24T21:42:00Z
HEAD moved during audit: a42ca8aa9efe -> e4442a134da0
recent history: e4442a13 Record integration intake status; a42ca8aa Refresh independent audit status; 748bc929 Record markerPDF handoff rejection; 96cb5683 Record Syncthing handoff rejection; 4c1945f4 Refresh independent audit status; 890f9d22 Refresh dashboard for libsqlite JSON integration; 0fa9ecaf Record libsqlite JSON integration
default status rows including untracked: 23810 -> 23836 -> 23851 -> 23864 -> 23950 -> 24000 -> 24028
tracked status rows: 220 -> 219 -> 225 -> 230
dirty shortstat: 219 files changed, 187051 insertions(+), 23073 deletions(-) -> 230 files changed, 188384 insertions(+), 23244 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every row still has upstreamDenominator null
exact root process gate: pgrep -af '^php tools/run-tests\.php$' returned no rows in earlier sampled gates, then a later pre-commit gate matched active no-argument root PID 2087823
root owner evidence: 2087823 claude 2068781 Rs 00:27 php tools/run-tests.php
focused PHP processes observed: 2034361 and 2063621 Syncthing focused shards, compound focused shards 2065722 (`lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests`), 2065727 (`lanes/rclone/tests lanes/syncthing/tests`), 2065757 (`lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests`), and later focused Quadrable PID 2081295; owner evidence sampled for 2081295 was `2081295 claude 2081189 R+ php tools/run-tests.php lanes/quadrable/tests`
root run by this audit: not started because the checkout moved during sampling, the dirty tree failed the stability gate, and the later exact root gate was occupied
```

Live dirty manifest/status samples, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     latest status
difftastic    1207/1323                 3824/0                     pending
dolt          613/613                   468/0                      not committed
esbuild       231/2567                  231/0                      uncommitted
gitoxide      1479/2877                 7650/0                     pending
libsqlite     225/1589                  225/0                      pending; previous accepted 9784b10c
LightningCSS  3026/3548                 4412/0                     uncommitted
markerPDF     170/78                    274/0                      pending; last accepted 5e46840f
pandoc        2276/2276                 408/0                      pending
quadrable     55/55                     261/0                      pending
rclone        513/1601                  513/0                      pending
Readability   1675/1984                 153/0                      uncommitted
syncthing     658/658                   9249/0                     pending
```

## Findings

1. **Critical - there is still no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `audits/integration-status.md`, `porting.html`,
     `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit
     small reviewable slices with passing verification from a stable snapshot.
   - Evidence: during this audit `HEAD` moved from `a42ca8aa9efe` to
     `e4442a134da0`, default status rows moved from `23810` to `24028`, and
     dirty shortstat moved to `230 files changed, 188384 insertions(+), 23244
     deletions(-)`. The exact no-argument root gate was initially clear, then
     a later pre-commit gate matched active root PID `2087823` owned by
     `claude`. A no-argument root run from this audit would either duplicate an
     active harness or not be tied to a frozen acceptance snapshot.

2. **Critical - current lane status still describes unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as
     accepted native implementation progress.
   - Evidence: every live lane status is `pending`, `uncommitted`, or
     `not committed`, except that libsqlite explicitly points back to prior
     accepted commit `9784b10c` while its new JSON aggregate slice is pending.
     The newest integration commit `e4442a13` records no acceptance; `748bc929`
     rejects markerPDF and `96cb5683` rejects Syncthing.

3. **Critical - root-red blockers are still cross-lane blockers, not lane-local noise.**
   - Paths: `audits/integration-status.md`,
     `lanes/difftastic/tests/TokenDifferTest.php`,
     `lanes/difftastic/src/TokenDiffer.php`,
     `lanes/syncthing/tests/BepSessionTest.php`,
     `lanes/syncthing/*`, `lanes/markerpdf/*`.
   - Goal requirement at risk: one lane cannot be accepted on focused green
     evidence when the required serialized root harness is red.
   - Evidence: markerPDF's coherent searchable-PDF handoff was rejected
     because the integration-owned root run failed first on Difftastic
     `TokenDiffer::isDartLanguage()` and later on Syncthing
     `syncthing_session_outbound_frames()`. The latest integration intake says
     Difftastic has a visible fix but is still broad and actively owned, while
     Syncthing remains a broad active dirty pile.

4. **High - `porting.html` and `porting-summary.json` are stale relative to live manifests.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: both dashboard artifacts still publish source commit
     `0fa9ecafcd10` generated at `2026-05-24 21:19:36 UTC`. They report
     Difftastic `240/586`, Pandoc `619/2276`, rclone `458/2553`, and
     Syncthing `324/658`, while live dirty manifests now report Difftastic
     `1207/1323`, Pandoc `2276/2276`, rclone `506/1601`, and Syncthing
     `658/658`.

5. **High - support-library tracking is visible but still not first-class coverage.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: support libraries require lane-equivalent
     granularity: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has `37` rows, `0` active rows, and
     `upstreamDenominator: null` for every row. The rows are useful routing
     notes, but none is an accepted support-library port.

6. **High - Pandoc rich conversion remains overstated by the `2276/2276` mapped claim.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a conversion kernel with
     Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and WordPress
     output backed by meaningful upstream parity and explicit blockers.
   - Evidence: Pandoc reports `2276/2276` mapped while lane status reports
     `408` PHP behavior tests and full Haskell runner parity remains
     unexecuted. The required rich areas are visible as gated rows or reuse
     paths: DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. None has an active support manifest, denominator,
     PHP ledger, malformed/corrupt evidence, or bounded install-attempt note.

7. **High - current rich lane work crosses inactive support boundaries.**
   - Paths: `dependency-backlog.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency work should not count as reusable
     progress unless the bounded row is activated, tested, and evidenced.
   - Evidence: esbuild now includes bounded package/node_modules resolver work
     while `js-package-resolution-core` is still deferred; rclone WebDAV LOCK
     response/runtime work sits ahead of inactive `webdav-protocol-core` and
     `xml-html5-dom-core`; Dolt/libsqlite JSON work sits ahead of inactive
     `json-json5-document-core` and `sql-expression-semantics-core`;
     Syncthing BEP/session wire work sits ahead of inactive
     `protobuf-wire-core`; Gitoxide protocol/fetch/push evidence sits ahead
     of inactive `git-wire-protocol-core`.

8. **High - markerPDF still has a weak denominator for its claimed PDF breadth.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/PdfTextExtractor.php`,
     `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native
     PDF-to-structured-content extraction pipeline; external runtime planning,
     supplied converter/model callbacks, and benchmark scaffolding cannot
     count as native conversion progress.
   - Evidence: markerPDF maps `170` focused semantics against only `78`
     tracked upstream paths and `0` committed Python unit-test files. The
     searchable-PDF text slices may be useful, but the latest handoff is still
     pending after aggregate root rejection, and the broader PDF page/layout,
     OCR/result, table, CMap/font, and benchmark archive areas remain gated by
     inactive support rows.

9. **Medium - manifest/status count units remain non-comparable.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
     units.
   - Evidence: markerPDF maps more items than its denominator (`170/78`);
     Pandoc maps the whole denominator (`2276/2276`) while reporting `408`
     behavior tests; Syncthing and Gitoxide `phpPass` values are assertion-like
     (`9249`, `7650`) while Dolt/rclone/readability use behavior-case counts.
     Dashboard commit cells still contain prose or truncations such as
     `port-es`, `Port rc`, and `uncommi`.

10. **Medium - Syncthing URL/query support is still not routed through the shared URL row.**
    - Paths: `dependency-backlog.json`,
      `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: a missing bounded support row is a blocker once
      a base lane is ready for or blocked by the next essential rich
      capability.
    - Evidence: Syncthing status and manifest continue to claim global
      discovery lookup URL construction and Go-style query encoding, but
      `url-percent-encoding-core` lists `rclone`, `gitoxide`, `esbuild`,
      `lightningcss`, and `readability` only. Either add Syncthing as a gated
      consumer with spec/vector expectations or keep this evidence explicitly
      lane-local and non-reusable.

11. **Medium - several lanes are broad dirty piles rather than reviewable slices.**
    - Paths: `lanes/gitoxide/*`, `lanes/quadrable/*`,
      `lanes/readability/*`, `lanes/syncthing/*`,
      `audits/integration-status.md`.
    - Goal requirement at risk: prefer small correct slices over broad shallow
      ports and commit small reviewable slices with passing tests.
    - Evidence: Gitoxide says WorkTreeGitDir cannot be isolated from earlier
      unaccepted discovery files; Quadrable lists proof transport, noTrack,
      iterator/checkpoint, raw-LMDB, sync, and other behavior in one dirty
      lane; Readability has seven advertised slices interleaved across the same
      tracked files; Syncthing was rejected for the same scope mismatch and is
      still broad.

12. **Medium - missing-package blockers need bounded install-attempt ledgers before becoming final blockers.**
    - Paths: `dependency-backlog.json`,
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until
      bounded `sudo -n` installs were attempted or ruled out.
    - Evidence: support rows specify expectations but no row records an
      actual dependency-specific suite attempt or install-attempt ledger.
      Pandoc, markerPDF, Difftastic, and Gitoxide all have substantial runner
      blockers, but those blockers are still lane notes rather than
      support-row evidence with bounded package checks.

## Required Next Intervention

Freeze writers, status publishers, dashboard regeneration, and duplicate
focused/root loops until two consecutive polls show unchanged `HEAD`, tracked
status, default status, shortstat, and exact root gate. Treat `9784b10c` as
accepted libsqlite-only evidence, `96cb5683` as a Syncthing rejection,
`748bc929` as a markerPDF rejection, and `e4442a13` as no-acceptance intake
status. The next concrete intervention is to isolate and accept or reject one
owner-free reduced Difftastic `TokenDiffer::isDartLanguage()` fix or Syncthing
`syncthing_session_outbound_frames()` fix whose dirty files match its evidence.

After that, run one serialized no-argument `php tools/run-tests.php` only from
the frozen snapshot with an empty exact process gate, normalize manifest/status
count units, and regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the same accepted commit. Activate or extend
exactly one bounded support-library row only when an accepted base-lane slice
is ready for or blocked by that component, with dependency-specific
upstream/spec denominator, mapped fixtures, PHP pass/fail ledger,
malformed/corrupt cases, and bounded install-attempt notes.
