<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inquiry;
use App\Message\InquiryCreatedMessage;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

#[AsDecorator('api_platform.doctrine.orm.state.persist_processor', priority: 20)]
class InquiryNotificationProcessor implements ProcessorInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function __construct(
        private ProcessorInterface $decorated,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param Inquiry|object $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Call the decorated processor (persist the entity)
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        // Add detailed logging
        $this->logger->info('Processing entity in InquiryNotificationProcessor', [
            'entity_class' => get_class($data),
            'operation_name' => $operation->getName(),
            'context_keys' => array_keys($context),
        ]);

        // Check if this is an Inquiry
        if ($data instanceof Inquiry) {
            $this->logger->info('Processing Inquiry entity', [
                'inquiry_id' => $data->getId()->toRfc4122(),
                'inquiry_number' => $data->getInquiryNumber()
            ]);

            // For API Platform, check for POST operation
            $isPostOperation = false;

            // Check operation type directly from Operation object
            if ($operation->getMethod() === 'POST') {
                $isPostOperation = true;
            }

            // Fallback check using context if needed
            if (isset($context['collection_operation_name']) && $context['collection_operation_name'] === 'post') {
                $isPostOperation = true;
            }

            // Only process when an inquiry is submitted from a draft
            $isSubmitOperation = $operation->getName() === 'submit';

            $this->logger->info('Operation check result', [
                'is_post_operation' => $isPostOperation,
                'is_submit_operation' => $isSubmitOperation,
                'operation_method' => $operation->getMethod()
            ]);

            // Dispatch message for new inquiries or when draft is submitted
            // Skip notifications for inquiries pending approval (not yet visible to Deckard)
            if (($isPostOperation || $isSubmitOperation)
                && $data->getStatus() !== Inquiry::STATUS_PENDING_APPROVAL) {
                try {
                    $this->logger->info('Dispatching InquiryCreatedMessage', [
                        'inquiry_id' => $data->getId()->toRfc4122()
                    ]);

                    $this->messageBus->dispatch(new InquiryCreatedMessage($data->getId()));

                    $this->logger->info('Successfully dispatched InquiryCreatedMessage');
                } catch (\Exception $e) {
                    $this->logger->error('Failed to dispatch InquiryCreatedMessage', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        return $result;
    }
}
