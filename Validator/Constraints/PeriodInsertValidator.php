<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Validator\Constraints;

use App\Activity\ActivityStatisticService;
use App\Configuration\LocaleService;
use App\Configuration\SystemConfiguration;
use App\Customer\CustomerStatisticService;
use App\Model\BudgetStatisticModel;
use App\Project\ProjectStatisticService;
use App\Repository\TimesheetRepository;
use App\Timesheet\RateServiceInterface;
use App\Utils\Duration;
use App\Utils\LocaleFormatter;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert as PeriodInsertEntity;
use KimaiPlugin\PeriodInsertBundle\Repository\PeriodInsertRepository;
use KimaiPlugin\PeriodInsertBundle\Validator\Constraints\PeriodInsert as PeriodInsertConstraint;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PeriodInsertValidator extends ConstraintValidator
{
    public function __construct(private readonly PeriodInsertRepository $repository,
        private readonly TimesheetRepository $timesheetRepository,
        private readonly SystemConfiguration $systemConfiguration,
        private readonly CustomerStatisticService $customerStatisticService,
        private readonly ProjectStatisticService $projectStatisticService,
        private readonly ActivityStatisticService $activityStatisticService,
        private readonly RateServiceInterface $rateService,
        private readonly AuthorizationCheckerInterface $security,
        private readonly LocaleService $localeService
    ) {
    }

    /**
     * @param mixed|PeriodInsertEntity $value
     * @param Constraint $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!($constraint instanceof PeriodInsertConstraint)) {
            throw new UnexpectedTypeException($constraint, PeriodInsertConstraint::class);
        }

        if (!\is_object($value) || !($value instanceof PeriodInsertEntity)) {
            throw new UnexpectedTypeException($value, PeriodInsertEntity::class);
        }

        /** @var DateTime $newBegin */
        $newBegin = clone $value->getBegin(); // @phpstan-ignore-line

        // this prevents the problem that "now" is being ignored in modify()
        $beginTime = new \DateTime($this->systemConfiguration->getTimesheetDefaultBeginTime(), $newBegin->getTimezone());
        $newBegin->setTime((int) $beginTime->format('H'), (int) $beginTime->format('i'), 0, 0);

        $value->setFields($newBegin);
        $this->repository->findHolidays($value);

        $this->validateTimeRange($value);
        $this->validateActivityAndProject($value);
        $this->validatePeriodInsert($value);
        $this->validateFutureTimes($value);
        $this->validateZeroDuration($value);
        $this->validateOverlapping($value);
        $this->validateBudgetUsed($value);
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    protected function validateTimeRange(PeriodInsertEntity $periodInsert): void
    {
        $dateRange = $periodInsert->getDateRange();

        if (null === $dateRange) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::MISSING_TIME_RANGE_ERROR))
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::MISSING_TIME_RANGE_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    protected function validateActivityAndProject(PeriodInsertEntity $periodInsert): void
    {
        $activity = $periodInsert->getActivity();

        if ($this->systemConfiguration->isTimesheetRequiresActivity() && null === $activity) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::MISSING_ACTIVITY_ERROR))
                ->atPath('activity')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::MISSING_ACTIVITY_ERROR)
                ->addViolation();
        }

        if (null === ($project = $periodInsert->getProject())) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::MISSING_PROJECT_ERROR))
                ->atPath('project')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::MISSING_PROJECT_ERROR)
                ->addViolation();
        }

        $hasActivity = null !== $activity;

        if (null === $project) {
            return;
        }

        if ($hasActivity && null !== $activity->getProject() && $activity->getProject() !== $project) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::ACTIVITY_PROJECT_MISMATCH_ERROR))
                ->atPath('project')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::ACTIVITY_PROJECT_MISMATCH_ERROR)
                ->addViolation();
        }

        if ($hasActivity && !$project->isGlobalActivities() && $activity->isGlobal()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_DISALLOWS_GLOBAL_ACTIVITY_ERROR))
                ->atPath('activity')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::PROJECT_DISALLOWS_GLOBAL_ACTIVITY_ERROR)
                ->addViolation();
        }

        $projectBegin = $project->getStart();
        $projectEnd = $project->getEnd();

        if (null === $projectBegin && null === $projectEnd) {
            return;
        }

        $periodInsertStart = $periodInsert->getBegin();
        $periodInsertEnd = $periodInsert->getEnd();

        if (null !== $periodInsertStart) {
            if (null !== $projectBegin && $periodInsertStart->getTimestamp() < $projectBegin->getTimestamp()) {
                $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_NOT_STARTED_ERROR))
                    ->atPath('daterange')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::PROJECT_NOT_STARTED_ERROR)
                    ->addViolation();

                    return;
            } elseif (null !== $projectEnd && $periodInsertStart->getTimestamp() > $projectEnd->getTimestamp()) {
                $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_ALREADY_ENDED_ERROR))
                    ->atPath('project')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::PROJECT_ALREADY_ENDED_ERROR)
                    ->addViolation();

                    return;
            }
        }

        if (null !== $periodInsertEnd) {
            if (null !== $projectEnd && $periodInsertEnd > $projectEnd) {
                $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_ALREADY_ENDED_ERROR))
                    ->atPath('daterange')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::PROJECT_ALREADY_ENDED_ERROR)
                    ->addViolation();
            } elseif (null !== $projectBegin && $periodInsertEnd < $projectBegin) {
                $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_NOT_STARTED_ERROR))
                    ->atPath('daterange')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::PROJECT_NOT_STARTED_ERROR)
                    ->addViolation();
            }
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    protected function validatePeriodInsert(PeriodInsertEntity $periodInsert): void
    {
        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('+1 day')) {
            if ($this->repository->checkDayValid($periodInsert, $begin)) {
                return;
            }
        }

        $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::MISSING_DAY_ERROR))
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::MISSING_DAY_ERROR)
                ->addViolation();
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    protected function validateFutureTimes(PeriodInsertEntity $periodInsert): void
    {
        if ($this->systemConfiguration->isTimesheetAllowFutureTimes()) {
            return;
        }

        $dayToInsert = null;
        for ($end = clone $periodInsert->getEnd(); $dayToInsert === null && $end >= $periodInsert->getBegin(); $end->modify('-1 day')) {
            if ($this->repository->checkDayValid($periodInsert, $end)) {
                $dayToInsert = $end->format('Y-m-d');
            }
        }

        if ($dayToInsert < date('Y-m-d')) {
            return;
        }

        if ($dayToInsert > date('Y-m-d')) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::TIME_RANGE_IN_FUTURE_ERROR))
                ->atPath('daterange')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::TIME_RANGE_IN_FUTURE_ERROR)
                ->addViolation();
            
            return;
        }

        $now = new \DateTime('now', $periodInsert->getBeginTime()->getTimezone());

        // allow configured default rounding time + 1 minute
        $nowBeginTs = $now->getTimestamp() + ($this->systemConfiguration->getTimesheetDefaultRoundingBegin() * 60) + 60;
        $nowEndTs = $now->getTimestamp() + ($this->systemConfiguration->getTimesheetDefaultRoundingEnd() * 60) + 60;

        if ($nowBeginTs < $periodInsert->getBegin()->getTimestamp()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::BEGIN_IN_FUTURE_ERROR))
                ->atPath('begin_time')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::BEGIN_IN_FUTURE_ERROR)
                ->addViolation();
        }
        else if ($nowEndTs < $periodInsert->getEnd()->getTimestamp()) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::END_IN_FUTURE_ERROR))
                ->atPath('duration')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::END_IN_FUTURE_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    protected function validateZeroDuration(PeriodInsertEntity $periodInsert): void
    {
        if ($this->systemConfiguration->isTimesheetAllowZeroDuration()) {
            return;
        }

        if ($periodInsert->getDuration() <= 0) {
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::ZERO_DURATION_ERROR))
                ->atPath('duration')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::ZERO_DURATION_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    protected function validateOverlapping(PeriodInsertEntity $periodInsert): void
    {
        $dateRange = $periodInsert->getDateRange();

        if (null === $dateRange) {
            return;
        }

        if ($this->systemConfiguration->isTimesheetAllowOverlappingRecords()) {
            return;
        }

        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('+1 day')) {
            if ($this->repository->checkDayValid($periodInsert, $begin) && $this->timesheetRepository->hasRecordForTime($this->repository->createTimesheet($periodInsert, $begin))) {
                $this->context->buildViolation('You already have an entry on ' . $begin->format('m/d/Y') . '.')
                    ->atPath('daterange')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::RECORD_OVERLAPPING_ERROR)
                    ->addViolation();
                
                return;
            }
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    protected function validateBudgetUsed(PeriodInsertEntity $periodInsert): void
    {   
        if ($this->systemConfiguration->isTimesheetAllowOverbookingBudget()) {
            return;
        }

        if (!$periodInsert->isBillable()) {
            return;
        }
        
        if ($periodInsert->getProject() === null) {
            return;
        }

        $totalValidDays = 0;
        $validDaysPerMonth = [];
        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('+1 day')) {
            if ($this->repository->checkDayValid($periodInsert, $begin)) {
                $totalValidDays++;
                $month = $begin->format('F Y');
                $validDaysPerMonth[$month] = ($validDaysPerMonth[$month] ?? 0) + 1;
            }
        }

        $recordDate = clone $periodInsert->getBegin();
        $duration = $periodInsert->getDuration();

        $timeRate = $this->rateService->calculate($this->repository->createTimesheet($periodInsert, $recordDate));
        $rate = $timeRate->getRate();

        $now = new \DateTime('now', $recordDate->getTimezone());
        
        foreach ($validDaysPerMonth as $month => $validDaysInMonth) {
            if (null !== ($activity = $periodInsert->getActivity()) && $activity->hasBudgets()) {
                $dateTime = $activity->isMonthlyBudget() ? $recordDate : $now;
                $validDays = $activity->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                $month = $activity->isMonthlyBudget() ? $month : '';
                $stat = $this->activityStatisticService->getBudgetStatisticModel($activity, $dateTime);
                if ($this->checkBudgets($stat, $periodInsert, $rate, $duration, $validDays, $month, 'activity')) {
                    return;
                }
            }
    
            if (null !== ($project = $periodInsert->getProject())) {
                if ($project->hasBudgets()) {
                    $dateTime = $project->isMonthlyBudget() ? $recordDate : $now;
                    $validDays = $project->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                    $month = $project->isMonthlyBudget() ? $month : '';
                    $stat = $this->projectStatisticService->getBudgetStatisticModel($project, $dateTime);
                    if ($this->checkBudgets($stat, $periodInsert, $rate, $duration, $validDays, $month, 'project')) {
                        return;
                    }
                }
                if (null !== ($customer = $project->getCustomer()) && $customer->hasBudgets()) {
                    $dateTime = $customer->isMonthlyBudget() ? $recordDate : $now;
                    $validDays = $customer->isMonthlyBudget() ? $validDaysInMonth : $totalValidDays;
                    $month = $customer->isMonthlyBudget() ? $month : '';
                    $stat = $this->customerStatisticService->getBudgetStatisticModel($customer, $dateTime);
                    if ($this->checkBudgets($stat, $periodInsert, $rate, $duration, $validDays, $month, 'customer')) {
                        return;
                    }
                }
            }
            $recordDate->modify('+1 month');
        }   
    }

    /**
     * @param BudgetStatisticModel $stat
     * @param PeriodInsertEntity $periodInsert
     * @param float $rate
     * @param int $duration
     * @param int $validDays
     * @param string $month
     * @param string $field
     * @return bool
     */
    protected function checkBudgets(BudgetStatisticModel $stat, PeriodInsertEntity $periodInsert, float $rate, int $duration, int $validDays, string $month, string $field): bool
    {
        $fullRate = $stat->getBudgetSpent() + $rate * $validDays;

        if ($stat->hasBudget() && $fullRate > $stat->getBudget()) {
            $this->addBudgetViolationMessage($periodInsert, $field, $month, $rate * $validDays, $stat->getBudget(), $stat->getBudgetSpent());
            
            return true;
        }

        $fullDuration = $stat->getTimeBudgetSpent() + $duration * $validDays;

        if ($stat->hasTimeBudget() && $fullDuration > $stat->getTimeBudget()) {
            $this->addTimeBudgetViolationMessage($field, $month, $duration * $validDays, $stat->getTimeBudget(), $stat->getTimeBudgetSpent());

            return true;
        }

        return false;
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     * @param string $field
     * @param string $month
     * @param float $insertRate
     * @param float $budget
     * @param float $rate
     */
    protected function addBudgetViolationMessage(PeriodInsertEntity $periodInsert, string $field, string $month, float $insertRate, float $budget, float $rate): void
    {
        $message = PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::BUDGET_USED_ERROR);
        if ($this->security->isGranted('budget_money', $field)) {
            // using the locale of the assigned user is not the best solution, but allows to be independent of the request stack
            $helper = new LocaleFormatter($this->localeService, $periodInsert->getUser()?->getLocale() ?? 'en');
            $currency = $periodInsert->getProject()->getCustomer()->getCurrency();

            $free = $budget - $rate;
            $free = max($free, 0);

            $used = $helper->money($rate, $currency);
            $budget = $helper->money($budget, $currency);
            $free = $helper->money($free, $currency);
            $insert = $helper->money($insertRate, $currency);

            $message = 'The budget is used up. Of the available ' . $budget . ', ' . $used . ' has been booked so far, ' . $free . ' can still be used. The selected period insert would use ' . $insert . ' in ' . $month . '.';
        }

        $this->context->buildViolation($message)
            ->atPath($field)
            ->setTranslationDomain('validators')
            ->setCode(PeriodInsertConstraint::BUDGET_USED_ERROR)
            ->addViolation();
    }

    /**
     * @param string $field
     * @param string $month
     * @param int $insertDuration
     * @param int $budget
     * @param int $duration
     */
    protected function addTimeBudgetViolationMessage(string $field, string $month, int $insertDuration, int $budget, int $duration): void
    {
        $message = PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::BUDGET_USED_ERROR);
        if ($this->security->isGranted('budget_time', $field)) {
            $durationFormat = new Duration();
            
            $free = $budget - $duration;
            $free = max($free, 0);

            $used = $durationFormat->format($duration);
            $budget = $durationFormat->format($budget);
            $free = $durationFormat->format($free);
            $insert = $durationFormat->format($insertDuration);

            $message = 'The budget is used up. Of the available ' . $budget . ', ' . $used . ' has been booked so far, ' . $free . ' can still be used. The selected period insert would use ' . $insert . ' in ' . $month . '.';
        }

        $this->context->buildViolation($message)
            ->atPath($field)
            ->setTranslationDomain('validators')
            ->setCode(PeriodInsertConstraint::BUDGET_USED_ERROR)
            ->addViolation();
    }
}