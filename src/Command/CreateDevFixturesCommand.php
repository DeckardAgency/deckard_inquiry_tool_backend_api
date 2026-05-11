<?php

declare(strict_types=1);

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
    name: 'app:create-dev-fixtures',
    description: 'Seed baseline dev data: admin users, client companies, client users'
)]
class CreateDevFixturesCommand extends Command
{
    /**
     * Admin users for the admin_client app.
     *
     * @var list<array{email:string,firstName:string,lastName:string,roles:list<string>,password:string}>
     */
    private array $adminUsers = [
        [
            'email' => 'super@deckard.com',
            'firstName' => 'Super',
            'lastName' => 'Admin',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'password' => 'Super2026!',
        ],
        [
            'email' => 'admin@deckard.com',
            'firstName' => 'Admin',
            'lastName' => 'User',
            'roles' => ['ROLE_ADMIN'],
            'password' => 'Admin2026!',
        ],
    ];

    /**
     * Client companies + one login user per company for the inquiry_tool_client app.
     *
     * @var list<array{client:array<string,string>,user:array{email:string,firstName:string,lastName:string,password:string}}>
     */
    private array $clientCompanies = [
        [
            'client' => [
                'name' => 'Acme Industrial Parts GmbH',
                'code' => 'ACME',
                'description' => 'Heavy-industry spare parts distributor.',
                'address' => 'Industriestraße 12, 4020 Linz, Austria',
                'phoneNumber' => '+43 732 200 100',
                'email' => 'office@acme-parts.example',
                'vatNumber' => 'ATU11111111',
            ],
            'user' => [
                'email' => 'john@acme.com',
                'firstName' => 'John',
                'lastName' => 'Schneider',
                'password' => 'Acme2026!',
            ],
        ],
        [
            'client' => [
                'name' => 'Globex Manufacturing Ltd.',
                'code' => 'GLOBEX',
                'description' => 'Mid-volume contract manufacturer.',
                'address' => '42 Springfield Way, Manchester, United Kingdom',
                'phoneNumber' => '+44 161 555 1000',
                'email' => 'contact@globex-mfg.example',
                'vatNumber' => 'GB222222222',
            ],
            'user' => [
                'email' => 'sara@globex.com',
                'firstName' => 'Sara',
                'lastName' => 'Bennett',
                'password' => 'Globex2026!',
            ],
        ],
        [
            'client' => [
                'name' => 'Initech Logistics s.r.o.',
                'code' => 'INITECH',
                'description' => 'Fleet maintenance and parts logistics.',
                'address' => 'Smetanova 7, 110 00 Praha, Czechia',
                'phoneNumber' => '+420 224 100 200',
                'email' => 'parts@initech-logistics.example',
                'vatNumber' => 'CZ33333333',
            ],
            'user' => [
                'email' => 'mike@initech.com',
                'firstName' => 'Mike',
                'lastName' => 'Novák',
                'password' => 'Initech2026!',
            ],
        ],
        [
            'client' => [
                'name' => 'Soylent Mechanics S.p.A.',
                'code' => 'SOYLENT',
                'description' => 'Industrial machinery service workshop.',
                'address' => 'Via Roma 88, 20121 Milano, Italy',
                'phoneNumber' => '+39 02 555 4000',
                'email' => 'info@soylent-mechanics.example',
                'vatNumber' => 'IT44444444444',
            ],
            'user' => [
                'email' => 'lisa@soylent.com',
                'firstName' => 'Lisa',
                'lastName' => 'Conti',
                'password' => 'Soylent2026!',
            ],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Replace existing fixtures (delete users/clients with matching emails/codes)')
            ->setHelp(<<<'HELP'
Seed baseline dev data needed to log into both Angular apps.

    php %command.full_name%
    php %command.full_name% --force   # remove existing seed rows first

This creates:
  - 2 admin accounts for the admin_client (super_admin + admin)
  - 4 client companies with one user each for the inquiry_tool client
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $io->title('Dev fixtures: admin + client companies + users');

        if ($force) {
            $io->section('Removing previous fixtures');
            $this->removeExisting();
            $io->writeln('  - cleared previous admin users and seeded client companies');
        }

        $io->section('Admin users (admin_client)');
        foreach ($this->adminUsers as $spec) {
            $this->ensureUser($spec, client: null, io: $io);
        }

        $io->section('Client companies (inquiry_tool_client)');
        foreach ($this->clientCompanies as $entry) {
            $client = $this->ensureClient($entry['client'], $io);
            $userSpec = $entry['user'] + ['roles' => ['ROLE_USER', 'ROLE_CLIENT']];
            $this->ensureUser($userSpec, client: $client, io: $io);
        }

        $this->em->flush();

        $io->section('Login credentials');
        $rows = [];
        foreach ($this->adminUsers as $u) {
            $rows[] = ['admin_client', $u['email'], $u['password'], implode(',', $u['roles'])];
        }
        foreach ($this->clientCompanies as $e) {
            $rows[] = ['inquiry_tool_client', $e['user']['email'], $e['user']['password'], 'ROLE_USER,ROLE_CLIENT'];
        }
        $io->table(['App', 'Email', 'Password', 'Roles'], $rows);

        $io->success('Dev fixtures applied. Login at /api/login_check (POST {username, password}).');

        return Command::SUCCESS;
    }

    /**
     * @param array{email:string,firstName:string,lastName:string,roles:list<string>,password:string} $spec
     */
    private function ensureUser(array $spec, ?Client $client, SymfonyStyle $io): User
    {
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $spec['email']]);
        $user = $existing ?? new User();
        $user->setEmail($spec['email']);
        $user->setFirstName($spec['firstName']);
        $user->setLastName($spec['lastName']);
        $user->setRoles($spec['roles']);
        $user->setIsActive(true);
        if ($client !== null) {
            $user->setClient($client);
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $spec['password']));

        $this->em->persist($user);

        $io->writeln(sprintf(
            '  %s %s <comment>(%s)</comment> [%s]',
            $existing ? '↻' : '+',
            $spec['email'],
            $spec['password'],
            implode(',', $spec['roles'])
        ));

        return $user;
    }

    /**
     * @param array<string,string> $spec
     */
    private function ensureClient(array $spec, SymfonyStyle $io): Client
    {
        $existing = $this->em->getRepository(Client::class)->findOneBy(['code' => $spec['code']]);
        $client = $existing ?? new Client();
        $client->setName($spec['name']);
        $client->setCode($spec['code']);
        $client->setDescription($spec['description'] ?? null);
        $client->setAddress($spec['address'] ?? null);
        $client->setPhoneNumber($spec['phoneNumber'] ?? null);
        $client->setEmail($spec['email'] ?? null);
        $client->setVatNumber($spec['vatNumber'] ?? null);

        $this->em->persist($client);

        $io->writeln(sprintf('  %s %s <comment>(%s)</comment>', $existing ? '↻' : '+', $spec['code'], $spec['name']));

        return $client;
    }

    private function removeExisting(): void
    {
        $emails = array_merge(
            array_column($this->adminUsers, 'email'),
            array_map(static fn ($e) => $e['user']['email'], $this->clientCompanies),
        );
        $codes = array_map(static fn ($e) => $e['client']['code'], $this->clientCompanies);

        $userRepo = $this->em->getRepository(User::class);
        foreach ($userRepo->findBy(['email' => $emails]) as $u) {
            $this->em->remove($u);
        }
        $this->em->flush();

        $clientRepo = $this->em->getRepository(Client::class);
        foreach ($clientRepo->findBy(['code' => $codes]) as $c) {
            $this->em->remove($c);
        }
        $this->em->flush();
    }
}
