<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class ChunkConversionPlanner
{
    private const LAUNCH_DELAY_SECONDS = 5;

    /**
     * Native no-execution boundary for top-level chunk_convert.py.
     *
     * Upstream argparse accepts only input/output folders, resolves the packaged
     * chunk_convert.sh path, then builds one raw shell command string and calls
     * subprocess.run(..., shell=True, check=True). Environment validation is in
     * the shell script, not in the Python wrapper.
     *
     * @param list<string|int|float|bool> $argv Arguments after the script name.
     * @return array<string, mixed>
     */
    public function wrapperRuntimePreflightPlan(array $argv, ?string $scriptPath = null): array
    {
        $tokens = $this->normalizeWrapperArgv($argv);
        $parser = $this->wrapperRuntimeParserPlan();

        if (count($tokens) < 2) {
            $missing = [];
            if (!array_key_exists(0, $tokens)) {
                $missing[] = 'in_folder';
            }
            if (!array_key_exists(1, $tokens)) {
                $missing[] = 'out_folder';
            }

            return $this->wrapperRuntimeErrorPlan(
                $tokens,
                'the following arguments are required: ' . implode(', ', $missing),
                null,
                $missing,
                $parser
            );
        }

        if (count($tokens) > 2) {
            $extra = array_slice($tokens, 2);

            return $this->wrapperRuntimeErrorPlan(
                $tokens,
                'unrecognized arguments: ' . implode(' ', $extra),
                $extra[0] ?? null,
                [],
                $parser
            );
        }

        $scriptPath ??= 'chunk_convert.sh';
        $inputFolder = $tokens[0];
        $outputFolder = $tokens[1];
        $command = $scriptPath . ' ' . $inputFolder . ' ' . $outputFolder;
        $whitespacePaths = $this->containsShellWhitespace($inputFolder) || $this->containsShellWhitespace($outputFolder);
        $metacharacterPaths = $this->containsShellMetacharacter($inputFolder) || $this->containsShellMetacharacter($outputFolder);

        return [
            'schema' => 'markerpdf.chunk_convert_wrapper_preflight.v1',
            'source' => 'sddai/markerPDF chunk_convert.py::main argparse + pkg_resources.resource_filename + subprocess.run',
            'parser' => $parser,
            'argv' => $tokens,
            'parse_args' => [
                'source' => 'argparse.ArgumentParser.parse_args',
                'parse_args_reached' => true,
                'parse_args_success' => true,
                'exit_code' => 0,
                'error_boundary' => null,
                'error_class' => null,
                'error_argument' => null,
                'error_message' => null,
                'missing_required_arguments' => [],
                'blocks_resource_lookup' => false,
                'blocks_subprocess' => false,
            ],
            'arguments' => [
                'in_folder' => $inputFolder,
                'out_folder' => $outputFolder,
                'positionals' => [
                    'in_folder' => $inputFolder,
                    'out_folder' => $outputFolder,
                ],
            ],
            'resource_script' => [
                'source' => 'pkg_resources.resource_filename wrapper',
                'lookup_call' => 'pkg_resources.resource_filename(__name__, "chunk_convert.sh")',
                'package_argument' => '__name__',
                'resource_name' => 'chunk_convert.sh',
                'script_path' => $scriptPath,
                'lookup_reached' => true,
                'blocked' => false,
            ],
            'subprocess' => [
                'source' => 'subprocess.run shell command boundary',
                'call' => 'subprocess.run(cmd, shell=True, check=True)',
                'command' => $command,
                'command_argument_type' => 'string',
                'shell' => true,
                'check' => true,
                'argv_list_used' => false,
                'argument_escaping_applied' => false,
                'quotes_positionals' => false,
                'raw_in_folder_fragment' => $inputFolder,
                'raw_out_folder_fragment' => $outputFolder,
                'blocks_on_nonzero_exit' => true,
                'blocked' => false,
                'blocks_chunk_shell' => false,
            ],
            'shell_boundary' => [
                'env_validation_before_subprocess' => false,
                'chunk_convert_sh_validates_environment_after_subprocess_launch' => true,
                'raw_command_source' => 'f"{script_path} {args.in_folder} {args.out_folder}"',
                'positionals_contain_shell_whitespace' => $whitespacePaths,
                'positionals_contain_shell_metacharacters' => $metacharacterPaths,
                'raw_shell_command_path_hazard' => $whitespacePaths || $metacharacterPaths,
                'native_plan_executes_shell' => false,
            ],
            'blocked_by' => null,
            'next_stage' => 'chunk_convert.sh',
            'review_only' => true,
            'executes_subprocess' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

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
     * @param array<mixed> $argv
     * @return list<string>
     */
    private function normalizeWrapperArgv(array $argv): array
    {
        $tokens = [];
        foreach (array_values($argv) as $token) {
            if (is_bool($token)) {
                $tokens[] = $token ? '1' : '0';
                continue;
            }
            if (!is_string($token) && !is_int($token) && !is_float($token)) {
                throw new InvalidArgumentException('chunk_convert.py argv tokens must be scalar CLI values.');
            }

            $tokens[] = (string) $token;
        }

        return $tokens;
    }

    /**
     * @return array<string, mixed>
     */
    private function wrapperRuntimeParserPlan(): array
    {
        return [
            'description' => 'Convert a folder of PDFs to a folder of markdown files in chunks.',
            'positionals' => [
                'in_folder' => 'Input folder with pdfs.',
                'out_folder' => 'Output folder',
            ],
            'options' => [],
            'allow_abbrev' => true,
            'error_exit_code' => 2,
        ];
    }

    /**
     * @param list<string> $argv
     * @param list<string> $missingRequiredArguments
     * @return array<string, mixed>
     */
    private function wrapperRuntimeErrorPlan(
        array $argv,
        string $message,
        ?string $errorArgument,
        array $missingRequiredArguments,
        array $parser
    ): array {
        return [
            'schema' => 'markerpdf.chunk_convert_wrapper_preflight.v1',
            'source' => 'sddai/markerPDF chunk_convert.py::main argparse + pkg_resources.resource_filename + subprocess.run',
            'parser' => $parser,
            'argv' => $argv,
            'parse_args' => [
                'source' => 'argparse.ArgumentParser.parse_args',
                'parse_args_reached' => true,
                'parse_args_success' => false,
                'exit_code' => 2,
                'error_boundary' => 'argparse-system-exit',
                'error_class' => 'SystemExit',
                'error_argument' => $errorArgument,
                'error_message' => $message,
                'missing_required_arguments' => $missingRequiredArguments,
                'blocks_resource_lookup' => true,
                'blocks_subprocess' => true,
            ],
            'arguments' => null,
            'resource_script' => [
                'source' => 'pkg_resources.resource_filename wrapper',
                'lookup_call' => 'pkg_resources.resource_filename(__name__, "chunk_convert.sh")',
                'package_argument' => '__name__',
                'resource_name' => 'chunk_convert.sh',
                'script_path' => null,
                'lookup_reached' => false,
                'blocked' => true,
            ],
            'subprocess' => [
                'source' => 'subprocess.run shell command boundary',
                'call' => 'subprocess.run(cmd, shell=True, check=True)',
                'command' => null,
                'command_argument_type' => 'string',
                'shell' => true,
                'check' => true,
                'argv_list_used' => false,
                'argument_escaping_applied' => false,
                'quotes_positionals' => false,
                'raw_in_folder_fragment' => $argv[0] ?? null,
                'raw_out_folder_fragment' => $argv[1] ?? null,
                'blocks_on_nonzero_exit' => true,
                'blocked' => true,
                'blocks_chunk_shell' => true,
            ],
            'shell_boundary' => [
                'env_validation_before_subprocess' => false,
                'chunk_convert_sh_validates_environment_after_subprocess_launch' => false,
                'raw_command_source' => 'f"{script_path} {args.in_folder} {args.out_folder}"',
                'positionals_contain_shell_whitespace' => false,
                'positionals_contain_shell_metacharacters' => false,
                'raw_shell_command_path_hazard' => false,
                'native_plan_executes_shell' => false,
            ],
            'blocked_by' => 'parse_args',
            'next_stage' => null,
            'review_only' => true,
            'executes_subprocess' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    private function containsShellWhitespace(string $value): bool
    {
        return preg_match('/\s/', $value) === 1;
    }

    private function containsShellMetacharacter(string $value): bool
    {
        return preg_match('/[;&|`$<>()[\]{}*?!#~\\\\\'"]/', $value) === 1;
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
