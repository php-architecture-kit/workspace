<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Uuid\Presentation\Controller;

use PhpArchitecture\Demo\Uuid\Application\Exception\InvalidFormInputException;
use PhpArchitecture\Demo\Uuid\Application\UuidDetails;
use PhpArchitecture\Demo\Uuid\Application\UuidPlayground;
use PhpArchitecture\Uuid\Foundation\Exception\UuidException;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/uuid')]
final class UuidController extends AbstractController
{
    public function __construct(
        private readonly UuidPlayground $playground,
    ) {
    }

    #[Route('', name: 'uuid_page', methods: ['GET'])]
    public function page(): Response
    {
        return $this->render('uuid/page.html.twig', [
            'props' => [
                'methods' => UuidPlayground::METHODS,
                'namespaces' => UuidPlayground::NAMESPACES,
                'generateUrl' => $this->generateUrl('uuid_generate'),
                'inspectUrl' => $this->generateUrl('uuid_inspect'),
                'homeUrl' => $this->generateUrl('showcase_home'),
            ],
        ]);
    }

    #[Route('/api/generate', name: 'uuid_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $input = $request->toArray();

        return $this->respond(fn (): UuidDetails => $this->playground->generate((string) ($input['method'] ?? ''), $input));
    }

    #[Route('/api/inspect', name: 'uuid_inspect', methods: ['POST'])]
    public function inspect(Request $request): JsonResponse
    {
        $input = $request->toArray();

        return $this->respond(fn (): UuidDetails => $this->playground->inspect((string) ($input['value'] ?? '')));
    }

    /**
     * @param callable(): UuidDetails $call
     */
    private function respond(callable $call): JsonResponse
    {
        try {
            return $this->json(['uuid' => $call()]);
        } catch (InvalidFormInputException $exception) {
            return $this->error('form', $exception, $exception->field);
        } catch (UuidException $exception) {
            /** @var UuidException&Throwable $exception */
            return $this->error('package', $exception);
        }
    }

    private function error(string $source, Throwable $exception, ?string $field = null): JsonResponse
    {
        return $this->json([
            'error' => [
                'source' => $source,
                'exception' => (new ReflectionClass($exception))->getShortName(),
                'exceptionClass' => $exception::class,
                'message' => $exception->getMessage(),
                'field' => $field,
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
