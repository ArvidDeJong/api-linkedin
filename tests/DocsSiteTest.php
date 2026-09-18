<?php

/**
 * Guards the rules of the GitHub Pages site in docs/: front matter, Liquid use,
 * single-source facts and the developer credit. Nothing here breaks loudly otherwise.
 */
function docsSitePath(string $path = ''): string
{
    return dirname(__DIR__).'/docs'.($path === '' ? '' : '/'.$path);
}

/**
 * @return array<string, string>
 */
function docsFrontMatter(string $file): array
{
    preg_match('/\A---\n(.*?)\n---\n/s', (string) file_get_contents($file), $match);

    $values = [];
    foreach (explode("\n", $match[1] ?? '') as $line) {
        if (preg_match('/^(\w+):\s*(.*)$/', $line, $pair)) {
            $value = trim($pair[2]);

            // An unquoted ": " makes the YAML invalid, and Jekyll then silently ignores all front matter.
            expect(str_contains($value, ': ') && ! str_starts_with($value, '"'))
                ->toBeFalse(basename($file).': quote the value of '.$pair[1]);

            $values[$pair[1]] = trim($value, '"');
        }
    }

    return $values;
}

/**
 * Site pages; docs/README.md is only for browsing on GitHub and has no front matter.
 *
 * @return array<int, string>
 */
function docsSitePages(): array
{
    $pages = glob(docsSitePath('*.md')) ?: [];

    return array_values(array_filter($pages, fn (string $page) => basename($page) !== 'README.md'));
}

test('every page has a title, a unique description and a unique nav order', function () {
    $pages = docsSitePages();
    $descriptions = [];
    $navOrders = [];

    foreach ($pages as $page) {
        $meta = docsFrontMatter($page);

        expect($meta)->toHaveKeys(['title', 'description', 'nav_order'], basename($page));

        $descriptions[] = $meta['description'];
        $navOrders[] = $meta['nav_order'];
    }

    expect(count($pages))->toBeGreaterThanOrEqual(6);
    expect(array_unique($descriptions))->toHaveCount(count($pages));
    expect(array_unique($navOrders))->toHaveCount(count($pages));
});

test('pages use Liquid only where it is intended', function () {
    foreach (docsSitePages() as $page) {
        if (basename($page) === 'faq.md') {
            continue;
        }

        expect(file_get_contents($page))->not->toMatch('/\{\{|\{%/', basename($page));
    }
});

test('pages link only to pages that exist', function () {
    foreach (docsSitePages() as $page) {
        preg_match_all('/\]\(([a-z-]+\.md)(?:#[^)]*)?\)/', (string) file_get_contents($page), $links);

        foreach ($links[1] as $target) {
            expect(docsSitePath($target))->toBeFile(basename($page).' links to '.$target);
        }
    }
});

test('the FAQ, structured data and llms.txt read from the shared data', function () {
    $faq = (string) file_get_contents(docsSitePath('_data/faq.yml'));

    expect(substr_count($faq, "\n- q: ") + (str_starts_with(ltrim($faq), '- q: ') ? 1 : 0))->toBeGreaterThanOrEqual(6);
    expect(substr_count($faq, '- q: '))->toBe(substr_count($faq, '  a: '));

    expect(file_get_contents(docsSitePath('faq.md')))->toContain('site.data.faq');
    expect(file_get_contents(docsSitePath('llms.txt')))
        ->toContain('permalink: /llms.txt')
        ->toContain('site.data.faq')
        ->toContain('site.pages');
    expect(file_get_contents(docsSitePath('_includes/head_custom.html')))
        ->toContain('"FAQPage"')
        ->toContain('"SoftwareSourceCode"')
        ->toContain('site.data.faq');
});

test('the config holds the package facts and the sitemap plugin', function () {
    expect(file_get_contents(docsSitePath('_config.yml')))
        ->toContain('- jekyll-sitemap')
        ->toContain('name: darvis/api-linkedin')
        ->toContain('company: ARVID.NL')
        ->toContain('url: https://arvid.nl')
        ->not->toContain('footer_content')
        ->toMatch('/exclude:\\n  - README\\.md/');
});

test('the site says it is not affiliated with LinkedIn', function () {
    expect(file_get_contents(docsSitePath('index.md')))->toContain('not affiliated with LinkedIn');
    expect(file_get_contents(docsSitePath('llms.txt')))->toContain('not affiliated with LinkedIn');
});

test('the footer credits ARVID.NL without a personal name', function () {
    $footer = (string) file_get_contents(docsSitePath('_includes/footer_custom.html'));

    expect($footer)->toContain('site.developer.company')
        ->toContain('site.developer.url')
        ->not->toContain('Arvid de Jong')
        ->not->toMatch('/developed by|made by/i');

    expect(file_get_contents(docsSitePath('_includes/head_custom.html')))->not->toContain('"Person"');
});
