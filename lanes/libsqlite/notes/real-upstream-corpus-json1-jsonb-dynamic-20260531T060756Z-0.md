# real-upstream-corpus-json1-jsonb-dynamic-20260531T060756Z-0

Status: blocked by overlap and adjacent rejected JSON regression.

Accepted base for this isolated worker: `cd24ba2f7b741bb89ced6cb6c27264084794565b`.

Assigned domain: real upstream SQLite JSON1/JSONB dynamic corpus from
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Inspected hydrated upstream sources:

- `json101.test`
- `json102.test`
- `json103.test`
- `json104.test`
- `json105.test`
- `json501.test`
- `json502.test`
- `jsonb01.test`

Current accepted lane already contains broad, real-upstream dynamic PHP coverage
for the useful high-yield JSON1/JSONB sections in this micro-slice family:

- `SQLiteJsonDynamicUpstreamCorpusTest.php` covers json101/json102 path type,
  array length, extract, and JSONB extract parity across text and JSONB inputs.
- `SQLiteRealUpstreamJson102JsonbMutationDynamicTest.php`,
  `SQLiteRealUpstreamJson102JsonbMutationTypeDynamicTest.php`,
  `SQLiteRealUpstreamJson102JsonbMutationTailDynamicTest.php`, and
  `SQLiteRealUpstreamJson102MutationDynamicMatrixTest.php` cover json102
  insert/replace/set/remove/type behavior.
- `SQLiteRealUpstreamJson103SelectSqlDynamicTest.php`,
  `SQLiteRealUpstreamJson103ArrayWindowDynamicMegaTest.php`, and
  `SQLiteRealUpstreamJson103ObjectWindowDynamicMegaTest.php` cover json103
  aggregate and window behavior.
- `SQLiteRealUpstreamJson104MergePatchMatrixDynamicTest.php` and
  `SQLiteRealUpstreamJson104QuotedKeyUpdateDynamicTest.php` cover json104
  merge patch and quoted-key update behavior.
- `SQLiteJson105ReverseIndexDynamicCorpusTest.php` and related real-upstream
  JSON1/JSONB dynamic expansion tests cover json105 reverse-index and append
  path behavior.
- `SQLiteRealUpstreamJson501502EscapedStressTest.php`,
  `SQLiteRealUpstreamJson501ControlCharacterDynamicTest.php`,
  `SQLiteRealUpstreamJson501NumericWhitespaceControlDynamicTest.php`, and
  `SQLiteRealUpstreamJson502EscapedLabelDynamicTest.php` cover JSON5 and
  escaped-label behavior from json501/json502.
- `SQLiteRealUpstreamJsonb01RemoveDynamicTest.php`,
  `SQLiteRealUpstreamJsonb01RemoveDynamicCorpusTest.php`, and
  `SQLiteRealUpstreamJsonbDynamicRemovalInspection20260531Test.php` cover
  jsonb01 removal and malformed JSONB behavior.

The lane status at this base also records that the immediately adjacent
`real-json-20260531T054939Z` JSON no-edit mutation candidate was rejected after
accepted-base comparison found four new/worse JSON regressions. Reopening the
same no-edit JSON101/JSON1/JSONB mutation area from this micro-slice would risk
reproducing that rejected surface.

No implementation patch was emitted because the hard throughput floor for
`real-upstream-corpus-*` slices requires at least 1,000 distinct focused
TestRunner PASS cases, 5,000 behavior assertions, a named blocker fix that
unlocks at least 2,000 PASS cases or 10,000 assertions, or guarded denominator
movement. The remaining non-overlapping JSON1/JSONB dynamic candidates found in
this inspection are either already covered by the files above or overlap the
rejected no-edit regression family.

Next larger batch to try:

- Start from the accepted-base known-red JSON cluster named in
  `lanes/libsqlite/lane-status.json`: JSON1/JSONB aggregate regressions,
  JSON502 escaped-path shape, and the rejected JSON no-edit mutation regression.
- Reproduce those failures with the broad comparison command used by the
  integrator, reduce the failing behavior, and fix the shared JSON
  implementation before adding new corpus rows.
- Once the behavior is fixed, admit the next batch as a regression-fix/unlock
  handoff rather than another overlapping dynamic corpus expansion.

Dependency closure: no new support component is needed. The blocker is in the
existing native PHP JSON1/JSONB implementation or accepted-base regression
selection, not in external runner hydration.
