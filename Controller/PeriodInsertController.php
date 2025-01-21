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
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use KimaiPlugin\PeriodInsertBundle\Form\PeriodInsertType;
use KimaiPlugin\PeriodInsertBundle\Repository\PeriodInsertRepository;
use Symfony\Component\Form\FormInterface;
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
        protected TimesheetService $timesheetService,
        protected SystemConfiguration $configuration
    ) {
    }

    #[Route(path: '', name: 'period_insert', methods: ['GET', 'POST'])]
    public function indexAction(Request $request): Response
    {
        $timesheet = $this->timesheetService->createNewTimesheet($this->getUser(), $request);

        $periodInsert = new PeriodInsert();
        $periodInsert->setUser($this->getUser());

        $form = $this->getInsertForm($periodInsert, $timesheet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var PeriodInsert $periodInsert */
            $periodInsert = $form->getData();
            $periodInsert->setFields($timesheet->getBegin());
            
            if (($dayToInsert = $this->repository->findDayToInsert($periodInsert)) === '') {
                $this->flashError('Could not find a day to insert in the given time range.');
            }
            else if ($this->repository->checkFutureTime($periodInsert, $dayToInsert)) {
                $this->flashError('The time range cannot be in the future.');
            }
            else if ($this->repository->checkZeroDuration($periodInsert)) {
                $this->flashError('Duration cannot be zero.');
            }
            else if (($overlap = $this->repository->checkOverlappingTimeEntries($periodInsert)) !== '') {
                $this->flashError('You already have an entry on ' . $overlap . '.');
            }
            else if (($message = $this->repository->checkBudgetOverbooked($periodInsert)) !== '') {
                $this->flashError($message);
            }
            else {
                try {
                    $this->repository->saveTimesheet($periodInsert);
                    $this->flashSuccess('action.update.success');

                    return $this->redirectToRoute('period_insert');
                } catch (\Exception $ex) {
                    $this->flashUpdateException($ex);
                }
            }
        }

        return $this->render('@PeriodInsert/index.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'route_back' => 'timesheet',
            'period_insert' => $periodInsert,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param PeriodInsert $periodInsert
     * @param Timesheet $timesheet
     * @return FormInterface
     */
    protected function getInsertForm(PeriodInsert $periodInsert, Timesheet $timesheet): FormInterface
    {
        return $this->createForm(PeriodInsertType::class, $periodInsert, [
            'action' => $this->generateUrl('period_insert'),
            'include_user' => $this->isGranted('create_other_timesheet'),
            'include_rate' => $this->isGranted('edit_rate', $timesheet),
            'include_billable' => $this->isGranted('edit_billable', $timesheet),
            'include_exported' => $this->isGranted('edit_export', $timesheet),
            'allow_begin_datetime' => $this->timesheetService->getActiveTrackingMode()->canEditBegin(),
            'duration_minutes' => $this->configuration->getTimesheetIncrementDuration(),
            'timezone' => $this->getDateTimeFactory()->getTimezone()->getName(),
            'customer' => true,
        ]);
    }

    /**
     * @return PageSetup
     */
    protected function createPageSetup(): PageSetup
    {
        $page = new PageSetup('periodinsert.title');
        $page->setHelp('https://www.kimai.org/store/lnngyn-period-insert-bundle.html');

        return $page;
    }
}
