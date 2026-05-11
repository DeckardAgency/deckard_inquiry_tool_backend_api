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
    name: 'app:create-clients',
    description: 'Creates clients (Grafit, Deckard, Deckard) with their users'
)]
class CreateClientsCommand extends Command
{
    private array $clients = [
        [
            'name' => 'Grafit d.o.o.',
            'code' => 'GRAFIT',
            'description' => 'Manufacturer of polypropylene woven bags, FIBC big bags, and flexible packaging solutions based in Bosnia and Herzegovina.',
            'address' => 'Bukinje bb, 75000 Tuzla, Bosnia and Herzegovina',
            'phoneNumber' => '+387 35 319 100',
            'email' => 'info@grafit.net',
            'vatNumber' => 'BA4209385280003',
            'users' => [
                ['email' => 'kp@grafit.net', 'firstName' => 'Anes', 'lastName' => 'Kapetanovic', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Grafit2025!'],
                ['email' => 'cen@grafit.net', 'firstName' => 'Iris', 'lastName' => 'Cenga', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Grafit2025!'],
                ['email' => 'pas@grafit.net', 'firstName' => 'Alexandr', 'lastName' => 'Pasechnik', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Grafit2025!'],
                ['email' => 'ise@grafit.net', 'firstName' => 'Erduan', 'lastName' => 'Ismani', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Grafit2025!'],
                ['email' => 'admin@grafit.net', 'firstName' => 'Grafit', 'lastName' => 'Admin', 'roles' => ['ROLE_USER', 'ROLE_CLIENT_ADMIN'], 'password' => 'GrafitAdmin2025!'],
            ],
        ],
        [
            'name' => 'Deckard d.o.o.',
            'code' => 'DECKARD',
            'description' => 'Digital agency and software development company specializing in web applications, mobile apps, and digital transformation based in Zagreb, Croatia.',
            'address' => 'Ulica Milana Amruša 10, 10000 Zagreb, Croatia',
            'phoneNumber' => '+385 1 6072 586',
            'email' => 'info@deckard.hr',
            'vatNumber' => 'HR12345678901',
            'users' => [
                ['email' => 'jozic@deckard.hr', 'firstName' => 'Ivan', 'lastName' => 'Jozic', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Deckard2025!'],
                ['email' => 'nikola.grdanjski@deckard.hr', 'firstName' => 'Nikola', 'lastName' => 'Grdanjski', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Deckard2025!'],
                ['email' => 'ales@mrbowtie.hr', 'firstName' => 'Aleš', 'lastName' => 'Horvatek', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Deckard2025!'],
                ['email' => 'admin@deckard.hr', 'firstName' => 'Deckard', 'lastName' => 'Admin', 'roles' => ['ROLE_USER', 'ROLE_CLIENT_ADMIN'], 'password' => 'DeckardAdmin2025!'],
            ],
        ],
        [
            'name' => 'Deckard & Co Gesellschaft m.b.H.',
            'code' => 'DECKARD',
            'description' => 'World market leader in machinery and process technology for woven plastic packaging, PET recycling and PET sheet extrusion based in Vienna, Austria.',
            'address' => 'Sonnenuhrgasse 4, 1060 Vienna, Austria',
            'phoneNumber' => '+43 1 59955 0',
            'email' => 'office@deckard.com',
            'vatNumber' => 'ATU14aborw8',
            'users' => [
                ['email' => 'service.ert@deckard.com', 'firstName' => 'Martin', 'lastName' => 'Ertl', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Deckard2025!'],
                ['email' => 'service.drf@deckard.com', 'firstName' => 'Lucas', 'lastName' => 'Dorfwirth', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Deckard2025!'],
                ['email' => 'service.ksm@deckard.com', 'firstName' => 'Stefan', 'lastName' => 'Krajnik', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Deckard2025!'],
                ['email' => 'admin@deckard.com', 'firstName' => 'Deckard', 'lastName' => 'Admin', 'roles' => ['ROLE_USER', 'ROLE_CLIENT_ADMIN'], 'password' => 'DeckardAdmin2025!'],
            ],
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
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Delete these clients and their users before re-creating')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        $io->title('Create Clients & Users');

        if ($dryRun) {
            $io->warning('DRY RUN MODE — no changes will be made.');
        }

        $clientCodes = array_column($this->clients, 'code');

        // Clear if requested
        if ($clear && !$dryRun) {
            $io->comment('Clearing existing clients and their users...');
            $conn = $this->entityManager->getConnection();

            // Find client UUIDs
            $clientIds = $conn->fetchFirstColumn(
                'SELECT id FROM client WHERE code IN (?)',
                [$clientCodes],
                [\Doctrine\DBAL\ArrayParameterType::STRING]
            );

            if (!empty($clientIds)) {
                // Delete users belonging to these clients
                $conn->executeStatement(
                    'DELETE FROM `user` WHERE client_id IN (?)',
                    [$clientIds],
                    [\Doctrine\DBAL\ArrayParameterType::STRING]
                );
                $io->comment('  → users deleted');

                // Delete client product prices
                $conn->executeStatement(
                    'DELETE FROM client_product_price WHERE client_id IN (?)',
                    [$clientIds],
                    [\Doctrine\DBAL\ArrayParameterType::STRING]
                );
                $io->comment('  → client_product_price rows deleted');

                // Delete installed base relations
                $conn->executeStatement(
                    'DELETE FROM client_machine_installed_base WHERE client_id IN (?)',
                    [$clientIds],
                    [\Doctrine\DBAL\ArrayParameterType::STRING]
                );
                $io->comment('  → client_machine_installed_base rows deleted');

                // Delete clients
                $conn->executeStatement(
                    'DELETE FROM client WHERE code IN (?)',
                    [$clientCodes],
                    [\Doctrine\DBAL\ArrayParameterType::STRING]
                );
                $io->comment('  → clients deleted');
            }

            // Also delete users by email that may exist without a client (e.g. admin users from old test data)
            $allEmails = [];
            foreach ($this->clients as $clientData) {
                foreach ($clientData['users'] as $userData) {
                    $allEmails[] = $userData['email'];
                }
            }
            $conn->executeStatement(
                'DELETE FROM `user` WHERE email IN (?)',
                [$allEmails],
                [\Doctrine\DBAL\ArrayParameterType::STRING]
            );

            $this->entityManager->clear();
            $io->comment('Cleared.');
        }

        if (!$dryRun) {
            $this->entityManager->getConnection()->beginTransaction();
        }

        try {
            $credentialsTable = []; // for summary display
            $totalClients = 0;
            $totalUsers = 0;

            foreach ($this->clients as $clientData) {
                // Check if client already exists
                $existingClient = $this->entityManager->getRepository(Client::class)
                    ->findOneBy(['code' => $clientData['code']]);

                if ($existingClient) {
                    $io->warning(sprintf('Client "%s" (code: %s) already exists, skipping...', $clientData['name'], $clientData['code']));
                    continue;
                }

                if ($dryRun) {
                    $io->section(sprintf('[DRY] Client: %s (%s)', $clientData['name'], $clientData['code']));
                    foreach ($clientData['users'] as $userData) {
                        $roles = implode(', ', $userData['roles']);
                        $io->text(sprintf('  → %s (%s %s) [%s] pw: %s',
                            $userData['email'], $userData['firstName'], $userData['lastName'], $roles, $userData['password']
                        ));
                    }
                    $totalClients++;
                    $totalUsers += count($clientData['users']);
                    continue;
                }

                $client = new Client();
                $client
                    ->setName($clientData['name'])
                    ->setCode($clientData['code'])
                    ->setDescription($clientData['description'])
                    ->setAddress($clientData['address'])
                    ->setPhoneNumber($clientData['phoneNumber'])
                    ->setEmail($clientData['email'])
                    ->setVatNumber($clientData['vatNumber']);

                $this->entityManager->persist($client);
                $totalClients++;

                $io->section(sprintf('Client: %s (%s)', $clientData['name'], $clientData['code']));

                foreach ($clientData['users'] as $userData) {
                    // Check if user already exists
                    $existingUser = $this->entityManager->getRepository(User::class)
                        ->findOneBy(['email' => $userData['email']]);

                    if ($existingUser) {
                        $io->warning(sprintf('  User %s already exists, skipping...', $userData['email']));
                        continue;
                    }

                    $user = new User();
                    $user
                        ->setEmail($userData['email'])
                        ->setFirstName($userData['firstName'])
                        ->setLastName($userData['lastName'])
                        ->setRoles($userData['roles'])
                        ->setClient($client);

                    $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
                    $user->setPassword($hashedPassword);

                    $this->entityManager->persist($user);
                    $totalUsers++;

                    $roles = implode(', ', $userData['roles']);
                    $io->text(sprintf('  + %s (%s %s) [%s]',
                        $userData['email'], $userData['firstName'], $userData['lastName'], $roles
                    ));

                    $credentialsTable[] = [
                        $clientData['code'],
                        $userData['email'],
                        $userData['password'],
                        $roles,
                    ];
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();
            }

            $io->newLine();
            $io->success(sprintf(
                '%s %d clients with %d users',
                $dryRun ? 'Would create' : 'Successfully created',
                $totalClients,
                $totalUsers
            ));

            if (!empty($credentialsTable)) {
                $io->section('Credentials Summary');
                $io->table(['Client', 'Email', 'Password', 'Roles'], $credentialsTable);
            }

        } catch (\Exception $e) {
            if (!$dryRun) {
                $this->entityManager->getConnection()->rollBack();
            }
            $io->error(['Failed!', $e->getMessage(), $e->getTraceAsString()]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
