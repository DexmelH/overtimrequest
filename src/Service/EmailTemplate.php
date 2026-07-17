<?php
namespace App\Service;

/**
 * Builds consistent overtime email HTML from template files.
 */
class EmailTemplate
{
    private string $templateDir;

    public function __construct(?string $templateDir = null)
    {
        $this->templateDir = $templateDir ?? dirname(__DIR__) . '/usr/template';
    }

    public function load(string $filename): string
    {
        $path = $this->templateDir . '/' . ltrim($filename, '/');
        if (!is_readable($path)) {
            throw new \RuntimeException("Email template not found: {$path}");
        }
        return (string) file_get_contents($path);
    }

    public function render(string $html, array $vars): string
    {
        $vars['{{year}}'] = $vars['{{year}}'] ?? date('Y');
        $vars['{{app_name}}'] = $vars['{{app_name}}'] ?? 'Overtime Request System';
        return strtr($html, $vars);
    }

    public static function normalizeDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        try {
            return (new \DateTime($date))->format('F j, Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
