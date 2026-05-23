<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class MarkerRuntimePlanner
{
    /**
     * Native boundary for marker.logger::configure_logging.
     *
     * @return array{root_level: string, logger_levels: array<string, string>, warning_filters: list<array{action: string, category: string}>}
     */
    public function loggingPlan(): array
    {
        return [
            'root_level' => 'WARNING',
            'logger_levels' => [
                'pdfminer' => 'ERROR',
                'PIL' => 'ERROR',
                'fitz' => 'ERROR',
                'ocrmypdf' => 'ERROR',
            ],
            'warning_filters' => [
                ['action' => 'ignore', 'category' => 'FutureWarning'],
            ],
        ];
    }

    /**
     * Native planning boundary for run_marker_app.py::run.
     *
     * Upstream shells out to Streamlit with a fixed environment overlay. This
     * port records the command and environment without executing Streamlit.
     *
     * @param array<string, string|int|float|bool|null> $environment
     * @return array{command: list<string>, environment: array<string, string>, executes_subprocess: false}
     */
    public function streamlitRunPlan(string $markerProjectDir, array $environment = []): array
    {
        $projectDir = $this->absoluteProjectDir($markerProjectDir);
        $env = [];
        foreach ($environment as $key => $value) {
            if ($value === null) {
                continue;
            }
            $env[(string) $key] = $this->envValue($value);
        }

        $env['IN_STREAMLIT'] = 'true';
        $env['PDFTEXT_CPU_WORKERS'] = '1';

        return [
            'command' => ['streamlit', 'run', $projectDir . DIRECTORY_SEPARATOR . 'marker_app.py'],
            'environment' => $env,
            'executes_subprocess' => false,
        ];
    }

    /**
     * Native boundary for marker_app.py's import-time environment setup.
     *
     * @return array{PYTORCH_ENABLE_MPS_FALLBACK: string, IN_STREAMLIT: string, PDFTEXT_CPU_WORKERS: string}
     */
    public function markerAppImportEnvironment(): array
    {
        return [
            'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
            'IN_STREAMLIT' => 'true',
            'PDFTEXT_CPU_WORKERS' => '1',
        ];
    }

    private function absoluteProjectDir(string $markerProjectDir): string
    {
        $markerProjectDir = trim($markerProjectDir);
        if ($markerProjectDir === '') {
            throw new InvalidArgumentException('Marker project directory must not be empty.');
        }

        $normalized = rtrim($markerProjectDir, "/\\");
        if ($normalized === '') {
            return DIRECTORY_SEPARATOR;
        }

        if ($this->isAbsolutePath($normalized)) {
            return $normalized;
        }

        return getcwd() . DIRECTORY_SEPARATOR . $normalized;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function envValue(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
