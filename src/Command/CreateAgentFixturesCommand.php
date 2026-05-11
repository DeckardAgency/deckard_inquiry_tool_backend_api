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
    name: 'app:create-agent-fixtures',
    description: 'Creates a client agent company with managed clients and an agent user for testing'
)]
class CreateAgentFixturesCommand extends Command
{
    private const AGENT_CLIENT_CODE = 'SPARTAN-AGENT';
    private const AGENT_USER_EMAIL = 'agent@spartan-parts.com';
    private const AGENT_PASSWORD = 'Agent2026!';

    /**
     * The agent company
     */
    private array $agentClient = [
        'name' => 'Spartan Parts Trading GmbH',
        'code' => self::AGENT_CLIENT_CODE,
        'description' => 'Parts trading agent representing multiple recycling and packaging companies across Europe.',
        'address' => 'Industriestraße 42, 4020 Linz, Austria',
        'phoneNumber' => '+43 732 123456',
        'email' => 'office@spartan-parts.com',
        'vatNumber' => 'ATU77889900',
    ];

    /**
     * Managed client companies
     */
    private array $managedClients = [
        [
            'name' => 'Akpol Recykling Sp.z.o.o.',
            'code' => 'AKPOL',
            'description' => 'Polish recycling company specializing in plastic waste processing.',
            'address' => 'ul. Przemysłowa 15, 62-300 Września, Poland',
            'phoneNumber' => '+48 61 436 0200',
            'email' => 'anes@company.com',
            'vatNumber' => 'PL7151954741',
            'users' => [
                ['email' => 'anes@akpol.pl', 'firstName' => 'Anes', 'lastName' => 'Kowalski', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Akpol2026!'],
            ],
        ],
        [
            'name' => 'Alaxe Italia Recycling S.p.A.',
            'code' => 'ALAXE',
            'description' => 'Italian recycling and packaging company based in Milan.',
            'address' => 'Via dell\'Industria 28, 20100 Milano, Italy',
            'phoneNumber' => '+39 02 1234567',
            'email' => 'emanuel@company.com',
            'vatNumber' => '1262330853',
            'users' => [
                ['email' => 'emanuel@alaxe.it', 'firstName' => 'Emanuel', 'lastName' => 'Rossi', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Alaxe2026!'],
            ],
        ],
        [
            'name' => 'Kugo Repara SL',
            'code' => 'KUGO',
            'description' => 'Spanish spare parts and machine repair company.',
            'address' => 'Calle de la Industria 7, 08019 Barcelona, Spain',
            'phoneNumber' => '+34 93 123 4567',
            'email' => 'eroghan@company.com',
            'vatNumber' => 'ESAA3931358',
            'users' => [
                ['email' => 'eroghan@kugo.es', 'firstName' => 'Eroghan', 'lastName' => 'García', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Kugo2026!'],
            ],
        ],
        [
            'name' => 'OMT Recycling Project S.L.',
            'code' => 'OMT',
            'description' => 'Spanish recycling project management company.',
            'address' => 'Avenida de Europa 12, 28023 Madrid, Spain',
            'phoneNumber' => '+34 91 765 4321',
            'email' => 'linda@company.com',
            'vatNumber' => 'ESAA4941362',
            'users' => [
                ['email' => 'linda@omt-recycling.es', 'firstName' => 'Linda', 'lastName' => 'Martínez', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'OMT2026!'],
            ],
        ],
        [
            'name' => 'PRT Rodomska',
            'code' => 'PRT',
            'description' => 'Polish packaging and recycling technology company.',
            'address' => 'ul. Fabryczna 3, 97-200 Tomaszów Mazowiecki, Poland',
            'phoneNumber' => '+48 44 724 5000',
            'email' => 'allen@company.com',
            'vatNumber' => 'ATU72944977',
            'users' => [
                ['email' => 'allen@prt.pl', 'firstName' => 'Allen', 'lastName' => 'Nowak', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'PRT2026!'],
            ],
        ],
        [
            'name' => 'Rymoplast n.v.',
            'code' => 'RYMO',
            'description' => 'Belgian plastics manufacturer and recycler.',
            'address' => 'Industriepark 22, 3500 Hasselt, Belgium',
            'phoneNumber' => '+32 11 234 567',
            'email' => 'dupton@company.com',
            'vatNumber' => 'BE043913824',
            'users' => [
                ['email' => 'dupton@rymoplast.be', 'firstName' => 'Dupton', 'lastName' => 'Van Berg', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Rymo2026!'],
            ],
        ],
        [
            'name' => 'EcoCycle Solutions Inc.',
            'code' => 'ECOCYCLE',
            'description' => 'North American recycling solutions provider.',
            'address' => '500 Innovation Drive, Austin, TX 78701, USA',
            'phoneNumber' => '+1 512 555 0100',
            'email' => 'marissa@company.com',
            'vatNumber' => 'PL7151954742',
            'users' => [
                ['email' => 'marissa@ecocycle.com', 'firstName' => 'Marissa', 'lastName' => 'Chen', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'EcoCycle2026!'],
            ],
        ],
        [
            'name' => 'GreenTech Waste Management Co.',
            'code' => 'GREENTECH',
            'description' => 'Environmental waste management and recycling technology company.',
            'address' => '88 Green Park Road, London SW1A 1AA, United Kingdom',
            'phoneNumber' => '+44 20 7123 4567',
            'email' => 'jason@company.com',
            'vatNumber' => '1262330854',
            'users' => [
                ['email' => 'jason@greentech-waste.co.uk', 'firstName' => 'Jason', 'lastName' => 'Palmer', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'GreenTech2026!'],
            ],
        ],
        [
            'name' => 'Reclaim Innovations Ltd.',
            'code' => 'RECLAIM',
            'description' => 'Innovative recycling technology and machinery company.',
            'address' => '12 Harbour View, D02 YW64 Dublin, Ireland',
            'phoneNumber' => '+353 1 234 5678',
            'email' => 'carmen@company.com',
            'vatNumber' => 'ESAA3931359',
            'users' => [
                ['email' => 'carmen@reclaim-innovations.ie', 'firstName' => 'Carmen', 'lastName' => 'Murphy', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Reclaim2026!'],
            ],
        ],
        [
            'name' => 'Sustainable Materials Group LLC',
            'code' => 'SMG',
            'description' => 'Sustainable packaging materials and recycling group.',
            'address' => '200 Sustainability Blvd, Portland, OR 97201, USA',
            'phoneNumber' => '+1 503 555 0200',
            'email' => 'thomas@company.com',
            'vatNumber' => 'ESAA4941363',
            'users' => [
                ['email' => 'thomas@smg-materials.com', 'firstName' => 'Thomas', 'lastName' => 'Reed', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'SMG2026!'],
            ],
        ],
        [
            'name' => 'TerraRenew Recycling Partners',
            'code' => 'TERRARENEW',
            'description' => 'European recycling partnership focused on circular economy solutions.',
            'address' => 'Recyclingweg 5, 1210 Wien, Austria',
            'phoneNumber' => '+43 1 987 6543',
            'email' => 'natalie@company.com',
            'vatNumber' => 'ATU72944978',
            'users' => [
                ['email' => 'natalie@terrarenew.at', 'firstName' => 'Natalie', 'lastName' => 'Gruber', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'Terra2026!'],
            ],
        ],
        [
            'name' => 'WasteWise Environmental Services',
            'code' => 'WASTEWISE',
            'description' => 'Environmental consulting and waste management services.',
            'address' => 'Milieulaan 30, 3584 Utrecht, Netherlands',
            'phoneNumber' => '+31 30 123 4567',
            'email' => 'paul@company.com',
            'vatNumber' => 'BE043913825',
            'users' => [
                ['email' => 'paul@wastewise.nl', 'firstName' => 'Paul', 'lastName' => 'de Vries', 'roles' => ['ROLE_USER', 'ROLE_CLIENT'], 'password' => 'WasteWise2026!'],
            ],
        ],
    ];

    /**
     * Agent users (belong to the agent company)
     */
    private array $agentUsers = [
        ['email' => self::AGENT_USER_EMAIL, 'firstName' => 'Marco', 'lastName' => 'Hoffmann', 'roles' => ['ROLE_USER', 'ROLE_USER_CLIENT_AGENT'], 'password' => self::AGENT_PASSWORD],
        ['email' => 'agent2@spartan-parts.com', 'firstName' => 'Elena', 'lastName' => 'Weber', 'roles' => ['ROLE_USER', 'ROLE_USER_CLIENT_AGENT'], 'password' => self::AGENT_PASSWORD],
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
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Delete agent fixtures before re-creating')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        $io->title('Create Agent Fixtures');

        if ($dryRun) {
            $io->warning('DRY RUN MODE — no changes will be made.');
        }

        // Clear if requested
        if ($clear && !$dryRun) {
            $this->clearFixtures($io);
        }

        if ($dryRun) {
            $this->previewFixtures($io);
            return Command::SUCCESS;
        }

        $this->entityManager->getConnection()->beginTransaction();

        try {
            // 1. Create managed client companies + their users
            $managedClientEntities = [];
            foreach ($this->managedClients as $clientData) {
                $existing = $this->entityManager->getRepository(Client::class)->findOneBy(['code' => $clientData['code']]);
                if ($existing) {
                    $io->comment(sprintf('Client "%s" already exists, reusing...', $clientData['code']));
                    $managedClientEntities[] = $existing;
                    continue;
                }

                $client = $this->createClient($clientData);
                $this->entityManager->persist($client);
                $managedClientEntities[] = $client;

                foreach ($clientData['users'] as $userData) {
                    $user = $this->createUser($userData, $client);
                    $this->entityManager->persist($user);
                }

                $io->comment(sprintf('  + Client: %s (%s) with %d user(s)', $clientData['name'], $clientData['code'], count($clientData['users'])));
            }

            // 2. Create the agent company
            $existingAgent = $this->entityManager->getRepository(Client::class)->findOneBy(['code' => self::AGENT_CLIENT_CODE]);
            if ($existingAgent) {
                $io->warning(sprintf('Agent client "%s" already exists, skipping creation...', self::AGENT_CLIENT_CODE));
                $agentClient = $existingAgent;
            } else {
                $agentClient = $this->createClient($this->agentClient);
                $agentClient->setIsClientAgent(true);
                $this->entityManager->persist($agentClient);
                $io->comment(sprintf('  + Agent Company: %s (%s) [isClientAgent=true]', $this->agentClient['name'], $this->agentClient['code']));
            }

            // 3. Assign managed clients to the agent
            foreach ($managedClientEntities as $managedClient) {
                if (!$agentClient->managesClient($managedClient)) {
                    $agentClient->addManagedClient($managedClient);
                }
            }
            $io->comment(sprintf('  → Assigned %d managed clients to agent', count($managedClientEntities)));

            // 4. Create agent users
            foreach ($this->agentUsers as $userData) {
                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userData['email']]);
                if ($existingUser) {
                    $io->comment(sprintf('  Agent user "%s" already exists, skipping...', $userData['email']));
                    continue;
                }
                $user = $this->createUser($userData, $agentClient);
                $this->entityManager->persist($user);
                $io->comment(sprintf('  + Agent User: %s (%s %s)', $userData['email'], $userData['firstName'], $userData['lastName']));
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            $io->success('Agent fixtures created successfully!');
            $io->newLine();

            // Summary table
            $io->section('Login Credentials');
            $io->table(
                ['Role', 'Email', 'Password', 'Company'],
                [
                    ['Agent', self::AGENT_USER_EMAIL, self::AGENT_PASSWORD, $this->agentClient['name']],
                    ['Agent', 'agent2@spartan-parts.com', self::AGENT_PASSWORD, $this->agentClient['name']],
                ]
            );

            $io->section('Managed Clients');
            $rows = [];
            foreach ($this->managedClients as $i => $c) {
                $rows[] = [($i + 1), $c['code'], $c['name'], $c['email'], $c['vatNumber']];
            }
            $io->table(['#', 'Code', 'Name', 'Email', 'VAT'], $rows);

            $io->note(sprintf(
                'Login to the Client App as "%s" with password "%s" to test agent ordering.',
                self::AGENT_USER_EMAIL,
                self::AGENT_PASSWORD
            ));

        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $io->error('Failed to create fixtures: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function createClient(array $data): Client
    {
        $client = new Client();
        $client
            ->setName($data['name'])
            ->setCode($data['code'])
            ->setDescription($data['description'] ?? null)
            ->setAddress($data['address'] ?? null)
            ->setPhoneNumber($data['phoneNumber'] ?? null)
            ->setEmail($data['email'] ?? null)
            ->setVatNumber($data['vatNumber'] ?? null)
            ->setIsActive(true)
            ->setIsArchived(false);

        return $client;
    }

    private function createUser(array $data, Client $client): User
    {
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setRoles($data['roles']);
        $user->setClient($client);
        $user->setIsActive(true);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        return $user;
    }

    private function clearFixtures(SymfonyStyle $io): void
    {
        $io->comment('Clearing agent fixtures...');
        $conn = $this->entityManager->getConnection();

        // Collect all codes
        $allCodes = [self::AGENT_CLIENT_CODE];
        foreach ($this->managedClients as $c) {
            $allCodes[] = $c['code'];
        }

        // Find client IDs
        $clientIds = $conn->fetchFirstColumn(
            'SELECT id FROM client WHERE code IN (?)',
            [$allCodes],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        if (!empty($clientIds)) {
            // Delete managed client relations
            $conn->executeStatement(
                'DELETE FROM client_agent_managed_clients WHERE agent_client_id IN (?) OR managed_client_id IN (?)',
                [$clientIds, $clientIds],
                [\Doctrine\DBAL\ArrayParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING]
            );
            $io->comment('  → managed client relations deleted');

            // Delete users
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
            $io->comment('  → client product prices deleted');

            // Delete clients
            $conn->executeStatement(
                'DELETE FROM client WHERE code IN (?)',
                [$allCodes],
                [\Doctrine\DBAL\ArrayParameterType::STRING]
            );
            $io->comment('  → clients deleted');
        }

        // Also delete agent users by email
        $agentEmails = array_column($this->agentUsers, 'email');
        $conn->executeStatement(
            'DELETE FROM `user` WHERE email IN (?)',
            [$agentEmails],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $this->entityManager->clear();
        $io->comment('Cleared.');
    }

    private function previewFixtures(SymfonyStyle $io): void
    {
        $io->section('Agent Company');
        $io->text(sprintf('  %s (%s) [isClientAgent=true]', $this->agentClient['name'], $this->agentClient['code']));

        $io->section('Agent Users');
        foreach ($this->agentUsers as $u) {
            $io->text(sprintf('  %s (%s %s) pw: %s', $u['email'], $u['firstName'], $u['lastName'], $u['password']));
        }

        $io->section(sprintf('Managed Clients (%d)', count($this->managedClients)));
        foreach ($this->managedClients as $c) {
            $io->text(sprintf('  %s (%s)', $c['name'], $c['code']));
            foreach ($c['users'] as $u) {
                $io->text(sprintf('    → %s (%s %s) pw: %s', $u['email'], $u['firstName'], $u['lastName'], $u['password']));
            }
        }
    }
}
