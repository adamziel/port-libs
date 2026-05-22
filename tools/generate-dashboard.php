<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$laneDirs = glob($root . '/lanes/*', GLOB_ONLYDIR) ?: [];
sort($laneDirs);

$rows = [];
$total = 0.0;

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

$auditSummary = static function (string $value) use ($shorten): string {
    if (preg_match('/audited\s+(\d{4}-\d{2}-\d{2})/i', $value, $matches) === 1) {
        return 'audited ' . $matches[1];
    }

    return $shorten($value, 44);
};

$blockerSummary = static function (string $value) use ($firstSentence, $shorten): string {
    $normalized = strtolower($value);
    if (str_contains($normalized, 'full cargo workspace runner not executed')) {
        return 'cargo runner not executed';
    }
    if (str_contains($normalized, 'upstream runner parity')) {
        return 'upstream runner parity unavailable';
    }
    if (str_contains($normalized, 'full upstream runner')) {
        return 'upstream runner not executed';
    }
    if (str_contains($normalized, 'full upstream benchmark runner')) {
        return 'benchmark runner not executed';
    }

    return $shorten($firstSentence($value), 76);
};

$scenarioSummary = static function (mixed $manifestScenario, mixed $statusScenario): string {
    if (is_array($manifestScenario)) {
        $count = count($manifestScenario);

        return $count . ' / ' . $count;
    }

    if (is_string($manifestScenario) && trim($manifestScenario) !== '') {
        return '1 / 1';
    }

    if (is_array($statusScenario)) {
        $count = count($statusScenario);

        return $count . ' / ' . $count;
    }

    if (is_string($statusScenario) && trim($statusScenario) !== '') {
        return 'tracked';
    }

    return 'pending';
};

$shortCommit = static function (string $value): string {
    $value = trim($value);
    if ($value === 'none' || $value === '') {
        return 'none';
    }

    return substr($value, 0, 7);
};

foreach ($laneDirs as $dir) {
    $manifestPath = $dir . '/UPSTREAM_TEST_MANIFEST.json';
    $statusPath = $dir . '/lane-status.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
    $status = is_file($statusPath) ? json_decode((string) file_get_contents($statusPath), true) : [];
    if (!is_array($manifest) || !is_array($status)) {
        continue;
    }

    $progress = (float) ($status['estimatedProgress'] ?? 0);
    $lane = basename($dir);
    $denominatorTotal = $manifest['benchmarkDenominator']['total'] ?? null;
    $mapped = $manifest['benchmarkDenominator']['mapped'] ?? null;
    $denominator = $metricSummary($denominatorTotal);
    $mappedSummary = $metricSummary($mapped);
    $coverage = $mappedSummary . ' / ' . $denominator;
    $manifestStatus = $stringValue($manifest['benchmarkDenominator']['status'] ?? null, 'pending');
    $currentWork = $stringValue(
        $status['currentWork'] ?? $manifest['nativeImplementation']['currentSlice'] ?? null,
        'none'
    );
    $nextTask = $stringValue(
        $status['nextTask'] ?? $manifest['nextTask'] ?? $status['currentWork'] ?? null,
        'none'
    );
    $total += $progress;
    $rows[] = [
        'lane' => $lane,
        'library' => $stringValue($status['library'] ?? null, $lane),
        'suite' => $shorten($manifestStatus, 58),
        'manifestStatus' => $shorten($manifestStatus, 58),
        'source' => $stringValue($manifest['upstream']['url'] ?? null),
        'denominator' => $denominator,
        'mapped' => $mappedSummary,
        'coverage' => $coverage,
        'php' => $stringValue($status['phpPass'] ?? 0, '0') . ' pass / ' . $stringValue($status['phpFail'] ?? 0, '0') . ' fail',
        'wp' => $scenarioSummary($manifest['wordpressScenario'] ?? null, $status['wordpressScenarios'] ?? null),
        'phase' => $shorten($stringValue($status['phase'] ?? null, 'planning'), 58),
        'audit' => $auditSummary($stringValue($status['audit'] ?? null, 'not started')),
        'work' => $shorten($currentWork, 92),
        'next' => $shorten($nextTask, 92),
        'blocker' => $blockerSummary($stringValue($status['blocker'] ?? null, 'none')),
        'commit' => $shortCommit($stringValue($status['latestCommit'] ?? null, 'none')),
        'progress' => $progress,
    ];
}

$average = $rows === [] ? 0.0 : $total / count($rows);
$generated = gmdate('Y-m-d H:i:s') . ' UTC';

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$htmlRows = '';
$summaryRows = [];
foreach ($rows as $row) {
    $lanePath = rawurlencode($row['lane']);
    $manifestLink = 'lanes/' . $lanePath . '/UPSTREAM_TEST_MANIFEST.json';
    $statusLink = 'lanes/' . $lanePath . '/lane-status.json';
    $source = $row['source'] === 'pending'
        ? $escape($row['source'])
        : '<a href="' . $escape($row['source']) . '">upstream</a>';
    $progress = number_format($row['progress'], 1);
    $summaryRows[] = [
        'library' => $row['library'],
        'progressPercent' => $progress,
        'suite' => $row['suite'],
        'benchmark' => $row['manifestStatus'],
        'denominator' => $row['denominator'],
        'mapped' => $row['mapped'],
        'coverage' => $row['coverage'],
        'php' => $row['php'],
        'wordpressScenarios' => $row['wp'],
        'phase' => $row['phase'],
        'audit' => $row['audit'],
        'currentWork' => $row['work'],
        'nextTarget' => $row['next'],
        'blocker' => $row['blocker'],
        'commit' => $row['commit'],
    ];
    $htmlRows .= '<tr>'
        . '<th scope="row">' . $escape($row['library']) . '<br><a href="' . $escape($statusLink) . '">status</a> | <a href="' . $escape($manifestLink) . '">manifest</a></th>'
        . '<td><meter min="0" max="100" value="' . $escape((string) $row['progress']) . '"></meter> <strong>' . $escape($progress) . '%</strong><br>' . $escape($row['suite']) . '</td>'
        . '<td>' . $escape($row['manifestStatus']) . '<br>' . $escape($row['denominator']) . ' benchmark | ' . $source . '</td>'
        . '<td>' . $escape($row['php']) . '<br>' . $escape($row['coverage']) . ' mapped</td>'
        . '<td>' . $escape($row['wp']) . '</td>'
        . '<td>' . $escape($row['phase']) . '</td>'
        . '<td>' . $escape($row['audit']) . '</td>'
        . '<td>' . $escape($row['work']) . '</td>'
        . '<td>' . $escape($row['blocker']) . '</td>'
        . '<td>' . $escape($row['commit']) . '</td>'
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
    body { margin: 0; padding: 24px; background: Canvas; color: CanvasText; }
    a { color: LinkText; }
    header { display: flex; justify-content: space-between; gap: 24px; align-items: baseline; margin-bottom: 12px; }
    h1 { margin: 0; font-size: 24px; }
    .summary { display: flex; gap: 16px; flex-wrap: wrap; color: color-mix(in srgb, CanvasText 72%, Canvas); }
    .note { margin: 0 0 16px; max-width: 960px; color: color-mix(in srgb, CanvasText 72%, Canvas); font-size: 13px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; min-width: 1180px; border-collapse: collapse; font-size: 12px; line-height: 1.35; }
    th, td { border: 1px solid color-mix(in srgb, CanvasText 16%, Canvas); padding: 6px 8px; vertical-align: top; text-align: left; }
    thead th { background: color-mix(in srgb, CanvasText 8%, Canvas); position: sticky; top: 0; z-index: 1; }
    tbody th { font-weight: 650; background: color-mix(in srgb, CanvasText 3%, Canvas); }
    meter { width: 72px; vertical-align: middle; }
    td, th { overflow-wrap: anywhere; }
    td:nth-child(2) { min-width: 160px; }
    td:nth-child(8), td:nth-child(9) { max-width: 220px; }
  </style>
</head>
<body>
  <header>
    <h1>Native PHP Porting Progress</h1>
    <div class="summary">
      <span>Average progress: <strong>{$escape(number_format($average, 1))}%</strong></span>
      <span>Lanes: <strong>{$escape((string) count($rows))}</strong></span>
      <span>Generated: <strong>{$escape($generated)}</strong></span>
    </div>
  </header>
  <p class="note">Rows are intentionally compact for low-context review. Full lane detail remains in the linked status and manifest files; agent-friendly compact JSON is available at <a href="porting-summary.json">porting-summary.json</a>.</p>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Library</th>
        <th>Suite Progress</th>
        <th>Benchmark</th>
        <th>Mapped</th>
        <th>WordPress Scenarios</th>
        <th>Phase</th>
        <th>Audit</th>
        <th>Current Work</th>
        <th>Blocker</th>
        <th>Commit</th>
      </tr>
    </thead>
    <tbody>
{$htmlRows}    </tbody>
  </table>
  </div>
</body>
</html>
HTML;

file_put_contents($root . '/porting.html', $html);
file_put_contents($root . '/porting-summary.json', json_encode([
    'generated' => $generated,
    'averageProgressPercent' => number_format($average, 1),
    'lanes' => $summaryRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDOUT, "Generated porting.html and porting-summary.json with " . count($rows) . " lanes\n");
