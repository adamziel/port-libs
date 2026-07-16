<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;

$writeJsonFile = static function (mixed $data): string {
    $path = sys_get_temp_dir() . '/markerpdf-score-verifier-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return $path;
};

$writeRawFile = static function (string $contents): string {
    $path = sys_get_temp_dir() . '/markerpdf-score-verifier-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($path, $contents);

    return $path;
};

$removeFile = static function (string $path): void {
    if (is_file($path)) {
        unlink($path);
    }
};

return [
    'verifies upstream marker score JSON files through current-base dispatch' => static function (TestRunner $t) use ($writeJsonFile, $removeFile): void {
        $path = $writeJsonFile([
            'marker' => [
                'files' => [
                    'multicolcnn.pdf' => ['score' => 0.341],
                    'switch_trans.pdf' => ['score' => 0.401],
                ],
            ],
        ]);

        try {
            $decoded = (new BenchmarkReportVerifier())->verifyScoreFile($path);

            $t->same(0.341, $decoded['marker']['files']['multicolcnn.pdf']['score']);
            $t->same(0.401, $decoded['marker']['files']['switch_trans.pdf']['score']);
        } finally {
            $removeFile($path);
        }
    },
    'rejects marker score JSON files at upstream threshold boundary' => static function (TestRunner $t) use ($writeJsonFile, $removeFile): void {
        $path = $writeJsonFile([
            'marker' => [
                'files' => [
                    'multicolcnn.pdf' => ['score' => 0.34],
                    'switch_trans.pdf' => ['score' => 0.99],
                ],
            ],
        ]);

        try {
            $t->throws(RuntimeException::class, static fn (): array => (new BenchmarkReportVerifier())->verifyScoreFile($path, 'marker'));
        } finally {
            $removeFile($path);
        }
    },
    'verifies upstream table score JSON files through current-base dispatch' => static function (TestRunner $t) use ($writeJsonFile, $removeFile): void {
        $path = $writeJsonFile([
            ['score' => 0.71, 'document' => 'switch_trans.pdf'],
            ['score' => 0.70, 'document' => 'multicolcnn.pdf'],
            ['score' => 0.72, 'document' => 'wordpress-import.pdf'],
        ]);

        try {
            $decoded = (new BenchmarkReportVerifier())->verifyScoreFile($path, 'table');

            $t->same(3, count($decoded));
            $t->same(0.71, $decoded[0]['score']);
            $t->same(0.7, (new BenchmarkReportVerifier())->tableAverageThreshold());
        } finally {
            $removeFile($path);
        }
    },
    'rejects table score JSON files below upstream average boundary' => static function (TestRunner $t) use ($writeJsonFile, $removeFile): void {
        $path = $writeJsonFile([
            ['score' => 0.70],
            ['score' => 0.69],
        ]);

        try {
            $t->throws(RuntimeException::class, static fn (): array => (new BenchmarkReportVerifier())->verifyScoreFile($path, 'table'));
        } finally {
            $removeFile($path);
        }
    },
    'rejects malformed score verifier files before WordPress gates trust them' => static function (TestRunner $t) use ($writeJsonFile, $writeRawFile, $removeFile): void {
        $validMarkerPath = $writeJsonFile([
            'marker' => [
                'files' => [
                    'multicolcnn.pdf' => ['score' => 0.9],
                    'switch_trans.pdf' => ['score' => 0.9],
                ],
            ],
        ]);
        $badJsonPath = $writeRawFile('{"marker": ');
        $scalarJsonPath = $writeRawFile('true');

        try {
            $verifier = new BenchmarkReportVerifier();

            $t->throws(InvalidArgumentException::class, static fn (): array => $verifier->verifyScoreFile('/path/that/does/not/exist.json'));
            $t->throws(InvalidArgumentException::class, static fn (): array => $verifier->verifyScoreFile($badJsonPath));
            $t->throws(InvalidArgumentException::class, static fn (): array => $verifier->verifyScoreFile($scalarJsonPath));
            $t->throws(InvalidArgumentException::class, static fn (): array => $verifier->verifyScoreFile($validMarkerPath, 'unknown'));
        } finally {
            $removeFile($validMarkerPath);
            $removeFile($badJsonPath);
            $removeFile($scalarJsonPath);
        }
    },
];
