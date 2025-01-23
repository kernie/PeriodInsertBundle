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
use App\Form\TimesheetEditForm;
use App\Form\Toolbar\ToolbarFormTrait;
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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PeriodInsertType extends TimesheetEditForm
{
    use ToolbarFormTrait;

    public function __construct(private CustomerRepository $customers, private SystemConfiguration $systemConfiguration)
    {
    }

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
        /** @var array<Customer> $customers */
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

    protected function addDescription(FormBuilderInterface $builder, bool $isNew): void
    {
        $descriptionOptions = ['required' => false];
        if (!$isNew) {
            $descriptionOptions['attr'] = ['autofocus' => 'autofocus'];
        }
        $builder->add('description', DescriptionType::class, $descriptionOptions);
    }

    protected function addTags(FormBuilderInterface $builder): void
    {
        $builder->add('tags', TagsType::class, [
            'required' => false,
        ]);
    }

    protected function addDays(FormBuilderInterface $builder): void
    {
        $builder->add('monday', YesNoType::class, [
            'label' => 'Monday'
        ]);
        $builder->add('tuesday', YesNoType::class, [
            'label' => 'Tuesday'
        ]);
        $builder->add('wednesday', YesNoType::class, [
            'label' => 'Wednesday'
        ]);
        $builder->add('thursday', YesNoType::class, [
            'label' => 'Thursday'
        ]);
        $builder->add('friday', YesNoType::class, [
            'label' => 'Friday'
        ]);
        $builder->add('saturday', YesNoType::class, [
            'label' => 'Saturday'
        ]);
        $builder->add('sunday', YesNoType::class, [
            'label' => 'Sunday'
        ]);
    }

    protected function addBillable(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_billable']) {
            $builder->add('billableMode', TimesheetBillableType::class, []);
        }
    }

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
