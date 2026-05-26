<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssMinifier
{
    public function minify(string $css): string
    {
        $css = $this->stripComments($css);
        $output = '';
        $quote = null;
        $pendingSpace = false;
        $length = strlen($css);
        $tight = '{}:;,>+~()[]';

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $css[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                if ($pendingSpace && $this->needsSpaceBefore($output, $char)) {
                    $output .= ' ';
                }
                $pendingSpace = false;
                $quote = $char;
                $output .= $char;
                continue;
            }

            if (ctype_space($char)) {
                $pendingSpace = true;
                continue;
            }

            if (str_contains($tight, $char)) {
                $output = rtrim($output);
                $output .= $char;
                $pendingSpace = false;
                continue;
            }

            if ($pendingSpace && $this->needsSpaceBefore($output, $char)) {
                $output .= ' ';
            }
            $pendingSpace = false;
            $output .= $char;
        }

        return str_replace(';}', '}', trim($output));
    }

    private function stripComments(string $css): string
    {
        $output = '';
        $quote = null;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $css[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                continue;
            }

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    return $output;
                }
                $i = $end + 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function needsSpaceBefore(string $output, string $next): bool
    {
        if ($output === '') {
            return false;
        }
        $previous = $output[strlen($output) - 1];
        return (ctype_alnum($previous) || $previous === '_' || $previous === '-')
            && (ctype_alnum($next) || $next === '_' || $next === '-' || $next === '.');
    }
}

