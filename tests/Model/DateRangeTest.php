<?php
/*
 * Copyright (c) 2021. This code is property of the Sdui GmbH.
 */
declare(strict_types=1);

namespace Sdui\Tests\Library\DateRange\Model;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Sdui\Library\DateRange\Model\DateRange;

class DateRangeTest extends TestCase
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * No Gaps should be returned
     * Scope            |8-----------------------10>
     * Intersections    [8          9][9         10]
     * Gaps
     */
    public function testNoGaps(): void
    {
        $start = Carbon::now()->setHour(8)->setMinute(0)->setSecond(0);
        $scope = new DateRange($start->clone(), $start->clone()->setHour(10));
        $intersection1 = new DateRange($start->clone(), $start->clone()->setHour(9));
        $intersection2 = new DateRange($start->clone()->setHour(9), $start->clone()->setHour(10));
        $gaps = DateRange::gaps($scope, [$intersection1, $intersection2]);

        self::assertSame([], $gaps);
    }

    /**
     * Full scope as Gap should be returned
     * Scope            |0--------------------------24>
     * Intersections
     * Gaps             {0                          24}
     */
    public function testFullGap(): void
    {
        $start = Carbon::now()->startOfDay();
        $end = Carbon::now()->endOfDay();
        $scope = new DateRange($start, $end);
        $gaps = DateRange::gaps($scope, []);

        self::assertCount(1, $gaps);

        self::assertSame(
            $start->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $end->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * remaining gaps
     * Scope            |7--------------------------9>
     * Intersections    [7           8]
     * Gaps                            {8           9}
     */
    public function testSimpleGap(): void
    {
        $start = Carbon::now()->setHour(7)->setMinute(0)->setSecond(0);
        $scope = new DateRange($start->clone(), $start->clone()->setHour(9));
        $intersection = new DateRange($start->clone(), $start->clone()->setHour(8));
        $gaps = DateRange::gaps($scope, [$intersection]);

        self::assertCount(1, $gaps);
        self::assertSame(
            $start->clone()->setHour(8)->setSecond(1)->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(9)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * random gaps in between
     * Scope            |0----------------------------------------24>
     * Intersections             [8   12]        [14 15]
     * Gaps             {0     8}        {12  14}      {15       24}
     */
    public function testComplexGap(): void
    {
        $start = Carbon::now()->startOfDay();
        $scope = new DateRange($start->clone(), $start->clone()->endOfDay());
        $intersection1 = new DateRange($start->clone()->setHour(8), $start->clone()->setHour(12));
        $intersection2 = new DateRange($start->clone()->setHour(14), $start->clone()->setHour(15));
        $gaps = DateRange::gaps($scope, [$intersection1, $intersection2]);

        self::assertCount(3, $gaps);

        // Gap 1
        self::assertSame(
            $start->clone()->setHour(0)->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(7)->setMinute(59)->setSecond(59)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );

        // Gap 2
        self::assertSame(
            $start->clone()->setHour(12)->setSecond(1)->format(self::DATE_FORMAT),
            $gaps[1]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(13)->setMinute(59)->setSecond(59)->format(self::DATE_FORMAT),
            $gaps[1]->getEnd()->format(self::DATE_FORMAT)
        );

        // Gap 3
        self::assertSame(
            $start->clone()->setHour(15)->setSecond(1)->format(self::DATE_FORMAT),
            $gaps[2]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->endOfDay()->format(self::DATE_FORMAT),
            $gaps[2]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * bug prevention for overlapping intersections
     * Scope            |0-----------------------------------------------24>
     * Intersections             [8  11]            [15  16.30]
     *                               [10     13]
     * Gaps             {0     8}              {13 15}        {16.30     24}
     */
    public function testOverlapGap(): void
    {
        $start = Carbon::now()->startOfDay();
        $scope = new DateRange($start->clone(), $start->clone()->endOfDay());
        $intersection1 = new DateRange($start->clone()->setHour(8), $start->clone()->setHour(11));
        $intersection2 = new DateRange($start->clone()->setHour(10), $start->clone()->setHour(13));
        $intersection3 = new DateRange($start->clone()->setHour(15), $start->clone()->setHour(16)->setMinute(30));
        $gaps = DateRange::gaps($scope, [$intersection1, $intersection2, $intersection3]);

        self::assertCount(3, $gaps);

        // Gap 1
        self::assertSame(
            $start->clone()->setHour(0)->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(7)->setMinute(59)->setSecond(59)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );

        // Gap 2
        self::assertSame(
            $start->clone()->setHour(13)->setSecond(1)->format(self::DATE_FORMAT),
            $gaps[1]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(14)->setMinute(59)->setSecond(59)->format(self::DATE_FORMAT),
            $gaps[1]->getEnd()->format(self::DATE_FORMAT)
        );

        // Gap 3
        self::assertSame(
            $start->clone()->setHour(16)->setMinute(30)->setSecond(1)->format(self::DATE_FORMAT),
            $gaps[2]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->endOfDay()->format(self::DATE_FORMAT),
            $gaps[2]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * bug prevention for including intersections
     * Scope            |0-----------------------------------------------24>
     * Intersections             [8                      16.30]
     *                               [10     13]
     * Gaps             {0     8}                              {16.30     24}
     */
    public function testInclusionGaps(): void
    {
        $start = Carbon::now()->startOfDay();
        $scope = new DateRange($start->clone(), $start->clone()->endOfDay());
        $intersection1 = new DateRange($start->clone()->setHour(8), $start->clone()->setHour(16)->setMinute(30));
        $intersection2 = new DateRange($start->clone()->setHour(10), $start->clone()->setHour(13));
        $gaps = DateRange::gaps($scope, [$intersection1, $intersection2]);

        self::assertCount(2, $gaps);

        // Gap 1
        self::assertSame(
            $start->clone()->setHour(0)->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(7)->setMinute(59)->setSecond(59)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );

        // Gap 2
        self::assertSame(
            $start->clone()->setHour(16)->setMinute(30)->setSecond(1)->format(self::DATE_FORMAT),
            $gaps[1]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->endOfDay()->format(self::DATE_FORMAT),
            $gaps[1]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * remaining gaps
     * Scope            |18--------------------------9>
     * Intersections    [18           23]
     * Gaps                            {23           9}
     */
    public function testDayGap(): void
    {
        $start = Carbon::now()->setHour(18)->setMinute(0)->setSecond(0);
        $scope = new DateRange($start->clone(), $start->clone()->addDay()->setHour(9));
        $intersection = new DateRange($start->clone(), $start->clone()->setHour(23));
        $gaps = DateRange::gaps($scope, [$intersection]);

        self::assertCount(1, $gaps);
        self::assertSame(
            $start->clone()->setHour(23)->setSecond(1)->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->addDay()->setHour(9)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * bug prevention for gaps after scope
     * Scope            |7--------------------------9>
     * Intersections                                    [10           11]
     * Gaps             {7                          9}
     */
    public function testGapAfterScope(): void
    {
        $start = Carbon::now()->setHour(7)->setMinute(0)->setSecond(0);
        $scope = new DateRange($start->clone(), $start->clone()->setHour(9));
        $intersection = new DateRange($start->clone()->setHour(10), $start->clone()->setHour(11));
        $gaps = DateRange::gaps($scope, [$intersection]);

        self::assertCount(1, $gaps);
        self::assertSame(
            $start->clone()->setHour(7)->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(9)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * bug prevention for multiple gaps after scope
     * Scope            |7--------------------------9>
     * Intersections                                    [10           11]  [11           12]
     * Gaps             {7                          9}
     */
    public function testMultipleGapsAfterScope(): void
    {
        $start = Carbon::now()->setHour(7)->setMinute(0)->setSecond(0);
        $scope = new DateRange($start->clone(), $start->clone()->setHour(9));
        $intersection1 = new DateRange($start->clone()->setHour(10), $start->clone()->setHour(11));
        $intersection2 = new DateRange($start->clone()->setHour(11), $start->clone()->setHour(12));
        $gaps = DateRange::gaps($scope, [$intersection1, $intersection2]);

        self::assertCount(1, $gaps);
        self::assertSame(
            $start->clone()->setHour(7)->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(9)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * bug prevention for intermediate gaps and gaps after scope
     * Scope            |7--------------------------9>
     * Intersections    [7          8]                       [10           11]
     * Gaps                            {8           9}
     */
    public function testGapAndAfterScope(): void
    {
        $start = Carbon::now()->setHour(7)->setMinute(0)->setSecond(0);
        $scope = new DateRange($start->clone(), $start->clone()->setHour(9));
        $intersection1 = new DateRange($start->clone()->setHour(7), $start->clone()->setHour(8));
        $intersection2 = new DateRange($start->clone()->setHour(10), $start->clone()->setHour(11));
        $gaps = DateRange::gaps($scope, [$intersection1, $intersection2]);

        self::assertCount(1, $gaps);
        self::assertSame(
            $start->clone()->setHour(8)->addSecond()->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(9)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * bug prevention for gaps before scope
     * Scope                            |7--------------------------9>
     * Intersections    [5           6]
     * Gaps                             {7                          9}
     */
    public function testGapBeforeScope(): void
    {
        $start = Carbon::now()->setHour(7)->setMinute(0)->setSecond(0);
        $scope = new DateRange($start->clone(), $start->clone()->setHour(9));
        $intersection = new DateRange($start->clone()->setHour(5), $start->clone()->setHour(6));
        $gaps = DateRange::gaps($scope, [$intersection]);

        self::assertCount(1, $gaps);
        self::assertSame(
            $start->clone()->setHour(7)->format(self::DATE_FORMAT),
            $gaps[0]->getStart()->format(self::DATE_FORMAT)
        );
        self::assertSame(
            $start->clone()->setHour(9)->format(self::DATE_FORMAT),
            $gaps[0]->getEnd()->format(self::DATE_FORMAT)
        );
    }

    /**
     * No Gaps should be returned in second run
     * Scope            |8-----------------------11>
     * Intersections          [9         10]
     * Gaps             {8  9}              {10  11}
     */
    public function testNoGapsAfterRunningTwice(): void
    {
        $start = Carbon::now()->setHour(8)->setMinute(0)->setSecond(0);
        $scope = new DateRange($start->clone(), $start->clone()->setHour(11));
        $intersection = new DateRange($start->clone()->setHour(9), $start->clone()->setHour(10));
        $gaps = DateRange::gaps($scope, [$intersection]);

        self::assertCount(2, $gaps);

        $gaps2 = DateRange::gaps($scope, [$intersection, ...$gaps]);

        self::assertCount(0, $gaps2);
    }
}
