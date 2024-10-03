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
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;

class PeriodInsertRepository
{
    private TimesheetRepository $timesheetRepository;

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
    protected function createTimesheet(PeriodInsert $entity, \DateTime $begin): Timesheet
    {
        $entry = new Timesheet();
        $entry->setUser($entity->getUser());

        $entry->setBegin((clone $begin));
        $entry->setEnd((clone $begin)->modify('+' . $entity->getDuration() . ' seconds'));
        $entry->setDuration($entity->getDuration());

        if (null !== $entity->getProject()) {
            $entry->setProject($entity->getProject());
        }

        if (null !== $entity->getActivity()) {
            $entry->setActivity($entity->getActivity());
        }

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
        
        $entry->setBillable($entity->isBillable());
        $entry->setBillableMode($entity->getBillableMode());
        $entry->setExported($entity->getExported());

        return $entry;
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
