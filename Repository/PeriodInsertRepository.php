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
use DateInterval;
use DateTime;
use Exception;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use Psr\Log\LoggerInterface;

class PeriodInsertRepository
{
    /**
     * @var TimesheetRepository
     */
    private $timesheetRepository;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var int
     */
    private $secondsInADay = 24*60*60;

    /**
     * PeriodInsertRepository constructor.
     * @param TimesheetRepository $timesheetRepository
     * @param LoggerInterface $logger
     */
    public function __construct(TimesheetRepository $timesheetRepository, LoggerInterface $logger)
    {
        $this->timesheetRepository = $timesheetRepository;
        $this->logger = $logger;
    }

    /**
     * @param PeriodInsert $entity
     * @return void
     * @throws Exception
     */
    public function saveTimesheet(PeriodInsert $entity)
    {
        $day = (int)$entity->getBegin()->format('w');
        $numberOfDays = $entity->getEnd()->diff($entity->getBegin())->format("%a") + $day;
        $begin = clone $entity->getBegin();
        $begin->setTime($entity->getBeginTime()->format('H'), $entity->getBeginTime()->format('i'));
        for (; $day <= $numberOfDays; $day++, $begin->modify('+1 day')) {
            if ($entity->getDay($day)) {
                $this->createTimesheet($entity, $begin, $entity->getDurationPerDay() % $this->secondsInADay);
            }
        }
    }

    /**
     * @param PeriodInsert $entity
     * @param DateTime $begin
     * @param int $duration
     * @return void
     * @throws Exception
     */
    protected function createTimesheet(PeriodInsert $entity, DateTime $begin, int $duration): void
    {
        $entry = new Timesheet();
        $entry->setUser($entity->getUser());

        $entry->setBegin($begin);
        $entry->setEnd((clone $begin)->add(new DateInterval('PT' . $duration . 'S')));
        $entry->setDuration($duration);

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
            $entry->setBillableMode($entity->getBillableMode());
        }

        if (null !== $entity->getExported()) {
            $entry->setExported($entity->getExported());
        }

        try {
            $this->timesheetRepository->save($entry);
        } catch (Exception $ex) {
            $this->logger->error($ex->getMessage());
        }
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
