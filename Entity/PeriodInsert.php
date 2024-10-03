<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Entity;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\User;
use App\Form\Model\DateRange;
use Doctrine\Common\Collections\Collection;

class PeriodInsert
{
    private ?User $user = null;
    private ?DateRange $beginToEnd = null;
    private ?\DateTime $beginTime = null;
    private ?\DateTime $endTime = null;
    private ?int $duration = 0;
    private ?Project $project = null;
    private ?Activity $activity = null;
    private ?string $description = '';
    /**
     * @var Tag[]
     */
    private Collection $tags;
    /**
     * @var bool[]
     */
    private array $days = [true, true, true, true, true, true, true];
    private ?float $fixedRate = null;
    private ?float $hourlyRate = null;
    private bool $billable = true;
    private string $billableMode = 'auto';
    private bool $exported = false;

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     */
    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return DateRange|null
     */
    public function getBeginToEnd(): ?DateRange
    {
        return $this->beginToEnd;
    }

    /**
     * @param DateRange|null $begintoEnd
     */
    public function setBeginToEnd(?DateRange $beginToEnd): void
    {
        $this->beginToEnd = $beginToEnd;
    }

    /**
     * @return DateTime|null
     */
    public function getBegin(): ?\DateTime
    {
        if ($this->beginToEnd !== null) {
            return $this->beginToEnd->getBegin();
        }
        return null;
    }

    /**
     * @return DateTime|null
     */
    public function getEnd(): ?\DateTime
    {
        if ($this->beginToEnd !== null) {
            return $this->beginToEnd->getEnd();
        }
        return null;
    }

    /**
     * @return DateTime|null
     */
    public function getBeginTime(): ?\DateTime
    {
        return $this->beginTime;
    }

    /**
     * @param DateTime|null $beginTime
     */
    public function setBeginTime(?\DateTime $beginTime): void
    {
        $this->beginTime = $beginTime;
    }

    /**
     * @return DateTime|null
     */
    public function getEndTime(): ?\DateTime
    {
        return $this->endTime;
    }

    public function setFields(): void
    {
        $this->beginToEnd->getBegin()->setTime($this->beginTime->format('H'), $this->beginTime->format('i'));
        $this->endTime = (clone $this->beginTime)->modify('+' . $this->duration . ' seconds');
        $this->beginToEnd->getEnd()->setTime($this->endTime->format('H'), $this->endTime->format('i'));
        $this->billable = $this->calculateBillable($this->billableMode);
    }

    /**
     * @return int|null
     */
    public function getDuration(): ?int
    {
        return $this->duration;
    }

    /**
     * @param int|null $duration
     */
    public function setDuration(?int $duration)
    {
        if ($duration !== null) {
            $secondsInADay = 24*60*60;
            $duration = $duration % $secondsInADay;
        }
        $this->duration = $duration;
    }
    
    /**
     * @return Project|null
     */
    public function getProject(): ?Project
    {
        return $this->project;
    }

    /**
     * @param Project|null $project
     */
    public function setProject(?Project $project): void
    {
        $this->project = $project;
    }

    /**
     * @return Activity|null
     */
    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    /**
     * @param Activity|null $activity
     */
    public function setActivity(?Activity $activity): void
    {
        $this->activity = $activity;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return Collection<Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @param Collection<Tag> $tags
     */
    public function setTags(Collection $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @return bool
     */
    public function isDayValid(\DateTime $day): bool
    {
        $day = (int)$day->format('w') % 7;
        return $this->days[$day < 0 ? $day + 7 : $day];
    }

    /**
     * @return bool
     */
    public function getMonday(): bool
    {
        return $this->days[1];
    }

    /**
     * @param bool $monday
     */
    public function setMonday(bool $monday): void
    {
        $this->days[1] = $monday;
    }

    /**
     * @return bool
     */
    public function getTuesday(): bool
    {
        return $this->days[2];
    }

    /**
     * @param bool $tuesday
     */
    public function setTuesday(bool $tuesday): void
    {
        $this->days[2] = $tuesday;
    }

    /**
     * @return bool
     */
    public function getWednesday(): bool
    {
        return $this->days[3];
    }

    /**
     * @param bool $wednesday
     */
    public function setWednesday(bool $wednesday): void
    {
        $this->days[3] = $wednesday;
    }

    /**
     * @return bool
     */
    public function getThursday(): bool
    {
        return $this->days[4];
    }

    /**
     * @param bool $thursday
     */
    public function setThursday(bool $thursday): void
    {
        $this->days[4] = $thursday;
    }

    /**
     * @return bool
     */
    public function getFriday(): bool
    {
        return $this->days[5];
    }

    /**
     * @param bool $friday
     */
    public function setFriday(bool $friday): void
    {
        $this->days[5] = $friday;
    }

    /**
     * @return bool
     */
    public function getSaturday(): bool
    {
        return $this->days[6];
    }

    /**
     * @param bool $saturday
     */
    public function setSaturday(bool $saturday): void
    {
        $this->days[6] = $saturday;
    }

    /**
     * @return bool
     */
    public function getSunday(): bool
    {
        return $this->days[0];
    }

    /**
     * @param bool $sunday
     */
    public function setSunday(bool $sunday): void
    {
        $this->days[0] = $sunday;
    }

    /**
     * @return float|null
     */
    public function getFixedRate(): ?float
    {
        return $this->fixedRate;
    }

    /**
     * @param float|null $fixedRate
     */
    public function setFixedRate(?float $fixedRate): void
    {
        $this->fixedRate = $fixedRate;
    }

    /**
     * @return float|null
     */
    public function getHourlyRate(): ?float
    {
        return $this->hourlyRate;
    }

    /**
     * @param float|null $hourlyRate
     */
    public function setHourlyRate(?float $hourlyRate): void
    {
        $this->hourlyRate = $hourlyRate;
    }

    /**
     * @return bool
     */
    public function isBillable(): bool
    {
        return $this->billable;
    }

    /**
     * @return bool
     */
    public function calculateBillable(): bool
    {
        if ($this->billableMode === 'auto') {
            if ($this->activity !== null && !$this->activity->isBillable()) {
                return false;
            }
            
            if ($this->project !== null) {
                if (!$this->project->isBillable()) {
                    return false;
                }
                
                $customer = $this->project->getCustomer();
                if ($customer !== null && !$customer->isBillable()) {
                    return false;
                }
            }
            return true;
        }
        return $this->billableMode === 'yes';
    }

    /**
     * @return string
     */
    public function getBillableMode(): string
    {
        return $this->billableMode;
    }

    /**
     * @param string $billableMode
     */
    public function setBillableMode(string $billableMode): void
    {
        $this->billableMode = $billableMode;
    }

    /**
     * @return bool
     */
    public function getExported(): bool
    {
        return $this->exported;
    }

    /**
     * @param bool $exported
     */
    public function setExported(bool $exported): void
    {
        $this->exported = $exported;
    }
}
