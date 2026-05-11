<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-qa-users',
    description: 'Creates QA test users for all roles with dedicated test clients'
)]
class CreateQaTestUsersCommand extends Command
{
    private array $testClients = [
        [
            'code' => 'QA-ALPHA',
            'name' => 'QA Alpha Manufacturing GmbH',
            'email' => 'info@qa-alpha.test',
            'address' => 'Teststrasse 1, 1010 Vienna, Austria',
            'phoneNumber' => '+43 1 000 0001',
            'isActive' => true,
            'isArchived' => false,
            'requiresOrderApproval' => true,
            'requiresInquiryApproval' => true,
        ],
        [
            'code' => 'QA-BETA',
            'name' => 'QA Beta Recycling Ltd',
            'email' => 'info@qa-beta.test',
            'address' => 'Teststrasse 2, 1020 Vienna, Austria',
            'phoneNumber' => '+43 1 000 0002',
            'isActive' => true,
            'isArchived' => false,
            'requiresOrderApproval' => false,
            'requiresInquiryApproval' => false,
        ],
        [
            'code' => 'QA-GAMMA',
            'name' => 'QA Gamma Plastics S.r.l.',
            'email' => 'info@qa-gamma.test',
            'address' => 'Via Test 3, 20100 Milan, Italy',
            'phoneNumber' => '+39 02 000 0003',
            'isActive' => true,
            'isArchived' => false,
            'requiresOrderApproval' => false,
            'requiresInquiryApproval' => false,
        ],
        [
            'code' => 'QA-AGENT',
            'name' => 'QA Agent Trading GmbH',
            'email' => 'info@qa-agent.test',
            'address' => 'Teststrasse 4, 1040 Vienna, Austria',
            'phoneNumber' => '+43 1 000 0004',
            'isActive' => true,
            'isArchived' => false,
            'isClientAgent' => true,
            'requiresOrderApproval' => false,
            'requiresInquiryApproval' => false,
        ],
        [
            'code' => 'QA-DEACT',
            'name' => 'QA Deactivated Corp',
            'email' => 'info@qa-deact.test',
            'address' => 'Teststrasse 5, 1050 Vienna, Austria',
            'phoneNumber' => '+43 1 000 0005',
            'isActive' => true,
            'isArchived' => false,
            'requiresOrderApproval' => false,
            'requiresInquiryApproval' => false,
        ],
    ];

    private array $testUsers = [
        // Admin (no client)
        [
            'email' => 'qa-admin@deckard.test',
            'firstName' => 'QA',
            'lastName' => 'Admin',
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
            'password' => 'QaAdmin2026!',
            'clientCode' => null,
        ],
        // Regular user on QA-ALPHA
        [
            'email' => 'qa-user@alpha.test',
            'firstName' => 'QA',
            'lastName' => 'User',
            'roles' => ['ROLE_USER'],
            'password' => 'QaUser2026!',
            'clientCode' => 'QA-ALPHA',
        ],
        // Client Admin on QA-ALPHA
        [
            'email' => 'qa-clientadmin@alpha.test',
            'firstName' => 'QA',
            'lastName' => 'ClientAdmin',
            'roles' => ['ROLE_USER', 'ROLE_CLIENT_ADMIN'],
            'password' => 'QaClientAdmin2026!',
            'clientCode' => 'QA-ALPHA',
        ],
        // Second regular user on QA-ALPHA (for testing multi-user)
        [
            'email' => 'qa-user2@alpha.test',
            'firstName' => 'QA',
            'lastName' => 'User Two',
            'roles' => ['ROLE_USER'],
            'password' => 'QaUser2026!',
            'clientCode' => 'QA-ALPHA',
        ],
        // Regular user on QA-BETA
        [
            'email' => 'qa-user@beta.test',
            'firstName' => 'QA',
            'lastName' => 'BetaUser',
            'roles' => ['ROLE_USER'],
            'password' => 'QaUser2026!',
            'clientCode' => 'QA-BETA',
        ],
        // Client Agent user
        [
            'email' => 'qa-agent@agent.test',
            'firstName' => 'QA',
            'lastName' => 'Agent',
            'roles' => ['ROLE_USER', 'ROLE_USER_CLIENT_AGENT'],
            'password' => 'QaAgent2026!',
            'clientCode' => 'QA-AGENT',
        ],
        // Deactivated user (for testing deactivation)
        [
            'email' => 'qa-deactivated@deact.test',
            'firstName' => 'QA',
            'lastName' => 'Deactivated',
            'roles' => ['ROLE_USER'],
            'password' => 'QaDeact2026!',
            'clientCode' => 'QA-DEACT',
            'isActive' => false,
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Delete all QA test data before re-creating')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        $io->title('Create QA Test Users & Clients');

        if ($dryRun) {
            $io->warning('DRY RUN MODE — no changes will be made.');
        }

        // Clear existing QA data
        if ($clear && !$dryRun) {
            $this->clearQaData($io);
        }

        // Create clients
        $io->section('Creating test clients');
        $clientEntities = [];

        foreach ($this->testClients as $clientData) {
            $existing = $this->entityManager->getRepository(Client::class)
                ->findOneBy(['code' => $clientData['code']]);

            if ($existing) {
                $io->comment(sprintf('Client %s already exists, skipping.', $clientData['code']));
                $clientEntities[$clientData['code']] = $existing;
                continue;
            }

            if ($dryRun) {
                $io->writeln(sprintf('  Would create client: %s (%s)', $clientData['name'], $clientData['code']));
                continue;
            }

            $client = new Client();
            $client->setCode($clientData['code']);
            $client->setName($clientData['name']);
            $client->setEmail($clientData['email']);
            $client->setAddress($clientData['address']);
            $client->setPhoneNumber($clientData['phoneNumber']);
            $client->setIsActive($clientData['isActive']);
            $client->setIsArchived($clientData['isArchived']);

            if (isset($clientData['isClientAgent']) && $clientData['isClientAgent']) {
                $client->setIsClientAgent(true);
            }

            if (isset($clientData['requiresOrderApproval'])) {
                $client->setRequiresOrderApproval($clientData['requiresOrderApproval']);
            }

            if (isset($clientData['requiresInquiryApproval'])) {
                $client->setRequiresInquiryApproval($clientData['requiresInquiryApproval']);
            }

            $this->entityManager->persist($client);
            $clientEntities[$clientData['code']] = $client;
            $io->writeln(sprintf('  Created client: %s (%s)', $clientData['name'], $clientData['code']));
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        // Set up agent managed clients
        if (isset($clientEntities['QA-AGENT']) && !$dryRun) {
            $agentClient = $clientEntities['QA-AGENT'];
            foreach (['QA-ALPHA', 'QA-BETA', 'QA-GAMMA'] as $managedCode) {
                if (isset($clientEntities[$managedCode])) {
                    $agentClient->addManagedClient($clientEntities[$managedCode]);
                }
            }
            $this->entityManager->flush();
            $io->writeln('  Agent company manages: QA-ALPHA, QA-BETA, QA-GAMMA');
        }

        // Create users
        $io->section('Creating test users');
        $createdUsers = [];

        foreach ($this->testUsers as $userData) {
            $existing = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $userData['email']]);

            if ($existing) {
                $io->comment(sprintf('User %s already exists, skipping.', $userData['email']));
                continue;
            }

            if ($dryRun) {
                $io->writeln(sprintf('  Would create user: %s (%s)',
                    $userData['email'],
                    implode(', ', $userData['roles'])
                ));
                continue;
            }

            $user = new User();
            $user->setEmail($userData['email']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setRoles($userData['roles']);

            if (isset($userData['isActive']) && $userData['isActive'] === false) {
                $user->setIsActive(false);
            }

            if ($userData['clientCode'] && isset($clientEntities[$userData['clientCode']])) {
                $user->setClient($clientEntities[$userData['clientCode']]);
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $createdUsers[] = $userData;
            $io->writeln(sprintf('  Created user: %s [%s]',
                $userData['email'],
                implode(', ', $userData['roles'])
            ));
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        // Summary table
        $io->section('Test Accounts Summary');
        $rows = [];
        foreach ($this->testUsers as $u) {
            $rows[] = [
                $u['email'],
                $u['password'],
                implode(', ', $u['roles']),
                $u['clientCode'] ?? 'None',
                isset($u['isActive']) && $u['isActive'] === false ? 'INACTIVE' : 'Active',
            ];
        }

        $io->table(
            ['Email', 'Password', 'Roles', 'Client', 'Status'],
            $rows
        );

        if ($dryRun) {
            $io->success(sprintf('Would create %d clients and %d users.', count($this->testClients), count($this->testUsers)));
        } else {
            $io->success(sprintf('Created %d clients and %d users for QA testing.', count($this->testClients), count($createdUsers)));
        }

        return Command::SUCCESS;
    }

    private function clearQaData(SymfonyStyle $io): void
    {
        $conn = $this->entityManager->getConnection();

        // Delete QA users
        $emails = array_column($this->testUsers, 'email');
        $placeholders = implode(',', array_fill(0, count($emails), '?'));
        $conn->executeStatement("DELETE FROM `user` WHERE email IN ($placeholders)", $emails);
        $io->comment(sprintf('Cleared %d QA users', count($emails)));

        // Remove managed client relationships for agent
        $conn->executeStatement("DELETE FROM client_managed_clients WHERE client_source_id IN (SELECT id FROM client WHERE code = 'QA-AGENT') OR client_target_id IN (SELECT id FROM client WHERE code LIKE 'QA-%')");

        // Delete QA clients
        $codes = array_column($this->testClients, 'code');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $conn->executeStatement("DELETE FROM client WHERE code IN ($placeholders)", $codes);
        $io->comment(sprintf('Cleared %d QA clients', count($codes)));

        $this->entityManager->clear();
    }
}
