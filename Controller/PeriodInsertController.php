<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Controller;

use App\Configuration\SystemConfiguration;
use App\Controller\AbstractController;
use App\Entity\Timesheet;
use App\Timesheet\TimesheetService;
use App\Utils\PageSetup;
use Exception;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use KimaiPlugin\PeriodInsertBundle\Form\PeriodInsertType;
use KimaiPlugin\PeriodInsertBundle\Repository\PeriodInsertRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/period_insert')]
#[IsGranted('period_insert')]
class PeriodInsertController extends AbstractController
{
    public function __construct(
        protected PeriodInsertRepository $repository,
        protected TimesheetService $service,
        protected SystemConfiguration $configuration)
    {
    }

    #[Route(path: '', name: 'period_insert', methods: ['GET', 'POST'])]
    public function indexAction(Request $request): Response
    {
        $entry = $this->service->createNewTimesheet($this->getUser(), $request);

        $entity = $this->repository->getTimesheet();
        $entity->setUser($this->getUser());
        $entity->setBeginTime($entry->getBegin());

        $form = $this->getInsertForm($entity, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var PeriodInsert $entity */
            $entity = $form->getData();
            $entity->setTimes();

            $dayToInsert = $this->findDayToInsert($entity);
            if ($dayToInsert === '') {
                $this->flashError('Could not find a day to insert in the given time range. Please reselect the time range or days to insert.');
            }
            else if (!$this->configuration->isTimesheetAllowFutureTimes() && ($dayToInsert > date('Y-m-d') || $this->checkTimestamp($entity))) {
                $this->flashError('The time range cannot be in the future.');
            }
            else if (!$this->configuration->isTimesheetAllowZeroDuration() && $entity->getDurationPerDay() === 0) {
                $this->flashError('Duration cannot be zero.');
            }
            else {
                $overlap = $this->repository->findOverlappingTimeEntry($entity);
                if (!$this->configuration->isTimesheetAllowOverlappingRecords() && $overlap !== '') {
                    $this->flashError('You already have an entry on ' . $overlap . '.');
                }
                else {
                    try {
                        $this->repository->saveTimesheet($entity);
                        $this->flashSuccess('action.update.success');

                        return $this->redirectToRoute('period_insert');
                    } catch (Exception $ex) {
                        $this->flashUpdateException($ex);
                    }
                }
            }
        }

        $page = new PageSetup('periodinsert.title');
        $page->setHelp('https://www.kimai.org/store/lnngyn-period-insert-bundle.html');

        return $this->render('@PeriodInsert/index.html.twig', [
            'page_setup' => $page,
            'route_back' => 'timesheet',
            'entity' => $entity,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param PeriodInsert $entity
     * @return bool
     */
    protected function checkTimestamp(PeriodInsert $entity): bool
    {
        $now = new \DateTime('now', $entity->getBeginTime()->getTimezone());

        // allow configured default rounding time + 1 minute
        $nowBeginTs = $now->getTimestamp() + ($this->configuration->getTimesheetDefaultRoundingBegin() * 60) + 60;
        $nowEndTs = $now->getTimestamp() + ($this->configuration->getTimesheetDefaultRoundingEnd() * 60) + 60;

        return $nowBeginTs < $entity->getBeginTime()->getTimestamp() || $nowEndTs < $entity->getEndTime()->getTimestamp();
    }

    /**
     * @param PeriodInsert $entity
     * @return string
     */
    protected function findDayToInsert(PeriodInsert $entity): string
    {
        $day = (int)$entity->getEnd()->format('w') - 7;
        $numberOfDays = $entity->getEnd()->diff($entity->getBegin())->format("%r%a") + $day;
        for ($end = clone $entity->getEnd(); $day >= $numberOfDays; $day--, $end->modify('-1 day')) {
            if ($entity->getDay($day)) {
                return $end->format('Y-m-d');
            }
        }
        return '';
    }

    /**
     * @param PeriodInsert $entity
     * @param Timesheet $entry
     * @return FormInterface
     */
    protected function getInsertForm(PeriodInsert $entity, Timesheet $entry)
    {
        return $this->createForm(PeriodInsertType::class, $entity, [
            'action' => $this->generateUrl('period_insert'),
            'include_user' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'include_rate' => $this->isGranted('edit_rate', $entry),
            'include_billable' => $this->isGranted('edit_billable', $entry),
            'include_exported' => $this->isGranted('edit_export', $entry),
            'allow_begin_datetime' => $this->service->getActiveTrackingMode()->canEditBegin(),
            'duration_minutes' => $this->configuration->getTimesheetIncrementDuration(),
            'timezone' => $this->getDateTimeFactory()->getTimezone()->getName(),
            'customer' => true,
        ]);
    }
}
