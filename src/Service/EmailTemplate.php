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

    /**
     * Courtesy title from employee_list.
     * gender: 0 = male, 1 = female
     * marital_status: 0 = single, 1 = married
     */
    public static function honorific($gender, $maritalStatus): string
    {
        if ($gender === null || $gender === '') {
            return '';
        }

        $g = (int) $gender;
        if ($g === 0) {
            return 'Mr.';
        }
        if ($g === 1) {
            return ((int) $maritalStatus === 1) ? 'Mrs.' : 'Ms.';
        }

        return '';
    }

    /**
     * Formal surname for email greetings and subjects (e.g. "Mr. Rivera").
     *
     * @param array<string, mixed>|null $person
     */
    public static function formalSurname(?array $person, string $fallback = ''): string
    {
        if (!$person) {
            return $fallback;
        }

        $surname = trim((string) ($person['surname'] ?? ''));
        $firstname = trim((string) ($person['firstname'] ?? ''));
        $name = $surname !== '' ? $surname : $firstname;
        if ($name === '') {
            $name = $fallback;
        }

        $title = self::honorific(
            $person['gender'] ?? null,
            $person['marital_status'] ?? null
        );
        if ($title === '' || $name === '') {
            return $name !== '' ? $name : $fallback;
        }

        return $title . ' ' . $name;
    }
}
