<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Repository;

use App\Entity\Timesheet;
use App\Timesheet\TimesheetService;
use DateTime;
use DateTimeImmutable;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;

class PeriodInsertRepository
{
    public function __construct(private readonly TimesheetService $timesheetService)
    {
    }

    /**
     * @param PeriodInsert $periodInsert
     * @throws \App\Validator\ValidationFailedException for invalid timesheets
     */
    public function savePeriodInsert(PeriodInsert $periodInsert): void
    {
        $validatedTimesheets = [];

        foreach ($periodInsert->getValidDays() as $day) {
            $timesheet = $this->createTimesheet($periodInsert, $day);
            $this->timesheetService->validateTimesheet($timesheet);
            $validatedTimesheets[] = $timesheet;
        }

        foreach ($validatedTimesheets as $timesheet) {
            $this->timesheetService->saveNewTimesheet($timesheet);
        }
    }

    /**
     * @param PeriodInsert $periodInsert
     * @param DateTimeImmutable $begin
     * @return Timesheet
     */
    public function createTimesheet(PeriodInsert $periodInsert, DateTimeImmutable $begin): Timesheet
    {
        $timesheet = new Timesheet();
        $timesheet->setUser($periodInsert->getUser());

        $begin = DateTime::createFromImmutable($begin);
        $timesheet->setBegin($begin);
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
