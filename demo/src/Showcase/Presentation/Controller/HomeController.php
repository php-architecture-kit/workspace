<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Showcase\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'showcase_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('showcase/home.html.twig', [
            'props' => [
                'title' => 'PHP Architecture Kit',
                'stack' => [
                    ['label' => 'PHP', 'value' => PHP_VERSION],
                    ['label' => 'Symfony', 'value' => Kernel::VERSION],
                ],
                'packages' => [
                    [
                        'name' => 'uuid',
                        'description' => 'Generate and inspect UUIDs (v1–v8, nil, max) through php-architecture-kit/uuid.',
                        'url' => $this->generateUrl('uuid_page'),
                    ],
                    [
                        'name' => 'lazy-operators',
                        'description' => 'Build an expression tree from data with ExpressionBuilder, bind its inputs and evaluate it.',
                        'url' => $this->generateUrl('lazy_operators_page'),
                    ],
                    [
                        'name' => 'state-machine',
                        'description' => 'Drive an ATM and an elevator: graph workflows that wait, time out and resume from a saved Execution.',
                        'url' => $this->generateUrl('state_machine_page'),
                    ],
                ],
            ],
        ]);
    }
}
