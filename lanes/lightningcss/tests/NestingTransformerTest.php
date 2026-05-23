<?php

declare(strict_types=1);

use PortLibs\LightningCSS\NestingTransformer;

return [
    'nesting transformer maps upstream direct parent selector lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{color:#00f}.foo>.bar{color:red}',
            $transformer->lower('.foo { color: blue; & > .bar { color: red; } }')
        );
        $t->same(
            '.foo{color:#00f}.foo.bar{color:red}',
            $transformer->lower('.foo { color: blue; &.bar { color: red; } }')
        );
        $t->same(
            '.foo{color:#00f}.foo.foo{padding:2ch}',
            $transformer->lower('.foo { color: blue; && { padding: 2ch; } }')
        );
    },
    'nesting transformer maps upstream selector-list parent references' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.foo, .bar {
  color: blue;
  & + .baz, &.qux {
    color: red;
  }
}
CSS;

        $t->same(
            '.foo,.bar{color:#00f}:is(.foo,.bar)+.baz,:is(.foo,.bar).qux{color:red}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream implicit descendant and recursive nesting' => static function (TestRunner $t): void {
        $css = <<<'CSS'
figure {
  margin: 0;

  & > figcaption {
    background: hsl(0 0% 0% / 50%);

    & > p {
      font-size: .9rem;
    }
  }
}
CSS;

        $t->same(
            'figure{margin:0}figure>figcaption{background:#00000080}figure>figcaption>p{font-size:.9rem}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream nested pseudo-element selectors' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.foo {
  &::before, &::after {
    background: blue;

    @media screen {
      background: orange;
    }
  }
}
CSS;

        $t->same(
            '.foo:before,.foo:after{background:#00f}@media screen{.foo:before,.foo:after{background:orange}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream attached type selector lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            'article.foo>figure{color:red}',
            $transformer->lower('.foo { &article > figure { color: red; } }')
        );
        $t->same(
            'span:is(div>.foo){background:green}',
            $transformer->lower('div > .foo { &span { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { &h1 { background: green; } }')
        );
        $t->same(
            'h1.foo.bar{background:green}',
            $transformer->lower('.foo.bar { &h1 { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar) .baz{background:green}',
            $transformer->lower('.foo .bar { &h1 .baz { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { @nest h1& { background: green; } }')
        );
        $t->same(
            'html:scope{color:red}.foo:scope{color:#00f}',
            $transformer->lower('&html { color: red; } .foo& { color: blue; }')
        );
    },
    'nesting transformer maps upstream nested media and @nest lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@media (orientation:landscape){.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @media (orientation: landscape) { grid-auto-flow: column; } }')
        );
        $t->same(
            '.foo{color:red}.parent .foo{color:#00f}',
            $transformer->lower('.foo { color: red; @nest .parent & { color: blue; } }')
        );
    },
    'nesting transformer maps upstream parent placement and leading combinators' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{color:red}.bar .foo{color:#00f}',
            $transformer->lower('.foo { color: red; .bar & { color: blue; } }')
        );
        $t->same(
            '.foo{color:red}.foo+.bar+.foo{color:#00f}',
            $transformer->lower('.foo { color: red; + .bar + & { color: blue; } }')
        );
        $t->same(
            '.baz :is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { .baz & { background: green; } }')
        );
        $t->same(
            '.baz :is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { @nest .baz & { background: green; } }')
        );
    },
    'nesting transformer maps upstream nested supports and container lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@supports (foo:bar){.foo{grid-auto-flow:column}.foo>.bar{color:#00f}}',
            $transformer->lower('.foo { display: grid; @supports (foo: bar) { grid-auto-flow: column; & > .bar { color: blue; } } }')
        );
        $t->same(
            '@container (width>=100px){.foo{grid-auto-flow:column}article.foo>figure{color:red}}',
            $transformer->lower('.foo { @container (min-width: 100px) { grid-auto-flow: column; &article > figure { color: red; } } }')
        );
    },
    'nesting transformer maps upstream nested layer lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@layer test{.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @layer test { grid-auto-flow: column; } }')
        );
        $t->same(
            '.foo{display:grid}@layer{.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @layer { grid-auto-flow: column; } }')
        );
    },
    'nesting transformer maps upstream namespace-attached selector lowering' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@namespace "http://example.com/foo";
@namespace toto "http://toto.example.org";

.foo {
  &div {
    color: red;
  }

  &* {
    color: green;
  }

  &|x {
    color: red;
  }

  &*|x {
    color: green;
  }

  &toto|x {
    color: red;
  }
}
CSS;

        $t->same(
            '@namespace "http://example.com/foo";@namespace toto "http://toto.example.org";div.foo{color:red}*.foo{color:green}|x.foo{color:red}*|x.foo{color:green}toto|x.foo{color:red}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer recovers invalid styled-jsx placeholder media query' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.container {
  padding: 3rem;

  @media (max-width: --styled-jsx-placeholder-0__) {
    .responsive {
      color: purple;
    }
  }
}
CSS;

        $t->same(
            '.container{padding:3rem}@media (width<=--styled-jsx-placeholder-0__){.container .responsive{color:purple}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream nested scope boundary lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '@scope(.bar){color:#ff0}',
            $transformer->lower('.foo { @scope (.bar) { color: yellow; } }')
        );
        $t->same(
            '.parent{color:#00f}@scope(.parent>.scope) to (.parent>.scope .limit){:scope .content{color:#ff0}}',
            $transformer->lower('.parent { color: blue; @scope (& > .scope) to (& .limit) { & .content { color: yellow; } } }')
        );
    },
    'wordpress nested block stylesheet lowers without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-query {
  color: blue;

  .wp-block-post-title {
    color: red;

    .is-featured & {
      opacity: .9;
    }
  }

  &:hover .wp-block-post-title {
    text-decoration-color: yellow;
  }

  @media (min-width: 600px) {
    .wp-block-post-title {
      color: blue;
    }
  }

  @scope (& > .wp-block-post-template) to (& .wp-block-post-excerpt) {
    & .wp-block-post-title {
      color: yellow;
    }
  }
}
CSS;

        $t->same(
            '.wp-block-query{color:#00f}.wp-block-query .wp-block-post-title{color:red}.is-featured :is(.wp-block-query .wp-block-post-title){opacity:.9}.wp-block-query:hover .wp-block-post-title{text-decoration-color:#ff0}@media (width>=600px){.wp-block-query .wp-block-post-title{color:#00f}}@scope(.wp-block-query>.wp-block-post-template) to (.wp-block-query>.wp-block-post-template .wp-block-post-excerpt){:scope .wp-block-post-title{color:#ff0}}',
            (new NestingTransformer())->lower($css)
        );
    },
    'wordpress nested pseudo-element selectors lower for block controls' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-button {
  &::before, &::after {
    content: "";
    border-color: yellow;
  }
}
CSS;

        $t->same(
            '.wp-block-button:before,.wp-block-button:after{content:"";border-color:#ff0}',
            (new NestingTransformer())->lower($css)
        );
    },
    'wordpress nested layer rules lower for block theme delivery' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-query {
  @layer theme.blocks {
    & .wp-block-post-title {
      color: yellow;
    }
  }
}
CSS;

        $t->same(
            '@layer theme.blocks{.wp-block-query .wp-block-post-title{color:#ff0}}',
            (new NestingTransformer())->lower($css)
        );
    },
];
