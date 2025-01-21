<?php
/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Repository;

use App\Activity\ActivityStatisticService;
use App\Configuration\LocaleService;
use App\Configuration\SystemConfiguration;
use App\Customer\CustomerStatisticService;
use App\Entity\Timesheet;
use App\Model\BudgetStatisticModel;
use App\Project\ProjectStatisticService;
use App\Repository\TimesheetRepository;
use App\Timesheet\RateServiceInterface;
use App\Timesheet\TimesheetService;
use App\Utils\Duration;
use App\Utils\LocaleFormatter;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PeriodInsertRepository
{
    public function __construct(private TimesheetRepository $timesheetRepository,
        private readonly SystemConfiguration $configuration,
        private readonly CustomerStatisticService $customerStatisticService,
        private readonly ProjectStatisticService $projectStatisticService,
        private readonly ActivityStatisticService $activityStatisticService,
        private readonly RateServiceInterface $rateService,
        private readonly AuthorizationCheckerInterface $security,
        private readonly LocaleService $localeService,
        private readonly WorkingTimeService $workService,
        private readonly TimesheetService $timesheetService,
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
     * @return string
     */
    public function checkBudgetOverbooked(PeriodInsert $periodInsert): string
    {   
        if ($this->configuration->isTimesheetAllowOverbookingBudget()) {
            return '';
        }

        if (!$periodInsert->isBillable()) {
            return '';
        }
        
        if ($periodInsert->getProject() === null) {
            return '';
        }

        $totalValidDays = 0;
        $validDaysPerMonth = [];
        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('+1 day')) {
            if ($periodInsert->isDaySelected($begin)) {
                $totalValidDays++;
                $validDaysPerMonth[$begin->format('Y-m')] = ($validDaysPerMonth[$begin->format('Y-m')] ?? 0) + 1;
            }
        }

        $recordDate = clone $periodInsert->getBegin();
        $duration = $periodInsert->getDuration();

        $timeRate = $this->rateService->calculate($this->createTimesheet($periodInsert, $recordDate));
        $rate = $timeRate->getRate();

        $now = new \DateTime('now', $recordDate->getTimezone());
        
        foreach ($validDaysPerMonth as $validDaysInMonth) {
            if (null !== ($activity = $periodInsert->getActivity()) && $activity->hasBudgets()) {
                $dateTime = $activity->isMonthlyBudget() ? $recordDate : $now;
                $validDays = $activity->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                $stat = $this->activityStatisticService->getBudgetStatisticModel($activity, $dateTime);
                if (($message = $this->checkBudgets($stat, $periodInsert, $duration, $rate, $validDays, 'activity')) !== '') {
                    return $message;
                }
            }
    
            if (null !== ($project = $periodInsert->getProject())) {
                if ($project->hasBudgets()) {
                    $dateTime = $project->isMonthlyBudget() ? $recordDate : $now;
                    $validDays = $project->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                    $stat = $this->projectStatisticService->getBudgetStatisticModel($project, $dateTime);
                    if (($message = $this->checkBudgets($stat, $periodInsert, $duration, $rate, $validDays, 'project')) !== '') {
                        return $message;
                    }
                }
                if (null !== ($customer = $project->getCustomer()) && $customer->hasBudgets()) {
                    $dateTime = $customer->isMonthlyBudget() ? $recordDate : $now;
                    $validDays = $customer->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                    $stat = $this->customerStatisticService->getBudgetStatisticModel($customer, $dateTime);
                    if (($message = $this->checkBudgets($stat, $periodInsert, $duration, $rate, $validDays, 'customer')) !== '') {
                        return $message;
                    }
                }
            }
            $recordDate->modify('+1 month');
        }
        return '';
    }

    /**
     * @param BudgetStatisticModel $stat
     * @param PeriodInsert $periodInsert
     * @param int $duration
     * @param float $rate
     * @param int $validDays
     * @param string $field
     * @return string
     */
    protected function checkBudgets(BudgetStatisticModel $stat, PeriodInsert $periodInsert, int $duration, float $rate, int $validDays, string $field): string
    {
        $fullRate = $stat->getBudgetSpent() + $rate * $validDays;

        if ($stat->hasBudget() && $fullRate > $stat->getBudget()) {
            return $this->getBudgetViolationMessage($periodInsert, $field, $stat->getBudget(), $stat->getBudgetSpent());
        }

        $fullDuration = $stat->getTimeBudgetSpent() + $duration * $validDays;

        if ($stat->hasTimeBudget() && $fullDuration > $stat->getTimeBudget()) {
            return $this->getTimeBudgetViolationMessage($field, $stat->getTimeBudget(), $stat->getTimeBudgetSpent());
        }

        return '';
    }

    /**
     * @param PeriodInsert $periodInsert
     * @param string $field
     * @param float $budget
     * @param float $rate
     * @return string
     */
    protected function getBudgetViolationMessage(PeriodInsert $periodInsert, string $field, float $budget, float $rate): string
    {
        if (!$this->security->isGranted('budget_money', $field)) {
            return 'Sorry, the budget is used up.';
        }
        // using the locale of the assigned user is not the best solution, but allows to be independent of the request stack
        $helper = new LocaleFormatter($this->localeService, $periodInsert->getUser()?->getLocale() ?? 'en');
        $currency = $periodInsert->getProject()->getCustomer()->getCurrency();

        $free = $budget - $rate;
        $free = max($free, 0);
        $used = $helper->money($rate, $currency);
        $budget = $helper->money($budget, $currency);
        $free = $helper->money($free, $currency);

        return 'The budget is used up. Of the available ' . $budget . ', ' . $used . ' has been booked so far, ' . $free . ' can still be used.';
    }

    /**
     * @param string $field
     * @param int $budget
     * @param int $duration
     * @return string
     */
    protected function getTimeBudgetViolationMessage(string $field, int $budget, int $duration): string
    {
        if (!$this->security->isGranted('budget_time', $field)) {
            return 'Sorry, the budget is used up.';
        }
        $durationFormat = new Duration();

        $free = $budget - $duration;
        $free = max($free, 0);

        $used = $durationFormat->format($duration);
        $budget = $durationFormat->format($budget);
        $free = $durationFormat->format($free);

        return 'The budget is used up. Of the available ' . $budget . ', ' . $used . ' has been booked so far, ' . $free . ' can still be used.';
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
    protected function checkDayValid(PeriodInsert $periodInsert, \DateTime $begin, array $holidays): bool
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
