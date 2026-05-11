<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Message\OrderCreatedMessage;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;

#[AsDecorator('api_platform.doctrine.orm.state.persist_processor', priority: 30)]
class OrderNotificationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param Order|object $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Call the decorated processor (persist the entity)
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        // Add detailed logging
        $this->logger->info('Processing entity in OrderNotificationProcessor', [
            'entity_class' => get_class($data),
            'operation_name' => $operation->getName(),
            'context_keys' => array_keys($context),
        ]);

        // Check if this is an Order
        if ($data instanceof Order) {
            $this->logger->info('Processing Order entity', [
                'order_id' => $data->getId()->toRfc4122(),
                'order_number' => $data->getOrderNumber()
            ]);

            // For API Platform 4.0, check for POST operation differently
            $isPostOperation = false;

            // Check operation type directly from Operation object
            if ($operation->getMethod() === 'POST') {
                $isPostOperation = true;
            }

            // Fallback check using context if needed
            if (isset($context['collection_operation_name']) && $context['collection_operation_name'] === 'post') {
                $isPostOperation = true;
            }

            $this->logger->info('Operation check result', [
                'is_post_operation' => $isPostOperation,
                'operation_method' => $operation->getMethod()
            ]);

            if ($isPostOperation) {
                try {
                    $this->logger->info('Dispatching OrderCreatedMessage', [
                        'order_id' => $data->getId()->toRfc4122()
                    ]);

                    $this->messageBus->dispatch(new OrderCreatedMessage($data->getId()));

                    $this->logger->info('Successfully dispatched OrderCreatedMessage');
                } catch (\Exception $e) {
                    $this->logger->error('Failed to dispatch OrderCreatedMessage', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        return $result;
    }
}
