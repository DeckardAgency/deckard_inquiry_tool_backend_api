<?php

namespace App\Command;

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
    name: 'app:create-admin',
    description: 'Creates the CMS admin user (ROLE_ADMIN) without a client association'
)]
class CreateAdminUserCommand extends Command
{
    private array $adminUser = [
        'email' => 'inquiry.deckard@deckard.hr',
        'firstName' => 'Deckard',
        'lastName' => 'Admin',
        'phoneNumber' => '+43 1 59955 0',
        'address' => 'Sonnenuhrgasse 4, 1060 Vienna, Austria',
        'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
        'password' => 'StarAdmin2025!',
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
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Delete this admin user before re-creating')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        $io->title('Create CMS Admin User');

        if ($dryRun) {
            $io->warning('DRY RUN MODE — no changes will be made.');
        }

        // Clear if requested
        if ($clear && !$dryRun) {
            $conn = $this->entityManager->getConnection();
            $conn->executeStatement(
                "DELETE FROM `user` WHERE email = ?",
                [$this->adminUser['email']]
            );
            $this->entityManager->clear();
            $io->comment(sprintf('Cleared existing user: %s', $this->adminUser['email']));
        }

        // Check if already exists
        $existing = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $this->adminUser['email']]);

        if ($existing) {
            $io->warning(sprintf('User %s already exists, skipping. Use --clear to re-create.', $this->adminUser['email']));
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->table(
                ['Field', 'Value'],
                [
                    ['Email', $this->adminUser['email']],
                    ['Name', $this->adminUser['firstName'] . ' ' . $this->adminUser['lastName']],
                    ['Roles', implode(', ', $this->adminUser['roles'])],
                    ['Password', $this->adminUser['password']],
                    ['Client', 'None (CMS Admin)'],
                ]
            );
            $io->success('Would create 1 admin user.');
            return Command::SUCCESS;
        }

        $user = new User();
        $user
            ->setEmail($this->adminUser['email'])
            ->setFirstName($this->adminUser['firstName'])
            ->setLastName($this->adminUser['lastName'])
            ->setPhoneNumber($this->adminUser['phoneNumber'])
            ->setAddress($this->adminUser['address'])
            ->setRoles($this->adminUser['roles']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $this->adminUser['password']);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Admin user created successfully.');
        $io->table(
            ['Field', 'Value'],
            [
                ['Email', $this->adminUser['email']],
                ['Name', $this->adminUser['firstName'] . ' ' . $this->adminUser['lastName']],
                ['Roles', implode(', ', $this->adminUser['roles'])],
                ['Password', $this->adminUser['password']],
                ['Client', 'None (CMS Admin)'],
            ]
        );

        return Command::SUCCESS;
    }
}
