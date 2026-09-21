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
 * Every Markdown file in docs/ is a page of the site.
 *
 * @return array<int, string>
 */
function docsSitePages(): array
{
    return glob(docsSitePath('*.md')) ?: [];
}

/**
 * The page without its raw blocks: Liquid leaves those alone, so a Blade example may live there.
 */
function docsPageOutsideRawBlocks(string $page): string
{
    return (string) preg_replace('/\{% raw %\}.*?\{% endraw %\}/s', '', (string) file_get_contents($page));
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

    expect(count($pages))->toBeGreaterThanOrEqual(10);
    expect(array_unique($descriptions))->toHaveCount(count($pages));
    expect(array_unique($navOrders))->toHaveCount(count($pages));
});

test('pages use Liquid only where it is intended', function () {
    foreach (docsSitePages() as $page) {
        if (basename($page) === 'faq.md') {
            continue;
        }

        expect(docsPageOutsideRawBlocks($page))->not->toMatch('/\{\{|\{%/', basename($page));
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

test('the home page links to every other page', function () {
    $home = (string) file_get_contents(docsSitePath('index.md'));

    foreach (docsSitePages() as $page) {
        if (basename($page) === 'index.md') {
            continue;
        }

        expect($home)->toContain(']('.basename($page).')');
    }
});

test('a beginner finds the quick start, troubleshooting and testing pages', function () {
    foreach (['quickstart.md', 'troubleshooting.md', 'testing.md'] as $page) {
        expect(docsSitePath($page))->toBeFile();
    }

    // docs/README.md duplicated the site index and drifted; the site is the one index.
    expect(docsSitePath('README.md'))->not->toBeFile();
});

test('troubleshooting quotes the messages exactly as the package writes them', function () {
    $page = (string) file_get_contents(docsSitePath('troubleshooting.md'));
    $source = '';

    foreach (['Http/Controllers/LinkedInController.php', 'LinkedInManager.php', 'Exceptions/LinkedInNotConnected.php', 'Exceptions/LinkedInConnectionExpired.php'] as $file) {
        $source .= file_get_contents(dirname(__DIR__).'/src/'.$file);
    }

    foreach ([
        'Configure LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET first.',
        'Invalid or expired connection session. Please try again.',
        'Connecting failed: LinkedIn did not accept the authorization. Please try again.',
        'Connecting failed: the LinkedIn profile could not be fetched. Please try again.',
        'Connecting failed: LinkedIn could not be reached. Please try again.',
        'Connecting failed because of an unexpected error. Please try again.',
        'No active LinkedIn connection.',
        'The LinkedIn connection has expired. Please reconnect the account.',
        'No LinkedIn company page given, and none configured (linkedin.organization_urn).',
    ] as $message) {
        expect($source)->toContain($message);
        expect($page)->toContain($message);
    }
});

test('the requirements agree with composer.json everywhere', function () {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true);

    expect($composer['require']['php'] ?? null)->toBe('^8.2');

    // The CI matrix rewrites the Laravel constraint to the version under test; only the
    // committed constraint, the one with alternatives, says what the package supports.
    $laravel = $composer['require']['laravel/framework'] ?? '';

    if (str_contains($laravel, '|')) {
        expect($laravel)->toBe('^11.0|^12.0|^13.0');
    }

    foreach ([docsSitePath('index.md'), docsSitePath('installation.md'), dirname(__DIR__).'/README.md'] as $file) {
        expect(file_get_contents($file))->toContain('PHP 8.2 or higher', 'Laravel 11, 12 or 13');
    }

    expect(file_get_contents(docsSitePath('_config.yml')))->toContain('requires: PHP 8.2+, Laravel 11, 12 or 13');
    expect(file_get_contents(docsSitePath('_data/faq.yml')))->toContain('PHP 8.2 or higher and Laravel 11, 12 and 13');
});

test('the README has the standard sections in the standard order', function () {
    preg_match_all('/^## (.+)$/m', (string) file_get_contents(dirname(__DIR__).'/README.md'), $headings);

    expect($headings[1])->toBe([
        'Features', 'Requirements', 'Installation', 'Who may connect', 'Quick start', 'Documentation',
        'Laravel Boost', 'Testing', 'Changelog', 'Contributing', 'Security', 'License',
    ]);
});

test('the FAQ, structured data and llms.txt read from the shared data', function () {
    $faq = (string) file_get_contents(docsSitePath('_data/faq.yml'));

    expect(substr_count($faq, "\n- q: ") + (str_starts_with(ltrim($faq), '- q: ') ? 1 : 0))->toBeBetween(6, 10);
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

test('the YAML files build, because one bad value fails the whole Pages build', function () {
    // An unquoted ": " makes the YAML invalid. Jekyll then aborts the build and GitHub keeps
    // serving the last version that did build, so the site looks fine while it is months old.
    foreach (['_config.yml', '_data/faq.yml'] as $file) {
        $path = docsSitePath($file);

        if (! is_file($path)) {
            continue;
        }

        $blockIndent = null;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $number => $line) {
            $indent = strlen($line) - strlen(ltrim($line));

            if ($blockIndent !== null) {
                // Inside a > or | block every line is text, whatever it contains.
                if (trim($line) === '' || $indent > $blockIndent) {
                    continue;
                }

                $blockIndent = null;
            }

            if (! preg_match('/^(\s*)(?:-\s+)?(\w+):(?:\s+(\S.*))?$/', $line, $pair)) {
                continue;
            }

            $value = trim($pair[3] ?? '');

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, '>') || str_starts_with($value, '|')) {
                $blockIndent = strlen($pair[1]);

                continue;
            }

            $quoted = str_starts_with($value, '"')
                || str_starts_with($value, "'")
                || str_starts_with($value, '[');

            expect(str_contains($value, ': ') && ! $quoted)
                ->toBeFalse($file.' line '.($number + 1).': quote the value of '.$pair[2]);
        }
    }
});

test('the config holds the package facts and the sitemap plugin', function () {
    expect(file_get_contents(docsSitePath('_config.yml')))
        ->toContain('- jekyll-sitemap')
        ->toContain('name: darvis/api-linkedin')
        ->toContain('company: ARVID.NL')
        ->toContain('url: https://arvid.nl')
        ->not->toContain('footer_content');
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
