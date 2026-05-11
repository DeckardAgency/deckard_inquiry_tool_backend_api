<?php

namespace App\Controller;

use App\Dto\CompleteInvitationInput;
use App\State\Processor\InvitationCompletedProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
class InvitationCompleteController extends AbstractController
{
    public function __construct(
        private readonly InvitationCompletedProcessor $processor,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function __invoke(string $token, Request $request): JsonResponse
    {
        // Deserialize the request body into CompleteInvitationInput
        $data = $this->serializer->deserialize(
            $request->getContent(),
            CompleteInvitationInput::class,
            'json'
        );

        // Validate the input
        $errors = $this->validator->validate($data);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            throw new UnprocessableEntityHttpException(
                json_encode([
                    'title' => 'Validation Failed',
                    'detail' => 'The provided data is invalid',
                    'violations' => $errorMessages
                ])
            );
        }

        try {
            $output = $this->processor->process($data, null, ['token' => $token], []);

            return $this->json([
                'message' => $output->message,
                'success' => $output->success,
            ]);
        } catch (BadRequestHttpException $e) {
            return $this->json([
                'detail' => $e->getMessage(),
                'title' => 'Bad Request',
            ], Response::HTTP_BAD_REQUEST);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'detail' => $e->getMessage(),
                'title' => 'Not Found',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
