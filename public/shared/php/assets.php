<?php
/**
 * Asset URL helpers: filemtime cache-busting for local CSS/JS.
 * Web root aliases: /overtime/{admin|request|approve|shared} → public/...
 */

function ot_public_root(): string
{
    return dirname(__DIR__, 2);
}

/** Favicon URL for the browser tab. */
function ot_favicon_links(): string
{
    $active = htmlspecialchars(ot_asset(ot_web_base() . '/shared/img/favicon.svg'), ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <link rel="icon" type="image/svg+xml" href="{$active}" id="otFavicon" />
    <meta name="ot-favicon-active" content="{$active}" />
HTML;
}

/** Browser base path, e.g. "/overtime". */
function ot_web_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.*?)/(admin|request|approve)(?:/|$)#', $script, $m)) {
        $base = rtrim($m[1], '/');
        return $base;
    }

    $base = '/overtime';
    return $base;
}

function ot_asset_version(string $fsPath): string
{
    $real = realpath($fsPath);
    if ($real !== false && is_file($real)) {
        return (string) filemtime($real);
    }
    if (is_file($fsPath)) {
        return (string) filemtime($fsPath);
    }
    return (string) time();
}

/**
 * Append ?v=filemtime for a path relative to the current page, or an absolute /overtime/... path.
 * Leaves http(s) CDN URLs unchanged.
 */
function ot_asset(string $href): string
{
    if ($href === '' || preg_match('#^(https?:)?//#i', $href) || str_starts_with($href, 'data:')) {
        return $href;
    }

    $public = ot_public_root();
    $webBase = ot_web_base();

    if (str_starts_with($href, '/')) {
        $rel = $href;
        if ($webBase !== '' && str_starts_with($href, $webBase . '/')) {
            $rel = substr($href, strlen($webBase) + 1);
        } elseif ($webBase !== '' && $href === $webBase) {
            $rel = '';
        } else {
            $rel = ltrim($href, '/');
        }
        $fs = $public . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        return $href . '?v=' . ot_asset_version($fs);
    }

    $pageDir = dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
    $fs = $pageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $href);
    return $href . '?v=' . ot_asset_version($fs);
}

/**
 * Import map so ES module relative imports also resolve to versioned URLs.
 */
function ot_import_map_json(): string
{
    $public = ot_public_root();
    $webBase = ot_web_base();
    $imports = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($public, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'js') {
            continue;
        }
        $full = $file->getPathname();
        $rel = str_replace('\\', '/', substr($full, strlen($public) + 1));
        if (str_contains($rel, '/php/')) {
            continue;
        }
        $url = $webBase . '/' . $rel;
        $imports[$url] = $url . '?v=' . filemtime($full);
    }

    ksort($imports);
    return json_encode(['imports' => $imports], JSON_UNESCAPED_SLASHES);
}
