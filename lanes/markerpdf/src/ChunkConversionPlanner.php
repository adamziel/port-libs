<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class ChunkConversionPlanner
{
    private const LAUNCH_DELAY_SECONDS = 5;

    /**
     * Native planning boundary for chunk_convert.py plus chunk_convert.sh.
     *
     * @param array<string, mixed> $environment
     * @return array{input_folder: string, output_folder: string, num_devices: int, num_workers: int, launch_delay_seconds: int, jobs: list<array<string, mixed>>}
     */
    public function planFromEnvironment(?string $inputFolder, ?string $outputFolder, array $environment): array
    {
        if ($inputFolder === null || $inputFolder === '') {
            throw new InvalidArgumentException('Please provide an input folder.');
        }
        if ($outputFolder === null || $outputFolder === '') {
            throw new InvalidArgumentException('Please provide an output folder.');
        }

        $numDevices = $this->requiredPositiveInteger($environment, 'NUM_DEVICES', 'Please set the NUM_DEVICES environment variable.');
        $numWorkers = $this->requiredPositiveInteger($environment, 'NUM_WORKERS', 'Please set the NUM_WORKERS environment variable.');
        $metadataFile = $this->optionalString($environment, 'METADATA_FILE');
        $minLength = $this->optionalPositiveInteger($environment, 'MIN_LENGTH');

        return $this->planDeviceJobs($inputFolder, $outputFolder, $numDevices, $numWorkers, $metadataFile, $minLength);
    }

    /**
     * @return array{input_folder: string, output_folder: string, num_devices: int, num_workers: int, launch_delay_seconds: int, jobs: list<array<string, mixed>>}
     */
    public function planDeviceJobs(
        string $inputFolder,
        string $outputFolder,
        int $numDevices,
        int $numWorkers,
        ?string $metadataFile = null,
        ?int $minLength = null
    ): array {
        if ($inputFolder === '') {
            throw new InvalidArgumentException('Please provide an input folder.');
        }
        if ($outputFolder === '') {
            throw new InvalidArgumentException('Please provide an output folder.');
        }
        if ($numDevices < 1) {
            throw new InvalidArgumentException('NUM_DEVICES must be at least one.');
        }
        if ($numWorkers < 1) {
            throw new InvalidArgumentException('NUM_WORKERS must be at least one.');
        }
        if ($minLength !== null && $minLength < 1) {
            throw new InvalidArgumentException('MIN_LENGTH must be at least one when provided.');
        }

        $jobs = [];
        for ($device = 0; $device < $numDevices; $device++) {
            $argv = [
                'marker',
                $inputFolder,
                $outputFolder,
                '--num_chunks',
                (string) $numDevices,
                '--chunk_idx',
                (string) $device,
                '--workers',
                (string) $numWorkers,
            ];

            if ($metadataFile !== null && $metadataFile !== '') {
                $argv[] = '--metadata_file';
                $argv[] = $metadataFile;
            }
            if ($minLength !== null) {
                $argv[] = '--min_length';
                $argv[] = (string) $minLength;
            }

            $env = [
                'CUDA_VISIBLE_DEVICES' => (string) $device,
                'DEVICE_NUM' => (string) $device,
                'NUM_DEVICES' => (string) $numDevices,
                'NUM_WORKERS' => (string) $numWorkers,
            ];

            $jobs[] = [
                'device_num' => $device,
                'env' => $env,
                'argv' => $argv,
                'command' => $this->commandString($env, $argv),
                'chunk_idx' => $device,
                'num_chunks' => $numDevices,
                'workers' => $numWorkers,
            ];
        }

        return [
            'input_folder' => $inputFolder,
            'output_folder' => $outputFolder,
            'num_devices' => $numDevices,
            'num_workers' => $numWorkers,
            'launch_delay_seconds' => self::LAUNCH_DELAY_SECONDS,
            'jobs' => $jobs,
        ];
    }

    /**
     * @param array<string, mixed> $environment
     */
    private function requiredPositiveInteger(array $environment, string $key, string $missingMessage): int
    {
        $value = $environment[$key] ?? null;
        if ($value === null || $value === '') {
            throw new InvalidArgumentException($missingMessage);
        }

        return $this->positiveInteger($value, $key);
    }

    /**
     * @param array<string, mixed> $environment
     */
    private function optionalPositiveInteger(array $environment, string $key): ?int
    {
        $value = $environment[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return $this->positiveInteger($value, $key);
    }

    private function positiveInteger(mixed $value, string $key): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $number = (int) $value;
        } else {
            throw new InvalidArgumentException($key . ' must be a positive integer.');
        }

        if ($number < 1) {
            throw new InvalidArgumentException($key . ' must be at least one.');
        }

        return $number;
    }

    /**
     * @param array<string, mixed> $environment
     */
    private function optionalString(array $environment, string $key): ?string
    {
        $value = $environment[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param array<string, string> $environment
     * @param list<string> $argv
     */
    private function commandString(array $environment, array $argv): string
    {
        $prefix = [];
        foreach ($environment as $key => $value) {
            if ($key === 'DEVICE_NUM' || $key === 'NUM_DEVICES' || $key === 'NUM_WORKERS') {
                continue;
            }
            $prefix[] = $key . '=' . escapeshellarg($value);
        }

        return trim(implode(' ', array_merge($prefix, array_map('escapeshellarg', $argv))));
    }
}
