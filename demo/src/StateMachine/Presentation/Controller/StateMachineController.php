<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Presentation\Controller;

use PhpArchitecture\Demo\StateMachine\Application\Exception\ActionRejected;
use PhpArchitecture\Demo\StateMachine\Application\ScenarioRunner;
use PhpArchitecture\Demo\StateMachine\Presentation\View\SnapshotViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/state-machine')]
final class StateMachineController extends AbstractController
{
    public function __construct(
        private readonly ScenarioRunner $runner,
        private readonly SnapshotViewFactory $view,
    ) {
    }

    #[Route('', name: 'state_machine_page', methods: ['GET'])]
    public function page(): Response
    {
        $scenarios = [];
        foreach ($this->runner->scenarios() as $id => $scenario) {
            $result = $this->runner->run($id);
            $scenarios[] = [
                'id' => $id,
                'title' => $scenario->title(),
                'graph' => $this->view->graph($id, $result->machine),
                'snapshot' => $this->view->snapshot($result),
                'actionUrl' => $this->generateUrl('state_machine_action', ['scenario' => $id]),
            ];
        }

        return $this->render('state_machine/page.html.twig', [
            'props' => [
                'scenarios' => $scenarios,
                'homeUrl' => $this->generateUrl('showcase_home'),
            ],
        ]);
    }

    #[Route('/api/{scenario}', name: 'state_machine_action', requirements: ['scenario' => 'atm|elevator'], methods: ['POST'])]
    public function act(string $scenario, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $action = is_string($body['action'] ?? null) ? $body['action'] : null;
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];

        try {
            $result = $this->runner->run($scenario, $action, $payload);
        } catch (ActionRejected $rejected) {
            return $this->json(['error' => $rejected->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['snapshot' => $this->view->snapshot($result)]);
    }
}
