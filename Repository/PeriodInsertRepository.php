<?php
/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Repository;

use App\Entity\Timesheet;
use App\Repository\TimesheetRepository;
use DateTime;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;

class PeriodInsertRepository
{
    /**
     * @var TimesheetRepository
     */
    private $timesheetRepository;

    /**
     * PeriodInsertRepository constructor.
     * @param TimesheetRepository $timesheetRepository
     */
    public function __construct(TimesheetRepository $timesheetRepository)
    {
        $this->timesheetRepository = $timesheetRepository;
    }

    /**
     * @param PeriodInsert $entity
     * @return string
     */
    public function findOverlappingTimeEntry(PeriodInsert $entity): string
    {   
        $day = (int)$entity->getBegin()->format('w');
        $numberOfDays = $entity->getEnd()->diff($entity->getBegin())->format("%a") + $day;
        $begin = clone $entity->getBegin();
        for (; $day <= $numberOfDays; $day++, $begin->modify('+1 day')) {
            if ($entity->getDay($day) && $this->timesheetRepository->hasRecordForTime(
                $this->createTimesheet($entity, $begin))) {
                return $begin->format('m/d/Y');
            }
        }
        return '';
    }

    /**
     * @param PeriodInsert $entity
     * @return void
     */
    public function saveTimesheet(PeriodInsert $entity): void
    {
        $day = (int)$entity->getBegin()->format('w');
        $numberOfDays = $entity->getEnd()->diff($entity->getBegin())->format("%a") + $day;
        $begin = clone $entity->getBegin();
        for (; $day <= $numberOfDays; $day++, $begin->modify('+1 day')) {
            if ($entity->getDay($day)) {
                $this->timesheetRepository->save($this->createTimesheet($entity, $begin));
            }
        }
    }

    /**
     * @param PeriodInsert $entity
     * @param DateTime $begin
     * @return Timesheet
     */
    protected function createTimesheet(PeriodInsert $entity, DateTime $begin): Timesheet
    {
        $entry = new Timesheet();
        $entry->setUser($entity->getUser());

        $entry->setBegin((clone $begin));
        $entry->setEnd((clone $begin)->setTime($entity->getEndTime()->format('H'), $entity->getEndTime()->format('i')));
        $entry->setDuration($entity->getDurationPerDay());

        $entry->setProject($entity->getProject());
        $entry->setActivity($entity->getActivity());
        $entry->setDescription($entity->getDescription());
        foreach ($entity->getTags() as $tag) {
            $entry->addTag($tag);
        }

        if (null !== $entity->getFixedRate()) {
            $entry->setFixedRate($entity->getFixedRate());
        }
        
        if (null !== $entity->getHourlyRate()) {
            $entry->setHourlyRate($entity->getHourlyRate());
        }

        if (null !== $entity->getBillableMode()) {
            $entry->setBillable($this->calculateBillable($entity));
            $entry->setBillableMode($entity->getBillableMode());
        }

        if (null !== $entity->getExported()) {
            $entry->setExported($entity->getExported());
        }
        return $entry;
    }

    /**
     * @param PeriodInsert $entity
     * @return bool
     */
    protected function calculateBillable(PeriodInsert $entity): bool
    {
        if ($entity->getBillableMode() === 'auto') {
            $activity = $entity->getActivity();
            if ($activity !== null && !$activity->isBillable()) {
                return false;
            }

            $project = $entity->getProject();
            if ($project !== null) {
                if (!$project->isBillable()) {
                    return false;
                }

                $customer = $project->getCustomer();
                if ($customer !== null && !$customer->isBillable()) {
                    return false;
                }
            }
        }
        return $entity->getBillableMode() === 'yes';
    }

    /**
     * @return PeriodInsert
     */
    public function getTimesheet(): PeriodInsert
    {
        $entity = new PeriodInsert();
        return $entity;
    }
}
