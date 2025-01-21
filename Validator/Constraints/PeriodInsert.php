<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class PeriodInsert extends Constraint
{
    public const MISSING_TIME_RANGE_ERROR = 'kimai-period-insert-bundle-01';
    public const MISSING_ACTIVITY_ERROR = 'kimai-period-insert-bundle-02';
    public const MISSING_PROJECT_ERROR = 'kimai-period-insert-bundle-03';
    public const ACTIVITY_PROJECT_MISMATCH_ERROR = 'kimai-period-insert-bundle-04';
    public const PROJECT_NOT_STARTED = 'kimai-period-insert-bundle-05';
    public const PROJECT_ALREADY_ENDED = 'kimai-period-insert-bundle-06';
    public const PROJECT_DISALLOWS_GLOBAL_ACTIVITY = 'kimai-period-insert-bundle-07';

    protected const ERROR_NAMES = [
        self::MISSING_TIME_RANGE_ERROR => 'You must submit a time range.',
        self::MISSING_ACTIVITY_ERROR => 'An activity needs to be selected.',
        self::MISSING_PROJECT_ERROR => 'A project needs to be selected.',
        self::ACTIVITY_PROJECT_MISMATCH_ERROR => 'Project mismatch, project specific activity and timesheet project are different.',
        self::PROJECT_NOT_STARTED => 'The project has not started at that time.',
        self::PROJECT_ALREADY_ENDED => 'The project is finished at that time.',
        self::PROJECT_DISALLOWS_GLOBAL_ACTIVITY => 'Global activities are forbidden for the selected project.',
    ];

    public string $message = 'This period insert has invalid settings.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}