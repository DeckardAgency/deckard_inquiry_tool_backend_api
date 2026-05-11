<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[AsCommand(
    name: 'app:send-test-mail',
    description: 'Send a test email using Symfony Mailer and Twig template',
)]
class SendTestMailCommand extends Command
{
    private MailerInterface $mailer;
    private string $mailerDsn;

    public function __construct(MailerInterface $mailer, string $mailerDsn)
    {
        parent::__construct();
        $this->mailer = $mailer;
        $this->mailerDsn = $mailerDsn;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('recipient', InputArgument::REQUIRED, 'Email recipient')
            ->addOption('subject', 's', InputOption::VALUE_OPTIONAL, 'Email subject', 'Test Email from Symfony')
            ->addOption('text', 't', InputOption::VALUE_OPTIONAL, 'Plain text content', null)
            ->addOption('template', null, InputOption::VALUE_OPTIONAL, 'Twig template name', 'emails/test.html.twig')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $recipient = $input->getArgument('recipient');
        $subject = $input->getOption('subject');
        $text = $input->getOption('text');
        $template = $input->getOption('template');

        // Show MAILER_DSN info (mask credentials)
        $dsnParsed = parse_url($this->mailerDsn);
        $dsnHost = $dsnParsed['host'] ?? 'unknown';
        $dsnPort = $dsnParsed['port'] ?? 'default';
        $dsnScheme = $dsnParsed['scheme'] ?? 'smtp';
        $io->section('Mailer Configuration');
        $io->listing([
            sprintf('DSN: %s://*****@%s:%s', $dsnScheme, $dsnHost, $dsnPort),
            sprintf('From: noreply@inquiry.deckard.com'),
            sprintf('To: %s', $recipient),
        ]);

        if (str_contains($dsnHost, 'mailtrap')) {
            $io->warning('MAILER_DSN points to Mailtrap (test inbox). Emails will NOT arrive at real addresses. Check your Mailtrap inbox or change MAILER_DSN in .env.local');
        }

        try {
            // Create a TemplatedEmail for Twig template
            $email = (new TemplatedEmail())
                ->from(new Address('noreply@inquiry.deckard.com', 'Test Mailer'))
                ->to($recipient)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context([
                    'recipient_email' => $recipient,
                    'subject' => $subject,
                    'sent_date' => new \DateTime(),
                    'additional_content' => 'This is a test email sent via console command.'
                ]);

            // Add text version if provided
            if ($text) {
                $email->text($text);
            }

            $io->text('Sending email...');

            // Send the email
            $this->mailer->send($email);

            $io->success(sprintf('Email sent to %s via %s:%s', $recipient, $dsnHost, $dsnPort));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                'Failed to send email',
                sprintf('SMTP Host: %s:%s', $dsnHost, $dsnPort),
                sprintf('Error: %s', $e->getMessage()),
            ]);
            return Command::FAILURE;
        }
    }
}
