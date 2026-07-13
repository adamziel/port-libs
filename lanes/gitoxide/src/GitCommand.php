<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitCommand
{
    private const SHELL_SCRIPT_CHARS = "|&;<>()$`\\\"' \t\n*?[#~=%";
    private const MANUAL_SPLIT_UNSAFE_CHARS = "\\|&;<>()$`\n*?[#~%";

    /**
     * @param list<string> $args
     * @param array<string, string> $env
     * @param array<string, mixed>|null $context
     */
    private function __construct(
        private readonly string $command,
        private readonly array $args = [],
        private readonly array $env = [],
        private readonly ?array $context = null,
        private readonly bool $useShell = false,
        private readonly bool $quoteCommand = false,
        private readonly ?string $shellProgram = null,
        private readonly bool $allowManualArgSplitting = PHP_OS_FAMILY === 'Windows',
    ) {
    }

    public static function prepare(string $command): self
    {
        return new self($command);
    }

    /**
     * @return array{interpreter: string, args: list<string>}|null
     */
    public static function parseShebang(string $buffer): ?array
    {
        if ($buffer === '') {
            return null;
        }

        $line = self::firstLine($buffer);
        if (!str_starts_with($line, '#!')) {
            return null;
        }

        $line = substr($line, 2);
        $forwardSlash = strrpos($line, '/');
        $backSlash = strrpos($line, '\\');
        if ($forwardSlash === false && $backSlash === false) {
            return null;
        }
        $slash = max($forwardSlash === false ? -1 : $forwardSlash, $backSlash === false ? -1 : $backSlash);

        $space = strpos(substr($line, $slash), ' ');
        if ($space === false) {
            return [
                'interpreter' => self::trimAsciiWhitespace($line),
                'args' => [],
            ];
        }

        $space += $slash;
        $args = self::trimAsciiWhitespace(substr($line, $space + 1));
        if ($args === '') {
            $splitArgs = [];
        } elseif (!self::isValidUtf8($args)) {
            $splitArgs = [$args];
        } else {
            $splitArgs = self::shellWords($args);
        }

        return [
            'interpreter' => self::trimAsciiWhitespace(substr($line, 0, $space)),
            'args' => $splitArgs,
        ];
    }

    /**
     * @return array{interpreter: string, args: list<string>}|null
     */
    public static function extractInterpreter(string $executable): ?array
    {
        $handle = @fopen($executable, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $bytes = fread($handle, 100);
            if ($bytes === false) {
                return null;
            }

            return self::parseShebang($bytes);
        } finally {
            fclose($handle);
        }
    }

    public function commandMayBeShellScript(): self
    {
        return new self(
            $this->command,
            $this->args,
            $this->env,
            $this->context,
            self::containsAnyByte($this->command, self::SHELL_SCRIPT_CHARS),
            $this->quoteCommand,
            $this->shellProgram,
            $this->allowManualArgSplitting,
        );
    }

    public function commandMayBeShellScriptAllowManualArgumentSplitting(): self
    {
        return (new self(
            $this->command,
            $this->args,
            $this->env,
            $this->context,
            $this->useShell,
            $this->quoteCommand,
            $this->shellProgram,
            true,
        ))->commandMayBeShellScript();
    }

    public function commandMayBeShellScriptDisallowManualArgumentSplitting(): self
    {
        return (new self(
            $this->command,
            $this->args,
            $this->env,
            $this->context,
            $this->useShell,
            $this->quoteCommand,
            $this->shellProgram,
            false,
        ))->commandMayBeShellScript();
    }

    public function withShell(): self
    {
        return new self(
            $this->command,
            $this->args,
            $this->env,
            $this->context,
            true,
            $this->quoteCommand,
            $this->shellProgram,
            false,
        );
    }

    public function withoutShell(): self
    {
        return new self(
            $this->command,
            $this->args,
            $this->env,
            $this->context,
            false,
            $this->quoteCommand,
            $this->shellProgram,
            $this->allowManualArgSplitting,
        );
    }

    public function withQuotedCommand(): self
    {
        return new self(
            $this->command,
            $this->args,
            $this->env,
            $this->context,
            $this->useShell,
            true,
            $this->shellProgram,
            $this->allowManualArgSplitting,
        );
    }

    public function withShellProgram(string $program): self
    {
        return new self(
            $this->command,
            $this->args,
            $this->env,
            $this->context,
            $this->useShell,
            $this->quoteCommand,
            $program,
            $this->allowManualArgSplitting,
        );
    }

    public function arg(string $arg): self
    {
        $args = $this->args;
        $args[] = $arg;

        return new self(
            $this->command,
            $args,
            $this->env,
            $this->context,
            $this->useShell,
            $this->quoteCommand,
            $this->shellProgram,
            $this->allowManualArgSplitting,
        );
    }

    /**
     * @param iterable<string> $args
     */
    public function args(iterable $args): self
    {
        $next = $this->args;
        foreach ($args as $arg) {
            $next[] = $arg;
        }

        return new self(
            $this->command,
            $next,
            $this->env,
            $this->context,
            $this->useShell,
            $this->quoteCommand,
            $this->shellProgram,
            $this->allowManualArgSplitting,
        );
    }

    public function env(string $key, string $value): self
    {
        $env = $this->env;
        $env[$key] = $value;

        return new self(
            $this->command,
            $this->args,
            $env,
            $this->context,
            $this->useShell,
            $this->quoteCommand,
            $this->shellProgram,
            $this->allowManualArgSplitting,
        );
    }

    /**
     * @param array{
     *     git_dir?: string|null,
     *     worktree_dir?: string|null,
     *     no_replace_objects?: bool|null,
     *     ref_namespace?: string|null,
     *     literal_pathspecs?: bool|null,
     *     glob_pathspecs?: bool|null,
     *     icase_pathspecs?: bool|null,
     *     stderr?: bool|null
     * } $context
     */
    public function withContext(array $context): self
    {
        return new self(
            $this->command,
            $this->args,
            $this->env,
            $context,
            $this->useShell,
            $this->quoteCommand,
            $this->shellProgram,
            $this->allowManualArgSplitting,
        );
    }

    /**
     * @return array{
     *     program: string,
     *     args: list<string>,
     *     argv: list<string>,
     *     env: array<string, string>,
     *     cwd: null,
     *     stdin: 'null',
     *     stdout: 'piped',
     *     stderr: 'inherit'|'null'
     * }
     */
    public function render(string $defaultShell = 'sh'): array
    {
        $program = $this->command;
        $args = $this->args;

        if ($this->useShell) {
            $split = $this->manualSplitIfSafe();
            if ($split !== null) {
                $program = array_shift($split);
                $args = array_merge($split, $this->args);
            } else {
                $program = $this->shellProgram ?? $defaultShell;
                $command = $this->command;
                if ($this->args !== [] && !str_contains($command, '$@')) {
                    if ($this->quoteCommand) {
                        $command = self::singleQuote($command);
                    }
                    $command .= ' "$@"';
                }

                $args = array_merge(['-c', $command, '--'], $this->args);
            }
        }

        $env = $this->renderEnv();
        $stderr = 'inherit';
        if ($this->context !== null && array_key_exists('stderr', $this->context) && $this->context['stderr'] !== null) {
            $stderr = $this->context['stderr'] ? 'inherit' : 'null';
        }

        return [
            'program' => $program,
            'args' => $args,
            'argv' => array_merge([$program], $args),
            'env' => $env,
            'cwd' => null,
            'stdin' => 'null',
            'stdout' => 'piped',
            'stderr' => $stderr,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function renderEnv(): array
    {
        $env = $this->env;
        if ($this->context === null) {
            return $env;
        }

        if (array_key_exists('git_dir', $this->context) && $this->context['git_dir'] !== null) {
            $env['GIT_DIR'] = (string) $this->context['git_dir'];
        }
        if (array_key_exists('worktree_dir', $this->context) && $this->context['worktree_dir'] !== null) {
            $env['GIT_WORK_TREE'] = (string) $this->context['worktree_dir'];
        }
        if (array_key_exists('no_replace_objects', $this->context) && $this->context['no_replace_objects'] !== null) {
            $env['GIT_NO_REPLACE_OBJECTS'] = $this->context['no_replace_objects'] ? '1' : '0';
        }
        if (array_key_exists('ref_namespace', $this->context) && $this->context['ref_namespace'] !== null) {
            $env['GIT_NAMESPACE'] = (string) $this->context['ref_namespace'];
        }
        if (array_key_exists('literal_pathspecs', $this->context) && $this->context['literal_pathspecs'] !== null) {
            $env['GIT_LITERAL_PATHSPECS'] = $this->context['literal_pathspecs'] ? '1' : '0';
        }
        if (array_key_exists('glob_pathspecs', $this->context) && $this->context['glob_pathspecs'] !== null) {
            if ($this->context['glob_pathspecs']) {
                $env['GIT_GLOB_PATHSPECS'] = '1';
            } else {
                $env['GIT_NOGLOB_PATHSPECS'] = '1';
            }
        }
        if (array_key_exists('icase_pathspecs', $this->context) && $this->context['icase_pathspecs'] !== null) {
            $env['GIT_ICASE_PATHSPECS'] = $this->context['icase_pathspecs'] ? '1' : '0';
        }

        return $env;
    }

    /**
     * @return list<string>|null
     */
    private function manualSplitIfSafe(): ?array
    {
        if (!$this->allowManualArgSplitting) {
            return null;
        }
        if (self::containsAnyByte($this->command, self::MANUAL_SPLIT_UNSAFE_CHARS)) {
            return null;
        }
        if (!self::isValidUtf8($this->command)) {
            return null;
        }

        try {
            $split = self::shellWords($this->command);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $split === [] ? null : $split;
    }

    private static function firstLine(string $buffer): string
    {
        $position = strpos($buffer, "\n");
        $line = $position === false ? $buffer : substr($buffer, 0, $position);

        return str_ends_with($line, "\r") ? substr($line, 0, -1) : $line;
    }

    private static function trimAsciiWhitespace(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B");
    }

    private static function isValidUtf8(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }

    private static function containsAnyByte(string $value, string $bytes): bool
    {
        return strpbrk($value, $bytes) !== false;
    }

    /**
     * @return list<string>
     */
    private static function shellWords(string $input): array
    {
        $words = [];
        $current = '';
        $quote = null;
        $hasCurrent = false;
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if ($quote === null && ($char === ' ' || $char === "\t")) {
                if ($hasCurrent) {
                    $words[] = $current;
                    $current = '';
                    $hasCurrent = false;
                }
                continue;
            }

            if ($char === "'" && $quote !== '"') {
                $quote = $quote === "'" ? null : "'";
                $hasCurrent = true;
                continue;
            }

            if ($char === '"' && $quote !== "'") {
                $quote = $quote === '"' ? null : '"';
                $hasCurrent = true;
                continue;
            }

            if ($char === '\\' && $quote !== "'" && $i + 1 < $length) {
                $current .= $input[++$i];
                $hasCurrent = true;
                continue;
            }

            $current .= $char;
            $hasCurrent = true;
        }

        if ($quote !== null) {
            throw new \InvalidArgumentException('Command contains an unterminated quote');
        }

        if ($hasCurrent) {
            $words[] = $current;
        }

        return $words;
    }

    private static function singleQuote(string $input): string
    {
        return "'" . str_replace("'", "'\\''", $input) . "'";
    }
}
