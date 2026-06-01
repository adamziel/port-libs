<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$laneDirs = glob($root . '/lanes/*', GLOB_ONLYDIR) ?: [];
sort($laneDirs);

$stringValue = static function (mixed $value, string $fallback = 'pending'): string {
    if ($value === null || $value === '') {
        return $fallback;
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    return $fallback;
};

$shorten = static function (string $value, int $maxLength = 96): string {
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($value === '') {
        return 'none';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') <= $maxLength) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maxLength - 3, 'UTF-8')) . '...';
    }

    if (strlen($value) <= $maxLength) {
        return $value;
    }

    return rtrim(substr($value, 0, $maxLength - 3)) . '...';
};

$firstSentence = static function (string $value): string {
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($value === '') {
        return 'none';
    }

    if (preg_match('/^(.+?[.!?])(?:\s|$)/', $value, $matches) === 1) {
        return $matches[1];
    }

    return $value;
};

$metricSummary = static function (mixed $value): string {
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    if (!is_string($value)) {
        return 'pending';
    }

    if (preg_match('/^\s*([\d,]+(?:\.\d+)?)\b/', $value, $matches) === 1) {
        return $matches[0];
    }

    return 'inventory';
};

$formatCount = static function (mixed $value): string {
    if (is_int($value)) {
        return number_format($value);
    }

    if (is_float($value)) {
        return number_format($value, 0);
    }

    if (!is_string($value)) {
        return '0';
    }

    $value = trim($value);
    if (preg_match('/^\d+$/', $value) === 1) {
        return number_format((int) $value);
    }

    return $value;
};

$numericCount = static function (mixed $value): int {
    if (is_int($value)) {
        return $value;
    }

    if (is_float($value)) {
        return (int) $value;
    }

    if (is_string($value) && preg_match('/^\s*([\d,]+)/', $value, $matches) === 1) {
        return (int) str_replace(',', '', $matches[1]);
    }

    return 0;
};

$shortCommit = static function (string $value) use ($shorten): string {
    $value = trim($value);
    if ($value === '' || $value === 'none') {
        return 'none';
    }

    if (preg_match('/^([0-9a-f]{7,40})\b/i', $value, $matches) === 1) {
        return substr($matches[1], 0, 7);
    }

    return $shorten($value, 18);
};

$blockerSummary = static function (string $value) use ($firstSentence, $shorten): string {
    $normalized = strtolower($value);
    if (str_contains($normalized, 'cargo workspace')) {
        return 'Cargo workspace not run';
    }
    if (str_contains($normalized, 'rust/node/wasm')) {
        return 'Rust/Node/WASM upstream runners not run';
    }
    if (str_contains($normalized, 'seven known failures')) {
        return 'Broad release/all still has 7 known failures';
    }
    if (str_contains($normalized, 'upstream runner parity')) {
        return 'Upstream runner parity unavailable';
    }
    if ((str_contains($normalized, 'no ') || str_contains($normalized, 'blocker: none')) && str_contains($normalized, 'blocker')) {
        return 'No local blocker';
    }

    return $shorten($firstSentence($value), 88);
};

$projectState = static function (string $lane, float $progress, int $phpFail, string $blocker): string {
    $normalizedBlocker = strtolower($blocker);
    if ($lane === 'dolt') {
        return 'Parked';
    }

    if ($phpFail > 0) {
        return number_format($phpFail) . ' open failures';
    }

    if ($progress >= 99.0 && (str_contains($normalizedBlocker, 'not run') || str_contains($normalizedBlocker, 'upstream'))) {
        return 'PHP green, upstream gap';
    }

    if ($progress >= 99.0) {
        return 'Near complete';
    }

    if ($progress >= 95.0) {
        return 'High coverage';
    }

    if ($progress >= 80.0) {
        return 'Active port';
    }

    return 'Needs catch-up';
};

$queueState = static function (string $lane): string {
    return match ($lane) {
        'libsqlite' => 'Primary',
        'lightningcss', 'gitoxide' => 'Active',
        'dolt' => 'Parked',
        default => 'Backlog',
    };
};

$mapPercent = static function (int $mapped, int $denominator): string {
    if ($denominator <= 0) {
        return 'n/a';
    }

    return number_format(($mapped / $denominator) * 100, 1) . '%';
};

$unmappedCount = static function (int $mapped, int $denominator) use ($formatCount): string {
    if ($denominator <= 0) {
        return 'n/a';
    }

    return $formatCount(max(0, $denominator - $mapped));
};

$rows = [];
$totalProgress = 0.0;
foreach ($laneDirs as $dir) {
    $manifestPath = $dir . '/UPSTREAM_TEST_MANIFEST.json';
    $statusPath = $dir . '/lane-status.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
    $status = is_file($statusPath) ? json_decode((string) file_get_contents($statusPath), true) : [];
    if (!is_array($manifest) || !is_array($status)) {
        continue;
    }

    $lane = basename($dir);
    $progress = (float) ($status['estimatedProgress'] ?? 0);
    $totalProgress += $progress;
    $mapped = $metricSummary($manifest['benchmarkDenominator']['mapped'] ?? null);
    $denominator = $metricSummary($manifest['benchmarkDenominator']['total'] ?? null);
    $mappedCount = $numericCount($mapped);
    $denominatorCount = $numericCount($denominator);
    $mapPercentValue = $mapPercent($mappedCount, $denominatorCount);
    $phpPass = $numericCount($status['phpPass'] ?? 0);
    $phpFail = $numericCount($status['phpFail'] ?? 0);
    $library = $stringValue($status['library'] ?? null, $lane);
    $currentWork = $stringValue($status['currentWork'] ?? $manifest['nativeImplementation']['currentSlice'] ?? null, 'none');
    $nextTask = $stringValue($status['nextTask'] ?? $manifest['nextTask'] ?? null, 'none');
    $blocker = $stringValue($status['blocker'] ?? null, 'none');
    $manifestStatus = $stringValue($manifest['benchmarkDenominator']['status'] ?? null, 'pending');
    $displayOrder = [
        'libsqlite' => 1,
        'lightningcss' => 2,
        'gitoxide' => 3,
        'readability' => 4,
        'pandoc' => 5,
        'quadrable' => 6,
        'syncthing' => 7,
        'difftastic' => 8,
        'rclone' => 9,
        'markerpdf' => 10,
        'esbuild' => 11,
        'dolt' => 99,
    ];

    $rows[] = [
        'lane' => $lane,
        'order' => $displayOrder[$lane] ?? 50,
        'library' => $library,
        'priority' => $queueState($lane),
        'queue' => $queueState($lane),
        'state' => $projectState($lane, $progress, $phpFail, $blocker),
        'progressPercent' => number_format($progress, 1),
        'completion' => number_format($progress, 1) . '%',
        'suite' => $shorten($manifestStatus, 72),
        'benchmark' => $shorten($manifestStatus, 72),
        'denominator' => $denominator,
        'mapped' => $mapped,
        'coverage' => $formatCount($mapped) . ' / ' . $formatCount($denominator) . ' (' . $mapPercentValue . ')',
        'mapPercent' => $mapPercentValue,
        'unmapped' => $unmappedCount($mappedCount, $denominatorCount),
        'php' => $formatCount($phpPass) . ' pass / ' . $formatCount($phpFail) . ' fail',
        'phase' => $shorten($stringValue($status['phase'] ?? null, 'planning'), 72),
        'audit' => $shorten($stringValue($status['audit'] ?? null, 'not started'), 72),
        'currentWork' => $shorten($firstSentence($currentWork), 88),
        'nextTarget' => $shorten($nextTask, 64),
        'remainingGate' => $lane === 'dolt' ? 'Parked' : $shorten($blockerSummary($blocker), 72),
        'commit' => $shortCommit($stringValue($status['latestCommit'] ?? null, 'none')),
        'statusPath' => 'lanes/' . rawurlencode($lane) . '/lane-status.json',
        'manifestPath' => 'lanes/' . rawurlencode($lane) . '/UPSTREAM_TEST_MANIFEST.json',
    ];
}

usort($rows, static function (array $left, array $right): int {
    return [$left['order'], $left['library']] <=> [$right['order'], $right['library']];
});

$average = $rows === [] ? 0.0 : $totalProgress / count($rows);
$generated = gmdate('Y-m-d H:i:s') . ' UTC';
$gitValue = static function (string $command): string {
    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
};
$dashboardCommit = $gitValue('git rev-parse HEAD');
$dashboardCommitShort = $dashboardCommit === 'unknown' ? 'unknown' : substr($dashboardCommit, 0, 12);
$sourceCommit = trim((string) getenv('PORT_LIBS_SOURCE_COMMIT'));
if ($sourceCommit === '') {
    $parents = preg_split('/\s+/', $gitValue('git show -s --format=%P HEAD')) ?: [];
    $sourceCommit = count($parents) >= 2 ? $parents[0] : $dashboardCommit;
}
$sourceCommitShort = $sourceCommit === 'unknown' ? 'unknown' : substr($sourceCommit, 0, 12);
$sourceBranch = trim((string) getenv('PORT_LIBS_SOURCE_BRANCH')) ?: $gitValue('git branch --show-current');

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$htmlRows = '';
foreach ($rows as $row) {
    $htmlRows .= '<tr>'
        . '<th scope="row"><a href="' . $escape($row['statusPath']) . '">' . $escape($row['library']) . '</a></th>'
        . '<td>' . $escape($row['priority']) . '</td>'
        . '<td>' . $escape($row['state']) . '</td>'
        . '<td class="num">' . $escape($row['completion']) . '</td>'
        . '<td class="num">' . $escape($row['php']) . '</td>'
        . '<td><a href="' . $escape($row['manifestPath']) . '">' . $escape($row['coverage']) . '</a></td>'
        . '<td class="num">' . $escape($row['unmapped']) . '</td>'
        . '<td>' . $escape($row['remainingGate']) . '</td>'
        . '<td class="commit">' . $escape($row['commit']) . '</td>'
        . '</tr>' . "\n";
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Native PHP Porting Progress</title>
  <style>
    :root { color-scheme: light dark; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    body { margin: 0; padding: 16px; background: Canvas; color: CanvasText; }
    a { color: LinkText; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; min-width: 840px; border-collapse: collapse; font-size: 13px; line-height: 1.3; }
    th, td { border: 1px solid color-mix(in srgb, CanvasText 16%, Canvas); padding: 6px 8px; text-align: left; vertical-align: top; }
    thead th { background: color-mix(in srgb, CanvasText 8%, Canvas); position: sticky; top: 0; }
    tbody th { background: color-mix(in srgb, CanvasText 3%, Canvas); white-space: nowrap; }
    .num { white-space: nowrap; text-align: right; }
    .commit { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: nowrap; }
    td { overflow-wrap: anywhere; }
  </style>
</head>
<body>
  <div class="table-wrap">
    <table aria-label="Native PHP porting progress">
      <thead>
        <tr>
          <th>Project</th>
          <th>Queue</th>
          <th>Status</th>
          <th>Completion</th>
          <th>PHP Gate</th>
          <th>Upstream Coverage</th>
          <th>Unmapped Upstream</th>
          <th>Blocker / Next Gate</th>
          <th>Commit</th>
        </tr>
      </thead>
      <tbody>
{$htmlRows}      </tbody>
    </table>
  </div>
</body>
</html>
HTML;

$markdownCell = static function (string $value): string {
    $value = str_replace(["\r", "\n"], ' ', $value);
    $value = str_replace('|', '\\|', $value);

    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
};

$progressRows = '';
foreach ($rows as $row) {
    $progressRows .= '| '
        . '[' . $markdownCell($row['library']) . '](' . $row['statusPath'] . ') | '
        . $markdownCell($row['priority']) . ' | '
        . $markdownCell($row['state']) . ' | '
        . $markdownCell($row['completion']) . ' | '
        . $markdownCell($row['php']) . ' | '
        . '[' . $markdownCell($row['coverage']) . '](' . $row['manifestPath'] . ') | '
        . $markdownCell($row['unmapped']) . ' | '
        . $markdownCell($row['remainingGate']) . ' | '
        . $markdownCell($row['commit']) . " |\n";
}

$progressMd = <<<MD
| Project | Queue | Status | Completion | PHP Gate | Upstream Coverage | Unmapped Upstream | Blocker / Next Gate | Commit |
| --- | --- | --- | ---: | ---: | --- | ---: | --- | --- |
{$progressRows}
MD;

file_put_contents($root . '/progress.md', $progressMd);
file_put_contents($root . '/porting.html', $html);
file_put_contents($root . '/porting-summary.json', json_encode([
    'generated' => $generated,
    'sourceCommit' => $sourceCommit,
    'sourceCommitShort' => $sourceCommitShort,
    'sourceBranch' => $sourceBranch,
    'dashboardCommit' => $dashboardCommit,
    'dashboardCommitShort' => $dashboardCommitShort,
    'averageProgressPercent' => number_format($average, 1),
    'lanes' => array_map(static function (array $row): array {
        unset($row['statusPath'], $row['manifestPath'], $row['order']);

        return $row;
    }, $rows),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

fwrite(STDOUT, 'Generated table-only progress.md, porting.html, and porting-summary.json with ' . count($rows) . " lanes\n");
