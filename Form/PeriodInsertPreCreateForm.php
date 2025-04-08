<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Form;

use App\Form\FormTrait;
use App\Form\Type\DescriptionType;
use App\Form\Type\TagsInputType;
use App\Form\Type\UserType;
use App\Form\Type\YesNoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Values that are allowed to be pre-set via URL.
 */
final class PeriodInsertPreCreateForm extends AbstractType
{
    use FormTrait;

    /**
     * @param FormBuilderInterface $builder
     * @param array<string, string|bool|int|null|array<string, mixed>> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_user']) {
            $builder->add('user', UserType::class, ['required' => false]);
        }
        
        $this->addProject($builder, true, null, null, ['required' => false]);
        $this->addActivity($builder, null, null, ['required' => false]);

        $builder->add('description', DescriptionType::class, ['required' => false]);
        $builder->add('tags', TagsInputType::class, ['required' => false]);

        $builder->add('monday', YesNoType::class)
            ->add('tuesday', YesNoType::class)
            ->add('wednesday', YesNoType::class)
            ->add('thursday', YesNoType::class)
            ->add('friday', YesNoType::class)
            ->add('saturday', YesNoType::class)
            ->add('sunday', YesNoType::class);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'include_user' => false,
            'method' => 'GET',
            'validation_groups' => ['none'] // otherwise the default period insert validations would trigger
        ]);
    }
}