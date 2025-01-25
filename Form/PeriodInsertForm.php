<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Form;

use App\Configuration\SystemConfiguration;
use App\Entity\Customer;
use App\Entity\Timesheet;
use App\Form\TimesheetEditForm;
use App\Form\Type\DateRangeType;
use App\Form\Type\DescriptionType;
use App\Form\Type\DurationType;
use App\Form\Type\TagsType;
use App\Form\Type\TimePickerType;
use App\Form\Type\TimesheetBillableType;
use App\Form\Type\YesNoType;
use App\Repository\CustomerRepository;
use App\Repository\Query\CustomerFormTypeQuery;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class PeriodInsertForm extends TimesheetEditForm
{
    public function __construct(private CustomerRepository $customers, private SystemConfiguration $systemConfiguration)
    {
    }

    /**
     * @param FormBuilderInterface $builder
     * @param mixed[] $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $activity = null;
        $project = null;
        $customer = null;
        $currency = false;
        $isNew = true;

        $this->addUser($builder, $options);
        $this->addDateRange($builder, $options, false);

        $dateTimeOptions = [
            'model_timezone' => $options['timezone'],
            'view_timezone' => $options['timezone'],
        ];

        // primarily for API usage, where we cannot use a user/locale specific format
        if (null !== $options['date_format']) {
            $dateTimeOptions['format'] = $options['date_format'];
        }

        if ($options['allow_begin_datetime']) {
            $this->addBeginTime($builder, $dateTimeOptions);
        }
        
        $this->addDuration($builder, $options, false, $isNew);

        $query = new CustomerFormTypeQuery($customer);
        $query->setUser($options['user']); // @phpstan-ignore-line
        $qb = $this->customers->getQueryBuilderForFormType($query);
        /** @var Customer[] $customers */
        $customers = $qb->getQuery()->getResult();
        $customerCount = \count($customers);

        if ($this->showCustomer($options, $isNew, $customerCount)) {
            $this->addCustomer($builder, $customer);
        }
        
        $this->addProject($builder, $isNew, $project, $customer);
        $this->addActivity($builder, $activity, $project, [
            'allow_create' => false,
        ]);

        $this->addDescription($builder, $isNew);
        $this->addTags($builder);

        $this->addDays($builder);
        
        $this->addRates($builder, $currency, $options);
        $this->addBillable($builder, $options);
        $this->addExported($builder, $options);
    }

    protected function addDateRange(FormBuilderInterface $builder, array $options, bool $allowEmpty = true): void
    {
        $params = [
            'required' => !$allowEmpty,
            'allow_empty' => $allowEmpty,
        ];

        if (\array_key_exists('timezone', $options)) {
            $params['timezone'] = $options['timezone'];
        }

        $builder->add('daterange', DateRangeType::class, $params);

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {
                /** @var PeriodInsert $periodInsert */
                $periodInsert = $event->getData();
                $dateRange = $periodInsert->getDateRange();

                $hour = (int) $periodInsert->getBeginTime()->format('H');
                $minute = (int) $periodInsert->getBeginTime()->format('i');

                $dateRange->getBegin()->setTime($hour, $minute);
                $dateRange->getEnd()->setTime($hour, $minute);
                $dateRange->getEnd()->modify('+' . $periodInsert->getDuration() . ' seconds');
            }
        );
    }

    /**
     * @param FormBuilderInterface $builder
     * @param mixed[] $dateTimeOptions
     */
    protected function addBeginTime(FormBuilderInterface $builder, array $dateTimeOptions): void
    {
        $timeOptions = $dateTimeOptions;

        $builder->add('begin_time', TimePickerType::class, array_merge($timeOptions, [
            'label' => 'Begin',
            'constraints' => [
                new NotBlank()
            ]
        ]));
    }

    /**
     * @param FormBuilderInterface $builder
     * @param mixed[] $options
     * @param bool $forceApply
     * @param bool $autofocus
     */
    protected function addDuration(FormBuilderInterface $builder, array $options, bool $forceApply = false, bool $autofocus = false): void
    {
        $durationOptions = [
            'required' => true,
            //'toggle' => true,
            'attr' => [
                'placeholder' => '0:00',
            ],
        ];

        if ($autofocus) {
            $durationOptions['attr']['autofocus'] = 'autofocus';
        }

        $duration = $options['duration_minutes'];
        if ($duration !== null && (int) $duration > 0) {
            $durationOptions = array_merge($durationOptions, [
                'preset_minutes' => $duration
            ]);
        }

        $duration = $options['duration_hours'];
        if ($duration !== null && (int) $duration > 0) {
            $durationOptions = array_merge($durationOptions, [
                'preset_hours' => $duration,
            ]);
        }

        $builder->add('duration', DurationType::class, $durationOptions);
    }

    /**
     * @param FormBuilderInterface $builder
     * @param bool $isNew
     */
    protected function addDescription(FormBuilderInterface $builder, bool $isNew): void
    {
        $descriptionOptions = ['required' => false];
        if (!$isNew) {
            $descriptionOptions['attr'] = ['autofocus' => 'autofocus'];
        }

        $builder->add('description', DescriptionType::class, $descriptionOptions);
    }

    /**
     * @param FormBuilderInterface $builder
     */
    protected function addTags(FormBuilderInterface $builder): void
    {
        $builder->add('tags', TagsType::class, [
            'required' => false,
        ]);
    }

    /**
     * @param FormBuilderInterface $builder
     */
    protected function addDays(FormBuilderInterface $builder): void
    {
        $builder->add('monday', YesNoType::class)
            ->add('tuesday', YesNoType::class)
            ->add('wednesday', YesNoType::class)
            ->add('thursday', YesNoType::class)
            ->add('friday', YesNoType::class)
            ->add('saturday', YesNoType::class)
            ->add('sunday', YesNoType::class);
    }

    /**
     * @param FormBuilderInterface $builder
     * @param mixed[] $options
     */
    protected function addBillable(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_billable']) {
            $builder->add('billableMode', TimesheetBillableType::class, []);
        }

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {
                /** @var PeriodInsert $periodInsert */
                $periodInsert = $event->getData();

                switch ($periodInsert->getBillableMode()) {
                    case Timesheet::BILLABLE_NO:
                        $periodInsert->setBillable(false);
                        break;
                    case Timesheet::BILLABLE_YES:
                        $periodInsert->setBillable(true);
                        break;
                    case Timesheet::BILLABLE_AUTOMATIC:
                        $billable = true;
        
                        $activity = $periodInsert->getActivity();
                        if ($activity !== null && !$activity->isBillable()) {
                            $billable = false;
                        }
        
                        $project = $periodInsert->getProject();
                        if ($billable && $project !== null && !$project->isBillable()) {
                            $billable = false;
                        }
        
                        if ($billable && $project !== null) {
                            $customer = $project->getCustomer();
                            if ($customer !== null && !$customer->isBillable()) {
                                $billable = false;
                            }
                        }

                        $periodInsert->setBillable($billable);
                        break;
                }
            }
        );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $maxMinutes = $this->systemConfiguration->getTimesheetLongRunningDuration();
        $maxHours = 10;
        if ($maxMinutes > 0) {
            $maxHours = (int) ($maxMinutes / 60);
        }
        
        $resolver->setDefaults([
            'data_class' => PeriodInsert::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'period_insert',
            'include_user' => false,
            'include_rate' => true,
            'include_billable' => true,
            'include_exported' => false,
            'method' => 'POST',
            'date_format' => null,
            'timezone' => date_default_timezone_get(),
            'customer' => false, // for API usage
            'allow_begin_datetime' => true,
            'duration_minutes' => null,
            'duration_hours' => $maxHours,
            'attr' => [
                'data-form-event' => 'kimai.periodInsertUpdate',
                'data-msg-success' => 'action.update.success',
                'data-msg-error' => 'action.update.error',
            ],
        ]);
    }
}
