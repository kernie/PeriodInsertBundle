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
        private readonly SystemConfiguration $systemConfiguration,
        private readonly CustomerStatisticService $customerStatisticService,
        private readonly ProjectStatisticService $projectStatisticService,
        private readonly ActivityStatisticService $activityStatisticService,
        private readonly RateServiceInterface $rateService,
        private readonly AuthorizationCheckerInterface $security,
        private readonly LocaleService $localeService)
    {
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

        $this->validateBeginAndEnd($value);
        $this->validateActivityAndProject($value);
        $this->validateBudget($value);
    }

    protected function validateBeginAndEnd(PeriodInsertEntity $periodInsert): void
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
            $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_DISALLOWS_GLOBAL_ACTIVITY))
                ->atPath('activity')
                ->setTranslationDomain('validators')
                ->setCode(PeriodInsertConstraint::PROJECT_DISALLOWS_GLOBAL_ACTIVITY)
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
                $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_NOT_STARTED))
                    ->atPath('daterange')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::PROJECT_NOT_STARTED)
                    ->addViolation();

                    return;
            } elseif (null !== $projectEnd && $periodInsertStart->getTimestamp() > $projectEnd->getTimestamp()) {
                $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_ALREADY_ENDED))
                    ->atPath('daterange')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::PROJECT_ALREADY_ENDED)
                    ->addViolation();

                    return;
            }
        }

        if (null !== $periodInsertEnd) {
            if (null !== $projectEnd && $periodInsertEnd > $projectEnd) {
                $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_ALREADY_ENDED))
                    ->atPath('daterange')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::PROJECT_ALREADY_ENDED)
                    ->addViolation();
            } elseif (null !== $projectBegin && $periodInsertEnd < $projectBegin) {
                $this->context->buildViolation(PeriodInsertConstraint::getErrorName(PeriodInsertConstraint::PROJECT_NOT_STARTED))
                    ->atPath('daterange')
                    ->setTranslationDomain('validators')
                    ->setCode(PeriodInsertConstraint::PROJECT_NOT_STARTED)
                    ->addViolation();
            }
        }
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     */
    protected function validateBudget(PeriodInsertEntity $periodInsert): void
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
        $holidays = $this->repository->findHolidays($periodInsert);
        for ($begin = clone $periodInsert->getBegin(); $begin <= $periodInsert->getEnd(); $begin->modify('+1 day')) {
            if ($this->repository->checkDayValid($periodInsert, $begin, $holidays)) {
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
            $this->addBudgetViolationMessage($periodInsert, $field, $month, $fullRate, $stat->getBudget(), $stat->getBudgetSpent());
            
            return true;
        }

        $fullDuration = $stat->getTimeBudgetSpent() + $duration * $validDays;

        if ($stat->hasTimeBudget() && $fullDuration > $stat->getTimeBudget()) {
            $this->addTimeBudgetViolationMessage($field, $month, $fullDuration, $stat->getTimeBudget(), $stat->getTimeBudgetSpent());

            return true;
        }

        return false;
    }

    /**
     * @param PeriodInsertEntity $periodInsert
     * @param string $field
     * @param string $month
     * @param float $fullRate
     * @param float $budget
     * @param float $rate
     */
    protected function addBudgetViolationMessage(PeriodInsertEntity $periodInsert, string $field, string $month, float $fullRate, float $budget, float $rate): void
    {
        $message = 'Sorry, the budget is used up.';
        if ($this->security->isGranted('budget_money', $field)) {
            // using the locale of the assigned user is not the best solution, but allows to be independent of the request stack
            $helper = new LocaleFormatter($this->localeService, $periodInsert->getUser()?->getLocale() ?? 'en');
            $currency = $periodInsert->getProject()->getCustomer()->getCurrency();

            $free = $budget - $rate;
            $free = max($free, 0);

            $used = $helper->money($rate, $currency);
            $budget = $helper->money($budget, $currency);
            $free = $helper->money($free, $currency);
            $full = $helper->money($fullRate, $currency);

            $message = 'The budget is used up. Of the available ' . $budget . ', ' . $used . ' has been booked so far, ' . $free . ' can still be used. The selected period insert would use ' . $full . ' in ' . $month . '.';
        }

        $this->context->buildViolation($message)
            ->atPath($field)
            ->setTranslationDomain('validators')
            ->addViolation()
        ;
    }

    /**
     * @param string $field
     * @param string $month
     * @param int $fullDuration
     * @param int $budget
     * @param int $duration
     */
    protected function addTimeBudgetViolationMessage(string $field, string $month, int $fullDuration, int $budget, int $duration): void
    {
        $message = 'Sorry, the budget is used up.';
        if ($this->security->isGranted('budget_time', $field)) {
            $durationFormat = new Duration();
            
            $free = $budget - $duration;
            $free = max($free, 0);

            $used = $durationFormat->format($duration);
            $budget = $durationFormat->format($budget);
            $free = $durationFormat->format($free);
            $full = $durationFormat->format($fullDuration);

            $message = 'The budget is used up. Of the available ' . $budget . ', ' . $used . ' has been booked so far, ' . $free . ' can still be used. The selected period insert would use ' . $full . ' in ' . $month . '.';
        }

        $this->context->buildViolation($message)
            ->atPath($field)
            ->setTranslationDomain('validators')
            ->addViolation()
        ;
    }
}