<?php
namespace App\Service;

/**
 * Shared daily cutoff used for approval finalization and employee request lock.
 */
class ApprovalCutoff
{
    private string $cutoffTime;

    public function __construct(string $cutoffTime = '15:00')
    {
        $this->cutoffTime = $this->normalizeCutoffTime($cutoffTime);
    }

    public function getCutoffTime(): string
    {
        return $this->cutoffTime;
    }

    /** Human-readable label, e.g. "3:00 PM". */
    public function getCutoffLabel(): string
    {
        $dt = \DateTimeImmutable::createFromFormat('H:i', $this->cutoffTime);
        return $dt ? $dt->format('g:i A') : $this->cutoffTime;
    }

    public function isPastCutoff(?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable('now');
        $cutoff = $this->cutoffDateTime($now);
        if (!$cutoff) {
            return false;
        }

        return $now >= $cutoff;
    }

    /** Seconds until today's cutoff; negative when already past. */
    public function secondsUntilCutoff(?\DateTimeInterface $now = null): int
    {
        $now = $now ?? new \DateTimeImmutable('now');
        $cutoff = $this->cutoffDateTime($now);
        if (!$cutoff) {
            return 0;
        }

        return $cutoff->getTimestamp() - $now->getTimestamp();
    }

    public function employeeLockMessage(): string
    {
        return sprintf(
            'Overtime requests are locked from %s onwards. Please ask your approver to submit on your behalf if you still need to request overtime.',
            $this->getCutoffLabel()
        );
    }

    private function cutoffDateTime(\DateTimeInterface $now): ?\DateTimeImmutable
    {
        $cutoff = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $now->format('Y-m-d') . ' ' . $this->cutoffTime
        );

        return $cutoff ?: null;
    }

    private function normalizeCutoffTime(string $cutoffTime): string
    {
        $cutoffTime = trim($cutoffTime);
        if (!preg_match('/^\d{1,2}:\d{2}$/', $cutoffTime)) {
            return '15:00';
        }
        [$h, $m] = array_map('intval', explode(':', $cutoffTime));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return '15:00';
        }

        return sprintf('%02d:%02d', $h, $m);
    }
}
