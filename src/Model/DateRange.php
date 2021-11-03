<?php
/*
 * Copyright (c) 2021. This code is property of the Sdui GmbH.
 */
declare(strict_types=1);

namespace Sdui\Library\DateRange\Model;

use DateTime;
use DateTimeInterface;

class DateRange
{
    private DateTimeInterface $start;
    private DateTimeInterface $end;

    public function __construct(DateTimeInterface $start, DateTimeInterface $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function getStart(): DateTimeInterface
    {
        return $this->start;
    }

    public function getEnd(): DateTimeInterface
    {
        return $this->end;
    }

    public function findGaps(iterable $intersections): array
    {
        return self::gaps($this, $intersections);
    }

    /**
     * @description iterates through the intersections to find gaps from the scope.
     * Carries the start and end date for each iteration to find overlaps
     *
     * @param self $scope
     * @param iterable&self[] $intersections
     * @return self[]
     */
    public static function gaps(self $scope, iterable $intersections): array
    {
        $gaps = [];
        $gapStart = $scope->getStart();

        // sort it first by startdate to identify overlappings easier and for also easier processing
        usort(
            $intersections,
            static fn (self $a, self $b) => $a->getStart()->getTimestamp() - $b->getStart()->getTimestamp()
        );

        // strip out-of-scope intersections
        $intersections = array_filter(
            $intersections,
            static fn (self $dateRange) => $scope->getEnd() > $dateRange->getStart() && $dateRange->getEnd() > $scope->getStart()
        );

        foreach ($intersections as $key => $dateRange) {
            $timestampAddition = ($key === 0 ? 0 : 1);
            $gapEnd = $dateRange->getStart();

            // inclusion detection
            if ($gapStart->getTimestamp() >= $dateRange->getEnd()->getTimestamp()) {
                continue;
            }

            // overlap detection: either from scope and an intersection or from between intersections
            if ($gapStart->getTimestamp() >= $gapEnd->getTimestamp()) {
                $gapStart = $dateRange->getEnd();
                continue;
            }

            $gapStart = (new DateTime())->setTimestamp($gapStart->getTimestamp() + $timestampAddition);
            $gapEnd = (new DateTime())->setTimestamp($gapEnd->getTimestamp() - 1);

            if ($gapEnd > $gapStart) {
                $gaps[] = new DateRange($gapStart, $gapEnd);
            }

            $gapStart = $dateRange->getEnd();
        }

        // is there an intersection left at the end?
        if ($scope->getEnd()->getTimestamp() > $gapStart->getTimestamp()) {
            $gaps[] = new DateRange(
                (new DateTime())->setTimestamp($gapStart->getTimestamp() + (count($intersections) === 0 ? 0 : 1)),
                $scope->getEnd()
            );
        }

        return $gaps;
    }
}
