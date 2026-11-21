<?php
declare(strict_types=1);

namespace JiraHours;

final class Week
{
    private function __construct(
        private readonly \DateTimeImmutable $start,
        private readonly \DateTimeImmutable $end
    ) {
    }

    public function weekStart(): string
    {
        return $this->start->format("Y-m-d");
    }

    public function weekEnd(): string
    {
        return $this->end->format("Y-m-d");
    }

    /** @return array{string, string} */
    public function searchRange(): array
    {
        return [
            $this->start->modify("-1 days")->format("Y-m-d"),
            $this->end->modify("+1 days")->format("Y-m-d"),
        ];
    }



    public static function fromInput(?string $start, \DateTimeZone $tz): self
    {
        if ($start === null) {
            $startDay = new \DateTimeImmutable('now', $tz);
            $startDay = $startDay->setTime(0, 0, 0);
        } else {
            $startDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $start, $tz);
            if ($startDay === false || $startDay->format('Y-m-d') !== $start) {
                throw new InvalidInputException('start must be a date in YYYY-MM-DD format');
            }
        }

        $monday = $startDay->modify('monday this week');

        return new self($monday, $monday->modify('+6 days'));
    }

}