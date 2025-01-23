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
use App\Timesheet\TimesheetService;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;

class PeriodInsertRepository
{
    /**
     * @var string[]
     */
    private array $holidays;

    public function __construct(private readonly SystemConfiguration $configuration,
        private readonly TimesheetService $timesheetService,
        private readonly WorkingTimeService $workService
    ) {
    }

    /**
     * @param PeriodInsert $periodInsert
     */
    public function findHolidays(PeriodInsert $periodInsert): void
    {
        $this->holidays = [];
        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('first day of next month')) {
            $month = $this->workService->getMonth($periodInsert->getUser(), $begin, (clone $begin)->modify('last day of this month'));
            foreach ($month->getDays() as $day) {
                if ($day->hasAddons()) {
                    $this->holidays[] = $day->getDay()->format('Y-m-d');
                }
            }
        }
    }

    /**
     * @param PeriodInsert $periodInsert
     * @param DateTime $begin
     * @return bool
     */
    public function checkDayValid(PeriodInsert $periodInsert, \DateTime $begin): bool
    {
        return $periodInsert->isDaySelected($begin) && $this->workService->getContractMode($periodInsert->getUser())->getCalculator($periodInsert->getUser())->isWorkDay($begin) && !in_array($begin->format('Y-m-d'), $this->holidays);
    }

    /**
     * @param PeriodInsert $periodInsert
     */
    public function savePeriodInsert(PeriodInsert $periodInsert): void
    {
        $validatedTimesheets = [];
        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('+1 day')) {
            if ($this->checkDayValid($periodInsert, $begin)) {
                $timesheet = $this->createTimesheet($periodInsert, $begin);
                $this->timesheetService->validateTimesheet($timesheet);
                $validatedTimesheets[] = $timesheet;
            }
        }
        foreach ($validatedTimesheets as $timesheet) {
            $this->timesheetService->saveNewTimesheet($timesheet);
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
