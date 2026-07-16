<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CredentialProgram
{
    public const BUILTIN = 'builtin';
    public const EXTERNAL_NAME = 'external-name';
    public const EXTERNAL_PATH = 'external-path';
    public const EXTERNAL_SHELL_SCRIPT = 'external-shell-script';

    private function __construct(
        public readonly string $kind,
        public readonly string $definition,
        public readonly bool $stderr = true,
    ) {
    }

    public static function builtin(bool $stderr = true): self
    {
        return new self(self::BUILTIN, '', $stderr);
    }

    public static function fromCustomDefinition(string $definition, bool $stderr = true): self
    {
        if (str_starts_with($definition, '!')) {
            return new self(self::EXTERNAL_SHELL_SCRIPT, substr($definition, 1), $stderr);
        }

        $firstSpace = strpos($definition, ' ');
        $program = $firstSpace === false ? $definition : substr($definition, 0, $firstSpace);

        return new self(
            self::isAbsolutePath($program) ? self::EXTERNAL_PATH : self::EXTERNAL_NAME,
            $definition,
            $stderr,
        );
    }

    /**
     * @return list<self>
     */
    public static function platformBuiltins(?string $osFamily = null): array
    {
        $platform = strtolower($osFamily ?? PHP_OS_FAMILY);
        $helper = match ($platform) {
            'darwin', 'mac', 'macos', 'osx' => 'osxkeychain',
            'linux' => 'libsecret',
            'windows', 'win32', 'winnt' => 'manager-core',
            default => null,
        };

        return $helper === null ? [] : [self::fromCustomDefinition($helper)];
    }

    public function suppressStderr(): self
    {
        return new self($this->kind, $this->definition, false);
    }

    public function actionArgument(string $action): string
    {
        $external = $this->kind !== self::BUILTIN;

        return match ($action) {
            'get' => $external ? 'get' : 'fill',
            'store' => $external ? 'store' : 'approve',
            'erase' => $external ? 'erase' : 'reject',
            default => throw new \InvalidArgumentException("Unsupported credential action: {$action}"),
        };
    }

    /**
     * @return list<string>
     */
    public function command(string $action, string $gitProgram = 'git', string $shell = 'sh', bool $windows = false): array
    {
        $actionArg = $this->actionArgument($action);

        return match ($this->kind) {
            self::BUILTIN => [$gitProgram, 'credential', $actionArg],
            self::EXTERNAL_NAME => $this->externalNameCommand($actionArg, $gitProgram, $shell),
            self::EXTERNAL_PATH => $this->externalPathCommand($actionArg, $shell, $windows),
            self::EXTERNAL_SHELL_SCRIPT => $this->scriptCommand($actionArg, $shell),
            default => throw new \LogicException("Unknown credential program kind: {$this->kind}"),
        };
    }

    /**
     * @return array{kind: string, definition: string, stderr: bool}
     */
    public function describe(): array
    {
        return [
            'kind' => $this->kind,
            'definition' => $this->definition,
            'stderr' => $this->stderr,
        ];
    }

    /**
     * @return list<string>
     */
    private function externalNameCommand(string $actionArg, string $gitProgram, string $shell): array
    {
        $command = "{$gitProgram} credential-{$this->definition}";
        if (self::requiresShell($this->definition)) {
            return [$shell, '-c', $command . ' "$@"', '--', $actionArg];
        }

        $parts = self::shellWords($this->definition);
        $name = array_shift($parts) ?? '';

        return array_merge([$gitProgram, 'credential-' . $name], $parts, [$actionArg]);
    }

    /**
     * @return list<string>
     */
    private function externalPathCommand(string $actionArg, string $shell, bool $windows): array
    {
        if (!str_contains($this->definition, ' ')) {
            return [$this->definition, $actionArg];
        }

        if ($windows) {
            return array_merge(self::shellWords($this->definition), [$actionArg]);
        }

        return [$shell, '-c', $this->definition . ' "$@"', '--', $actionArg];
    }

    /**
     * @return list<string>
     */
    private function scriptCommand(string $actionArg, string $shell): array
    {
        if ($this->definition !== '' && !self::requiresShell($this->definition)) {
            $parts = self::shellWords($this->definition);

            return array_merge($parts, [$actionArg]);
        }

        return [$shell, '-c', $this->definition . ' "$@"', '--', $actionArg];
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private static function requiresShell(string $definition): bool
    {
        return preg_match('/[~$`*?{}()<>|;&\n\r]/', $definition) === 1;
    }

    /**
     * @return list<string>
     */
    private static function shellWords(string $input): array
    {
        $words = [];
        $current = '';
        $quote = null;
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if ($quote === null && ($char === ' ' || $char === "\t")) {
                if ($current !== '') {
                    $words[] = $current;
                    $current = '';
                }
                continue;
            }

            if (($char === '"' || $char === "'") && ($quote === null || $quote === $char)) {
                $quote = $quote === null ? $char : null;
                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $input[++$i];
                continue;
            }

            $current .= $char;
        }

        if ($quote !== null) {
            throw new \InvalidArgumentException('Credential helper definition contains an unterminated quote');
        }

        if ($current !== '') {
            $words[] = $current;
        }

        return $words;
    }
}
