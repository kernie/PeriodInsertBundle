<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration;
use App\Form\Type\YesNoType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    /**
     * @param SystemConfigurationEvent $event
     */
    public function onSystemConfiguration(SystemConfigurationEvent $event): void
    {
        $event->addConfiguration(
            (new SystemConfiguration('periodinsert'))
            ->setTranslationDomain('messages')
            ->setConfiguration([
                (new Configuration('periodinsert.include_absences'))
                    ->setLabel('periodinsert.include_absences')
                    ->setType(YesNoType::class),
                (new Configuration('periodinsert.include_nonworkdays'))
                    ->setLabel('periodinsert.include_nonworkdays')
                    ->setType(YesNoType::class),
            ])
        );
    }
}
