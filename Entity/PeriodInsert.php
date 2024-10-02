<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Entity;

class PeriodInsert
{

    private $user;
    private $beginToEnd;
    private $beginTime;
    private $endTime;
    private $durationPerDay;
    private $project;
    private $activity;
    private $description;
    private $tags;
    private $days;
    private $fixedRate;
    private $hourlyRate;
    private $billableMode;
    private $exported;

    /**
     * PeriodInsertRepository constructor.
     */
    public function __construct()
    {
        $this->days = array_fill(0, 7, true);
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getBeginToEnd()
    {
        return $this->beginToEnd;
    }

    /**
     * @param mixed $beginToEnd
     */
    public function setBeginToEnd($beginToEnd): void
    {
        $this->beginToEnd = $beginToEnd;
    }

    /**
     * @return mixed
     */
    public function getBegin()
    {
        return $this->beginToEnd->getBegin();
    }

    /**
     * @return mixed
     */
    public function getEnd()
    {
        return $this->beginToEnd->getEnd();
    }

    /**
     * @return mixed
     */
    public function getBeginTime()
    {
        return $this->beginTime;
    }

    /**
     * @param mixed $beginTime
     */
    public function setBeginTime($beginTime): void
    {
        $this->beginTime = $beginTime;
    }

    /**
     * @return mixed
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    public function setTimes(): void
    {
        $this->beginToEnd->getBegin()->setTime($this->beginTime->format('H'), $this->beginTime->format('i'));
        $this->endTime = (clone $this->beginTime)->modify('+' . $this->durationPerDay . ' seconds');
    }

    /**
     * @return mixed
     */
    public function getDurationPerDay()
    {
        return $this->durationPerDay;
    }

    /**
     * @param mixed $durationPerDay
     */
    public function setDurationPerDay($durationPerDay)
    {
        $secondsInADay = 24*60*60;
        $this->durationPerDay = $durationPerDay % $secondsInADay;
    }
    
    /**
     * @return mixed
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param mixed $project
     */
    public function setProject($project): void
    {
        $this->project = $project;
    }

    /**
     * @return mixed
     */
    public function getActivity()
    {
        return $this->activity;
    }

    /**
     * @param mixed $activity
     */
    public function setActivity($activity): void
    {
        $this->activity = $activity;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description): void
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param mixed $tags
     */
    public function setTags($tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @return mixed
     */
    public function getDay(int $day)
    {
        $day = $day % 7;
        return $this->days[$day >= 0 ? $day : $day + 7];
    }

    /**
     * @return mixed $monday
     */
    public function getMonday()
    {
        return $this->days[1];
    }

    /**
     * @param mixed $monday
     */
    public function setMonday($monday): void
    {
        $this->days[1] = $monday;
    }

    /**
     * @return mixed $tuesday
     */
    public function getTuesday()
    {
        return $this->days[2];
    }

    /**
     * @param mixed $tuesday
     */
    public function setTuesday($tuesday): void
    {
        $this->days[2] = $tuesday;
    }

    /**
     * @return mixed $wednesday
     */
    public function getWednesday()
    {
        return $this->days[3];
    }

    /**
     * @param mixed $wednesday
     */
    public function setWednesday($wednesday): void
    {
        $this->days[3] = $wednesday;
    }

    /**
     * @return mixed $thursday
     */
    public function getThursday()
    {
        return $this->days[4];
    }

    /**
     * @param mixed $thursday
     */
    public function setThursday($thursday): void
    {
        $this->days[4] = $thursday;
    }

    /**
     * @return mixed $friday
     */
    public function getFriday()
    {
        return $this->days[5];
    }

    /**
     * @param mixed $friday
     */
    public function setFriday($friday): void
    {
        $this->days[5] = $friday;
    }

    /**
     * @return mixed $saturday
     */
    public function getSaturday()
    {
        return $this->days[6];
    }

    /**
     * @param mixed $saturday
     */
    public function setSaturday($saturday): void
    {
        $this->days[6] = $saturday;
    }

    /**
     * @return mixed $sunday
     */
    public function getSunday()
    {
        return $this->days[0];
    }

    /**
     * @param mixed $sunday
     */
    public function setSunday($sunday): void
    {
        $this->days[0] = $sunday;
    }

    /**
     * @return mixed $fixedRate
     */
    public function getFixedRate()
    {
        return $this->fixedRate;
    }

    /**
     * @param mixed $fixedRate
     */
    public function setFixedRate($fixedRate): void
    {
        $this->fixedRate = $fixedRate;
    }

    /**
     * @return mixed $hourlyRate
     */
    public function getHourlyRate()
    {
        return $this->hourlyRate;
    }

    /**
     * @param mixed $hourlyRate
     */
    public function setHourlyRate($hourlyRate): void
    {
        $this->hourlyRate = $hourlyRate;
    }

    /**
     * @return mixed $billableMode
     */
    public function getBillableMode()
    {
        return $this->billableMode;
    }

    /**
     * @param mixed $billableMode
     */
    public function setBillableMode($billableMode): void
    {
        $this->billableMode = $billableMode;
    }

    /**
     * @return mixed $exported
     */
    public function getExported()
    {
        return $this->exported;
    }

    /**
     * @param mixed $exported
     */
    public function setExported($exported): void
    {
        $this->exported = $exported;
    }
}
