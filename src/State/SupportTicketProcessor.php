<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SupportTicket;
use App\Entity\MediaItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeTypes;

class SupportTicketProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Security $security,
        private RequestStack $requestStack,
        private SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private string $uploadDirectory,
        #[Autowire('%env(ADMIN_EMAIL)%')]
        private string $adminEmail
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Set the current user if creating a new ticket
        if ($operation instanceof \ApiPlatform\Metadata\Post) {
            $request = $this->requestStack->getCurrentRequest();

            if (!$data instanceof SupportTicket) {
                // Handle multipart/form-data - create entity from request data
                $data = new SupportTicket();

                if ($request instanceof Request) {
                    $data->setSubject($request->request->get('subject'));
                    $data->setMessage($request->request->get('message'));
                    $data->setUrgency($request->request->get('urgency', 'medium'));

                    if ($request->request->has('orderId')) {
                        $data->setOrderId($request->request->get('orderId'));
                    }

                    if ($request->request->has('machine')) {
                        $data->setMachine($request->request->get('machine'));
                    }
                }
            }

            $user = $this->security->getUser();
            if ($user) {
                $data->setUser($user);
            }

            // Handle file upload using same approach as CreateMediaItemAction
            if ($request instanceof Request && $request->files->has('attachment')) {
                $file = $request->files->get('attachment');

                if ($file) {
                    try {
                        $mediaItem = $this->handleFileUpload($file);

                        // Persist the MediaItem
                        $this->entityManager->persist($mediaItem);

                        // Set the MediaItem to the support ticket
                        $data->setAttachment($mediaItem);
                    } catch (\Exception $e) {
                        // Handle file upload error
                        // You could log this error
                    }
                }
            }
        }

        // Persist the entity
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        // Send email notification to admin for new tickets
        if ($operation instanceof \ApiPlatform\Metadata\Post) {
            $this->sendAdminNotification($data);
        }

        return $data;
    }

    private function handleFileUpload($uploadedFile): MediaItem
    {
        $filesystem = new Filesystem();

        // Ensure upload directory exists
        if (!$filesystem->exists($this->uploadDirectory)) {
            $filesystem->mkdir($this->uploadDirectory);
        }

        $mediaItem = new MediaItem();

        // Get mime type before moving the file
        $mimeTypes = new MimeTypes();
        $mimeType = $mimeTypes->guessMimeType($uploadedFile->getPathname());

        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $uploadedFile->guessExtension() ?? 'bin';
        $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid(), $extension);

        // Move file to permanent location
        $uploadedFile->move(
            $this->uploadDirectory,
            $newFilename
        );

        $mediaItem->setFilename($newFilename);
        $mediaItem->setMimeType($mimeType);
        $mediaItem->setFilePath('/uploads/' . $newFilename);

        return $mediaItem;
    }

    private function sendAdminNotification(SupportTicket $ticket): void
    {
        $user = $ticket->getUser();
        $userEmail = $user ? $user->getEmail() : 'Unknown';
        $userName = $user ? $user->getFullName() : 'Unknown User';

        $urgencyLabel = match($ticket->getUrgency()) {
            'high' => '🔴 HIGH',
            'medium' => '🟡 MEDIUM',
            'low' => '🟢 LOW',
            default => 'MEDIUM'
        };

        $attachment = $ticket->getAttachment();
        $attachmentInfo = 'No';
        if ($attachment) {
            $attachmentInfo = sprintf(
                'Yes (%s - %s)',
                $attachment->getFilename(),
                $attachment->getMimeType()
            );
        }

        $emailBody = sprintf(
            "A new support ticket has been created:\n\n" .
            "Ticket ID: %s\n" .
            "Subject: %s\n" .
            "Urgency: %s\n" .
            "Status: %s\n\n" .
            "Submitted by: %s (%s)\n\n" .
            "Message:\n%s\n\n" .
            "---\n" .
            "Order/Inquiry ID: %s\n" .
            "Machine/Product: %s\n" .
            "Attachment: %s\n\n" .
            "Created at: %s",
            $ticket->getId(),
            $ticket->getSubject(),
            $urgencyLabel,
            strtoupper($ticket->getStatus()),
            $userName,
            $userEmail,
            $ticket->getMessage(),
            $ticket->getOrderId() ?: 'N/A',
            $ticket->getMachine() ?: 'N/A',
            $attachmentInfo,
            $ticket->getCreatedAt()->format('Y-m-d H:i:s')
        );

        $email = (new Email())
            ->from('noreply@deckard.com')
            ->to($this->adminEmail)
            ->subject(sprintf('[Support Ticket] %s - %s', $urgencyLabel, $ticket->getSubject()))
            ->text($emailBody);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log email error but don't fail the request
            // You could add proper logging here
        }
    }
}
