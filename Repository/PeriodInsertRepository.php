<?php
/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Repository;

use App\Configuration\SystemConfiguration;
use App\Entity\Timesheet;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;

class PeriodInsertRepository
{
    public function __construct(private TimesheetRepository $timesheetRepository,
        private readonly SystemConfiguration $configuration,
        private readonly TimesheetService $timesheetService,
        private readonly WorkingTimeService $workService
    ) {
    }

    /**
     * @param PeriodInsert $periodInsert
     * @return string
     */
    public function findDayToInsert(PeriodInsert $periodInsert): string
    {
        for ($end = clone $periodInsert->getEnd(); $end >= $periodInsert->getBegin(); $end->modify('-1 day')) {
            if ($periodInsert->isDaySelected($end)) {
                return $end->format('Y-m-d');
            }
        }
        return '';
    }

    /**
     * @param PeriodInsert $periodInsert
     * @param string $dayToInsert
     * @return bool
     */
    public function checkFutureTime(PeriodInsert $periodInsert, string $dayToInsert): bool
    {
        if ($this->configuration->isTimesheetAllowFutureTimes()) {
            return false;
        }

        if ($dayToInsert !== date('Y-m-d')) {
            return $dayToInsert > date('Y-m-d');
        }

        $now = new \DateTime('now', $periodInsert->getBeginTime()->getTimezone());

        // allow configured default rounding time + 1 minute
        $nowBeginTs = $now->getTimestamp() + ($this->configuration->getTimesheetDefaultRoundingBegin() * 60) + 60;
        $nowEndTs = $now->getTimestamp() + ($this->configuration->getTimesheetDefaultRoundingEnd() * 60) + 60;

        return $nowBeginTs < $periodInsert->getBeginTime()->getTimestamp() || $nowEndTs < $periodInsert->getEndTime()->getTimestamp();
    }

    /**
     * @param PeriodInsert $periodInsert
     * @return bool
     */
    public function checkZeroDuration(PeriodInsert $periodInsert): bool
    {
        if ($this->configuration->isTimesheetAllowZeroDuration()) {
            return false;
        }
        return $periodInsert->getDuration() === 0;
    }

    /**
     * @param PeriodInsert $periodInsert
     * @return string
     */
    public function checkOverlappingTimeEntries(PeriodInsert $periodInsert): string
    {   
        if (!$this->configuration->isTimesheetAllowOverlappingRecords()) {
            for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('+1 day')) {
                if ($periodInsert->isDaySelected($begin) && $this->timesheetRepository->hasRecordForTime($this->createTimesheet($periodInsert, $begin))) {
                    return $begin->format('m/d/Y');
                }
            }
        }
        return '';
    }

    /**
     * @param PeriodInsert $periodInsert
     * @return string[]
     */
    public function findHolidays(PeriodInsert $periodInsert): array
    {
        $holidays = [];
        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('first day of next month')) {
            $month = $this->workService->getMonth($periodInsert->getUser(), $begin, (clone $begin)->modify('last day of this month'));
            foreach ($month->getDays() as $day) {
                if ($day->hasAddons()) {
                    $holidays[] = $day->getDay()->format('Y-m-d');
                }
            }
        }
        return $holidays;
    }

    /**
     * @param PeriodInsert $periodInsert
     * @param DateTime $begin
     * @param string[] $holidays
     * @return bool
     */
    public function checkDayValid(PeriodInsert $periodInsert, \DateTime $begin, array $holidays): bool
    {
        return $periodInsert->isDaySelected($begin) && $this->workService->getContractMode($periodInsert->getUser())->getCalculator($periodInsert->getUser())->isWorkDay($begin) && !in_array($begin->format('Y-m-d'), $holidays);
    }

    /**
     * @param PeriodInsert $periodInsert
     * @return void
     */
    public function saveTimesheet(PeriodInsert $periodInsert): void
    {
        $holidays = $this->findHolidays($periodInsert);
        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('+1 day')) {
            if ($this->checkDayValid($periodInsert, $begin, $holidays)) {
                $this->timesheetService->saveNewTimesheet($this->createTimesheet($periodInsert, $begin));
            }
        }
    }

    /**
     * @param PeriodInsert $periodInsert
     * @param DateTime $begin
     * @return Timesheet
     */
    public function createTimesheet(PeriodInsert $periodInsert, \DateTime $begin): Timesheet
    {
        $timesheet = new Timesheet();
        $timesheet->setUser($periodInsert->getUser());

        $timesheet->setBegin((clone $begin));
        $timesheet->setEnd((clone $begin)->modify('+' . $periodInsert->getDuration() . ' seconds'));
        $timesheet->setDuration($periodInsert->getDuration());

        if (null !== $periodInsert->getProject()) {
            $timesheet->setProject($periodInsert->getProject());
        }

        if (null !== $periodInsert->getActivity()) {
            $timesheet->setActivity($periodInsert->getActivity());
        }

        $timesheet->setDescription($periodInsert->getDescription());
        
        foreach ($periodInsert->getTags() as $tag) {
            $timesheet->addTag($tag);
        }

        if (null !== $periodInsert->getFixedRate()) {
            $timesheet->setFixedRate($periodInsert->getFixedRate());
        }
        
        if (null !== $periodInsert->getHourlyRate()) {
            $timesheet->setHourlyRate($periodInsert->getHourlyRate());
        }
        
        $timesheet->setBillable($periodInsert->isBillable());
        $timesheet->setBillableMode($periodInsert->getBillableMode());
        $timesheet->setExported($periodInsert->getExported());

        return $timesheet;
    }
}
