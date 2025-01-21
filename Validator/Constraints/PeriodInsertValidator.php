<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Validator\Constraints;

use App\Configuration\SystemConfiguration;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert as PeriodInsertEntity;
use KimaiPlugin\PeriodInsertBundle\Repository\PeriodInsertRepository;
use KimaiPlugin\PeriodInsertBundle\Validator\Constraints\PeriodInsert as PeriodInsertConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PeriodInsertValidator extends ConstraintValidator
{
    public function __construct(private readonly PeriodInsertRepository $repository,
        private readonly SystemConfiguration $systemConfiguration)
    {
    }

    /**
     * @param mixed|PeriodInsertEntity $value
     * @param Constraint $constraint
     * @return void
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
}