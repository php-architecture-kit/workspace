<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\LazyOperators\Presentation\Controller;

use PhpArchitecture\Demo\LazyOperators\Application\Exception\PlaygroundFailure;
use PhpArchitecture\Demo\LazyOperators\Application\ExpressionPlayground;
use PhpArchitecture\Demo\LazyOperators\Application\ExpressionPresets;
use PhpArchitecture\Demo\LazyOperators\Presentation\View\CatalogViewFactory;
use PhpArchitecture\LazyOperators\Application\Builder\Exception\InvalidExpressionDefinitionException;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/lazy-operators')]
final class LazyOperatorsController extends AbstractController
{
    public function __construct(
        private readonly ExpressionPlayground $playground,
        private readonly ExpressionPresets $presets,
        private readonly CatalogViewFactory $catalog,
    ) {
    }

    #[Route('', name: 'lazy_operators_page', methods: ['GET'])]
    public function page(): Response
    {
        return $this->render('lazy_operators/page.html.twig', [
            'props' => [
                'catalog' => $this->catalog->create(),
                'presets' => $this->presets->all(),
                'evaluateUrl' => $this->generateUrl('lazy_operators_evaluate'),
                'homeUrl' => $this->generateUrl('showcase_home'),
            ],
        ]);
    }

    #[Route('/api/evaluate', name: 'lazy_operators_evaluate', methods: ['POST'])]
    public function evaluate(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $definition = $payload['definition'] ?? null;
        $inputs = $payload['inputs'] ?? [];

        try {
            $result = $this->playground->evaluate(
                is_array($definition) ? $definition : [],
                is_array($inputs) ? $inputs : [],
            );
        } catch (PlaygroundFailure $failure) {
            $cause = $failure->cause;

            return $this->json([
                'error' => [
                    'stage' => $failure->stage,
                    'input' => $failure->input,
                    'exception' => (new ReflectionClass($cause))->getShortName(),
                    'exceptionClass' => $cause::class,
                    'message' => $cause instanceof InvalidExpressionDefinitionException ? $cause->reason : $cause->getMessage(),
                    'path' => $cause instanceof InvalidExpressionDefinitionException ? $cause->path : null,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['result' => $result]);
    }
}
