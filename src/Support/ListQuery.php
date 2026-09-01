<?php
namespace App\Support;

/**
 * Shared from/to/page/limit parsing for paginated list endpoints.
 */
final class ListQuery
{
    public const DEFAULT_DAYS = 60;
    public const DEFAULT_LIMIT = 25;
    public const MAX_LIMIT = 100;

    /**
     * @param array<string, mixed> $input Usually $_GET
     * @return array{from: string, to: string, page: int, limit: int, offset: int}
     */
    public static function normalize(array $input, int $defaultDays = self::DEFAULT_DAYS, int $defaultLimit = self::DEFAULT_LIMIT): array
    {
        $to = self::validDate($input['to'] ?? null) ?? date('Y-m-d');
        $from = self::validDate($input['from'] ?? null);
        if ($from === null) {
            $from = date('Y-m-d', strtotime($to . ' -' . max(1, $defaultDays) . ' days'));
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $page = max(1, (int) ($input['page'] ?? 1));
        $limit = min(self::MAX_LIMIT, max(1, (int) ($input['limit'] ?? $defaultLimit)));

        return [
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    /**
     * @param array{page: int, limit: int, total: int} $meta
     * @return array{page: int, limit: int, total: int, pages: int}
     */
    public static function pagination(array $meta): array
    {
        $limit = max(1, (int) $meta['limit']);
        $total = max(0, (int) $meta['total']);

        return [
            'page' => max(1, (int) $meta['page']),
            'limit' => $limit,
            'total' => $total,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
        ];
    }

    private static function validDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $parts = array_map('intval', explode('-', $value));
        if (!checkdate($parts[1], $parts[2], $parts[0])) {
            return null;
        }

        return $value;
    }
}
