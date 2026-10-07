<?php
declare(strict_types=1);

namespace Softaware\CronManager\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Locale\ResolverInterface;
use Softaware\CronManager\Model\JobConfig;
use Softaware\CronManager\Model\ScheduleManager;
use Softaware\CronManager\Ui\Component\Listing\Column\Duration;
use Softaware\CronManager\Ui\Component\Listing\Column\StatusBadge;

/**
 * Gantt-like timeline of cron runs: one row per job, one bar per run, coloured by status.
 */
class Timeline extends Container
{
    public const SPANS = [1, 3, 6, 12, 24];
    private const MIN_WIDTH = 0.18;

    protected $_template = 'Softaware_CronManager::timeline.phtml';

    /** @var array<string, mixed>|null */
    private ?array $chart = null;

    public function __construct(
        Context $context,
        private readonly ScheduleManager $scheduleManager,
        private readonly JobConfig $jobConfig,
        private readonly ResolverInterface $localeResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _prepareLayout()
    {
        if ($this->_authorization->isAllowed('Softaware_CronManager::jobs')) {
            $this->addButton('jobs', [
                'label' => __('Cron Jobs'),
                'onclick' => sprintf("setLocation('%s')", $this->escapeJs($this->getUrl('softaware_cron/job/index'))),
            ], 0, 10);
        }
        $this->addButton('schedule', [
            'label' => __('Schedule'),
            'onclick' => sprintf("setLocation('%s')", $this->escapeJs($this->getUrl('softaware_cron/schedule/index'))),
        ], 0, 20);
        return parent::_prepareLayout();
    }

    public function formatNumber(int $number): string
    {
        $formatter = new \NumberFormatter($this->localeResolver->getLocale(), \NumberFormatter::DECIMAL);
        $formatted = $formatter->format($number);
        return $formatted === false ? (string) $number : $formatted;
    }

    /**
     * The shown period in the admin locale, e.g. "7 Oct 2026, 01:00 – 04:00".
     */
    public function getPeriodLabel(): string
    {
        $locale = $this->localeResolver->getLocale();
        $tz = $this->getTimezone();
        $start = $this->getStart();
        $end = $this->getEnd();
        $full = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, $tz);
        $endFormatter = $start->format('Y-m-d') === $end->format('Y-m-d')
            ? new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT, $tz)
            : $full;
        return $full->format($start) . ' – ' . $endFormatter->format($end);
    }

    public function getTimezone(): \DateTimeZone
    {
        return new \DateTimeZone($this->_localeDate->getConfigTimezone());
    }

    public function getSpan(): int
    {
        $span = (int) $this->getRequest()->getParam('span', 3);
        return in_array($span, self::SPANS, true) ? $span : 3;
    }

    public function getGroup(): string
    {
        return (string) $this->getRequest()->getParam('group', '');
    }

    public function getSearch(): string
    {
        return trim((string) $this->getRequest()->getParam('q', ''));
    }

    public function onlyProblems(): bool
    {
        return (bool) $this->getRequest()->getParam('problems');
    }

    /**
     * End of the range: the "end" parameter (local time), or now rounded up to the next quarter hour.
     */
    public function getEnd(): \DateTimeImmutable
    {
        $tz = $this->getTimezone();
        $param = (string) $this->getRequest()->getParam('end', '');
        if ($param !== '') {
            $end = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $param, $tz);
            if ($end) {
                return $end;
            }
        }
        $now = new \DateTimeImmutable('now', $tz);
        $minutes = (int) $now->format('i');
        $round = (int) (ceil(($minutes + 1) / 15) * 15);
        return $now->setTime((int) $now->format('H'), 0)->modify('+' . $round . ' minutes');
    }

    public function getStart(): \DateTimeImmutable
    {
        return $this->getEnd()->modify('-' . $this->getSpan() . ' hours');
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->jobConfig->getGroups();
    }

    public function getUrlFor(array $params): string
    {
        $current = [
            'span' => $this->getSpan(),
            'end' => $this->getEnd()->format('Y-m-d\TH:i'),
            'group' => $this->getGroup(),
            'q' => $this->getSearch(),
            'problems' => $this->onlyProblems() ? 1 : null,
        ];
        $query = array_filter(array_merge($current, $params), static fn ($v) => $v !== null && $v !== '');
        return $this->getUrl('*/*/index', ['_query' => $query]);
    }

    /**
     * @return array{rows: array<string, array<int, array<string, mixed>>>, ticks: array<int, array{left: float, label: string, major: bool}>, now: ?float, count: int, truncated: bool}
     */
    public function getChart(): array
    {
        if ($this->chart !== null) {
            return $this->chart;
        }
        $start = $this->getStart()->getTimestamp();
        $end = $this->getEnd()->getTimestamp();
        $length = max(60, $end - $start);

        $jobs = $this->jobConfig->getJobs(false);
        $codes = null;
        if ($this->getGroup() !== '' || $this->getSearch() !== '') {
            $search = mb_strtolower($this->getSearch());
            $codes = [];
            foreach ($jobs as $code => $job) {
                if ($this->getGroup() !== '' && $job['group'] !== $this->getGroup()) {
                    continue;
                }
                if ($search !== '' && !str_contains(mb_strtolower($code), $search)) {
                    continue;
                }
                $codes[] = $code;
            }
        }
        $limit = 6000;
        $entries = $this->scheduleManager->getForTimeline(gmdate('Y-m-d H:i:s', $start), gmdate('Y-m-d H:i:s', $end), $codes, $limit);
        $rows = [];
        $tz = $this->getTimezone();
        $now = time();
        foreach ($entries as $entry) {
            $status = (string) $entry['status'];
            $from = strtotime((string) ($entry['executed_at'] ?: $entry['scheduled_at']) . ' UTC');
            if ($entry['finished_at']) {
                $to = strtotime($entry['finished_at'] . ' UTC');
            } elseif ($status === 'running') {
                $to = $now;
            } else {
                $to = $from;
            }
            $left = max(0.0, ($from - $start) / $length * 100);
            $right = min(100.0, ($to - $start) / $length * 100);
            $width = max(self::MIN_WIDTH, $right - $left);
            $duration = $entry['executed_at'] && ($entry['finished_at'] || $status === 'running') ? $to - $from : null;
            $rows[(string) $entry['job_code']][] = [
                'left' => round(min($left, 100 - self::MIN_WIDTH), 3),
                'width' => round($width, 3),
                'status' => $status,
                'title' => $this->buildTitle($entry, $duration, $tz),
            ];
        }
        if ($this->onlyProblems()) {
            $rows = array_filter($rows, static function (array $bars) {
                foreach ($bars as $bar) {
                    if (in_array($bar['status'], ['error', 'missed', 'running'], true)) {
                        return true;
                    }
                }
                return false;
            });
        }
        ksort($rows);

        $ticks = [];
        $step = $this->getSpan() <= 3 ? 900 : ($this->getSpan() <= 6 ? 1800 : 3600);
        $tick = (int) (ceil($start / $step) * $step);
        for (; $tick <= $end; $tick += $step) {
            $local = (new \DateTimeImmutable('@' . $tick))->setTimezone($tz);
            $ticks[] = [
                'left' => round(($tick - $start) / $length * 100, 3),
                'label' => $local->format('H:i'),
                'major' => $local->format('i') === '00',
            ];
        }
        $this->chart = [
            'rows' => $rows,
            'ticks' => $ticks,
            'now' => $now >= $start && $now <= $end ? round(($now - $start) / $length * 100, 3) : null,
            'count' => count($entries),
            'truncated' => count($entries) >= $limit,
        ];
        return $this->chart;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function buildTitle(array $entry, ?int $duration, \DateTimeZone $tz): string
    {
        $fmt = static fn (?string $utc) => $utc
            ? (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone($tz)->format('H:i:s')
            : '—';
        $lines = [
            $entry['job_code'] . ' · ' . StatusBadge::getLabel((string) $entry['status']) . ' · #' . $entry['schedule_id'],
            __('Scheduled %1, started %2, finished %3', $fmt($entry['scheduled_at']), $fmt($entry['executed_at']), $fmt($entry['finished_at'])),
        ];
        if ($duration !== null) {
            $lines[] = __('Duration: %1', Duration::format((float) $duration));
        }
        if (!empty($entry['messages'])) {
            $lines[] = mb_strimwidth(preg_replace('/\s+/', ' ', (string) $entry['messages']) ?? '', 0, 300, '…');
        }
        return implode("\n", array_map('strval', $lines));
    }
}
