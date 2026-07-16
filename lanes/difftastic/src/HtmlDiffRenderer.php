<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class HtmlDiffRenderer
{
    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
    ) {
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, stripCr?: bool, title?: string} $options
     */
    public function renderTokenDiff(string $old, string $new, array $options = []): string
    {
        return $this->renderOps(
            $this->differ->diff($old, $new, $options),
            'difftastic-token-diff',
            'tokens',
            $options['title'] ?? null,
        );
    }

    /**
     * @param array{splitNumbers?: bool, stripCr?: bool, title?: string} $options
     */
    public function renderWordDiff(string $old, string $new, array $options = []): string
    {
        return $this->renderOps(
            $this->differ->diffWords($old, $new, $options),
            'difftastic-word-diff',
            'words',
            $options['title'] ?? null,
        );
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, stripCr?: bool, title?: string} $options
     */
    public function renderSyntaxListDiff(string $old, string $new, array $options = []): string
    {
        $changes = $this->differ->diffSyntaxLists($old, $new, $options);
        $html = '<section class="difftastic-syntax-list-diff" data-difftastic-display="syntax-list">';
        $html .= $this->renderTitle($options['title'] ?? null);

        if ($changes === []) {
            return $html . '<p class="dft-empty">No syntactic changes</p></section>';
        }

        $html .= '<ol class="dft-changes">';
        foreach ($changes as $change) {
            $op = $change['op'];
            $class = match ($op) {
                '+' => 'dft-add',
                '-' => 'dft-del',
                '~' => 'dft-update',
                default => 'dft-change',
            };
            $path = $this->escape($change['path']);
            $html .= '<li class="dft-change ' . $class . '" data-op="' . $this->escape($op) . '" data-path="' . $path . '">';
            $html .= '<code class="dft-path">' . $path . '</code>';

            if ($op === '~') {
                $html .= '<del>' . $this->escape($change['old'] ?? '') . '</del>';
                $html .= '<ins>' . $this->escape($change['new'] ?? '') . '</ins>';
            } else {
                $html .= '<code class="dft-text">' . $this->escape($change['text'] ?? '') . '</code>';
            }

            $html .= '</li>';
        }

        return $html . '</ol></section>';
    }

    /**
     * @param list<array{op:string, text:string}> $ops
     */
    private function renderOps(array $ops, string $sectionClass, string $display, ?string $title): string
    {
        $hasChanges = false;
        $html = '<section class="' . $sectionClass . '" data-difftastic-display="' . $display . '">';
        $html .= $this->renderTitle($title);
        $html .= '<pre class="dft-stream">';

        foreach ($ops as $op) {
            $operation = $op['op'];
            if ($operation !== '=') {
                $hasChanges = true;
            }

            $class = match ($operation) {
                '+' => 'dft-add',
                '-' => 'dft-del',
                default => 'dft-eq',
            };
            $html .= '<span class="' . $class . '" data-op="' . $this->escape($operation) . '">';
            $html .= $this->escape($op['text']);
            $html .= '</span>';
        }

        $html .= '</pre>';
        if (!$hasChanges) {
            $html .= '<p class="dft-empty">No syntactic changes</p>';
        }

        return $html . '</section>';
    }

    private function renderTitle(?string $title): string
    {
        if ($title === null || $title === '') {
            return '';
        }

        return '<h2>' . $this->escape($title) . '</h2>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
