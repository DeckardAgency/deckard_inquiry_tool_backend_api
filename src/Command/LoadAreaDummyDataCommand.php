<?php

namespace App\Command;

use App\Entity\Area;
use App\Entity\AreaCriteria;
use App\Entity\AreaManager;
use App\Entity\AreaManagerAvailability;
use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-area-dummy-data',
    description: 'Load dummy data for Area Management System testing',
)]
class LoadAreaDummyDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Loading Area Management System Dummy Data');

        // Get first client
        $client = $this->entityManager->getRepository(Client::class)->findOneBy(['isActive' => true]);

        if (!$client) {
            $io->error('No active client found. Please create a client first.');
            return Command::FAILURE;
        }

        $io->info("Using client: {$client->getName()} ({$client->getCode()})");

        // Get active users to use as managers
        $users = $this->entityManager->getRepository(User::class)->findBy(['isActive' => true], null, 10);

        if (count($users) < 2) {
            $io->error('Need at least 2 active users to create area managers. Please create more users first.');
            return Command::FAILURE;
        }

        $io->info("Found " . count($users) . " active users to use as area managers");

        // Create Areas
        $io->section('Creating Areas');

        $northAmerica = new Area();
        $northAmerica->setName('North America');
        $northAmerica->setCode('NA');
        $northAmerica->setDescription('North American region covering USA, Canada, and Mexico');
        $northAmerica->setClient($client);
        $northAmerica->setPriority(10);
        $northAmerica->setIsActive(true);
        $this->entityManager->persist($northAmerica);
        $io->success('Created area: North America (NA)');

        $europe = new Area();
        $europe->setName('Europe');
        $europe->setCode('EU');
        $europe->setDescription('European region');
        $europe->setClient($client);
        $europe->setPriority(9);
        $europe->setIsActive(true);
        $this->entityManager->persist($europe);
        $io->success('Created area: Europe (EU)');

        $asiaPacific = new Area();
        $asiaPacific->setName('Asia Pacific');
        $asiaPacific->setCode('APAC');
        $asiaPacific->setDescription('Asia Pacific region including China, Japan, Australia');
        $asiaPacific->setClient($client);
        $asiaPacific->setPriority(8);
        $asiaPacific->setIsActive(true);
        $this->entityManager->persist($asiaPacific);
        $io->success('Created area: Asia Pacific (APAC)');

        // Create sub-areas for Europe
        $westernEurope = new Area();
        $westernEurope->setName('Western Europe');
        $westernEurope->setCode('EU-WEST');
        $westernEurope->setDescription('Western European countries');
        $westernEurope->setClient($client);
        $westernEurope->setParentArea($europe);
        $westernEurope->setPriority(5);
        $westernEurope->setIsActive(true);
        $this->entityManager->persist($westernEurope);
        $io->success('Created sub-area: Western Europe (EU-WEST)');

        $easternEurope = new Area();
        $easternEurope->setName('Eastern Europe');
        $easternEurope->setCode('EU-EAST');
        $easternEurope->setDescription('Eastern European countries');
        $easternEurope->setClient($client);
        $easternEurope->setParentArea($europe);
        $easternEurope->setPriority(4);
        $easternEurope->setIsActive(true);
        $this->entityManager->persist($easternEurope);
        $io->success('Created sub-area: Eastern Europe (EU-EAST)');

        $this->entityManager->flush();

        // Create Area Managers
        $io->section('Creating Area Managers');

        $manager1 = new AreaManager();
        $manager1->setArea($northAmerica);
        $manager1->setManager($users[0]);
        $manager1->setIsPrimary(true);
        $manager1->setIsActive(true);
        $manager1->setMaxCapacity(50);
        $manager1->setSpecializations(['machinery', 'spare_parts']);
        $this->entityManager->persist($manager1);
        $io->success("Assigned {$users[0]->getFullName()} as primary manager for North America (capacity: 50)");

        $manager2 = new AreaManager();
        $manager2->setArea($northAmerica);
        $manager2->setManager($users[1]);
        $manager2->setIsPrimary(false);
        $manager2->setIsActive(true);
        $manager2->setMaxCapacity(30);
        $manager2->setSpecializations(['technical_support']);
        $this->entityManager->persist($manager2);
        $io->success("Assigned {$users[1]->getFullName()} as secondary manager for North America (capacity: 30)");

        if (count($users) > 2) {
            $manager3 = new AreaManager();
            $manager3->setArea($europe);
            $manager3->setManager($users[2]);
            $manager3->setIsPrimary(true);
            $manager3->setIsActive(true);
            $manager3->setMaxCapacity(40);
            $manager3->setSpecializations(['machinery', 'recycling']);
            $this->entityManager->persist($manager3);
            $io->success("Assigned {$users[2]->getFullName()} as primary manager for Europe (capacity: 40)");
        }

        if (count($users) > 3) {
            $manager4 = new AreaManager();
            $manager4->setArea($asiaPacific);
            $manager4->setManager($users[3]);
            $manager4->setIsPrimary(true);
            $manager4->setIsActive(true);
            $manager4->setMaxCapacity(35);
            $manager4->setSpecializations(['machinery']);
            $this->entityManager->persist($manager4);
            $io->success("Assigned {$users[3]->getFullName()} as primary manager for Asia Pacific (capacity: 35)");
        }

        if (count($users) > 4) {
            $manager5 = new AreaManager();
            $manager5->setArea($westernEurope);
            $manager5->setManager($users[4]);
            $manager5->setIsPrimary(true);
            $manager5->setIsActive(true);
            $manager5->setMaxCapacity(25);
            $this->entityManager->persist($manager5);
            $io->success("Assigned {$users[4]->getFullName()} as manager for Western Europe (capacity: 25)");
        }

        $this->entityManager->flush();

        // Create Area Criteria for auto-assignment
        $io->section('Creating Area Criteria for Auto-Assignment');

        $criteria1 = new AreaCriteria();
        $criteria1->setArea($northAmerica);
        $criteria1->setName('USA Orders');
        $criteria1->setDescription('Auto-assign USA orders to North America');
        $criteria1->setFieldType(AreaCriteria::FIELD_TYPE_COUNTRY);
        $criteria1->setOperator(AreaCriteria::OPERATOR_IN);
        $criteria1->setValue(['US', 'USA', 'United States']);
        $criteria1->setPriority(10);
        $criteria1->setIsActive(true);
        $this->entityManager->persist($criteria1);
        $io->success('Created criteria: USA Orders → North America');

        $criteria2 = new AreaCriteria();
        $criteria2->setArea($northAmerica);
        $criteria2->setName('Canada Orders');
        $criteria2->setDescription('Auto-assign Canada orders to North America');
        $criteria2->setFieldType(AreaCriteria::FIELD_TYPE_COUNTRY);
        $criteria2->setOperator(AreaCriteria::OPERATOR_EQUALS);
        $criteria2->setValue(['CA', 'Canada']);
        $criteria2->setPriority(10);
        $criteria2->setIsActive(true);
        $this->entityManager->persist($criteria2);
        $io->success('Created criteria: Canada Orders → North America');

        $criteria3 = new AreaCriteria();
        $criteria3->setArea($europe);
        $criteria3->setName('EU Countries');
        $criteria3->setDescription('Auto-assign European orders');
        $criteria3->setFieldType(AreaCriteria::FIELD_TYPE_COUNTRY);
        $criteria3->setOperator(AreaCriteria::OPERATOR_IN);
        $criteria3->setValue(['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'CH', 'UK', 'GB']);
        $criteria3->setPriority(9);
        $criteria3->setIsActive(true);
        $this->entityManager->persist($criteria3);
        $io->success('Created criteria: EU Countries → Europe');

        $criteria4 = new AreaCriteria();
        $criteria4->setArea($asiaPacific);
        $criteria4->setName('APAC Countries');
        $criteria4->setDescription('Auto-assign Asia Pacific orders');
        $criteria4->setFieldType(AreaCriteria::FIELD_TYPE_COUNTRY);
        $criteria4->setOperator(AreaCriteria::OPERATOR_IN);
        $criteria4->setValue(['CN', 'JP', 'AU', 'NZ', 'SG', 'KR', 'IN']);
        $criteria4->setPriority(8);
        $criteria4->setIsActive(true);
        $this->entityManager->persist($criteria4);
        $io->success('Created criteria: APAC Countries → Asia Pacific');

        $criteria5 = new AreaCriteria();
        $criteria5->setArea($westernEurope);
        $criteria5->setName('Western EU Countries');
        $criteria5->setDescription('Auto-assign Western European orders');
        $criteria5->setFieldType(AreaCriteria::FIELD_TYPE_COUNTRY);
        $criteria5->setOperator(AreaCriteria::OPERATOR_IN);
        $criteria5->setValue(['DE', 'FR', 'NL', 'BE', 'AT', 'CH']);
        $criteria5->setPriority(10);
        $criteria5->setIsActive(true);
        $this->entityManager->persist($criteria5);
        $io->success('Created criteria: Western EU Countries → Western Europe');

        $this->entityManager->flush();

        // Create Manager Availabilities
        $io->section('Creating Manager Availability Schedules');

        // Manager 1 availability: Monday-Friday 9am-5pm UTC
        for ($day = 1; $day <= 5; $day++) {
            $availability = new AreaManagerAvailability();
            $availability->setAreaManager($manager1);
            $availability->setDayOfWeek($day);
            $availability->setStartTime(new \DateTime('09:00'));
            $availability->setEndTime(new \DateTime('17:00'));
            $availability->setTimezone('UTC');
            $availability->setIsActive(true);
            $this->entityManager->persist($availability);
        }
        $io->success("Set {$users[0]->getFullName()} availability: Mon-Fri 9am-5pm UTC");

        // Manager 2 availability: Monday-Friday 1pm-9pm UTC (different shift)
        for ($day = 1; $day <= 5; $day++) {
            $availability = new AreaManagerAvailability();
            $availability->setAreaManager($manager2);
            $availability->setDayOfWeek($day);
            $availability->setStartTime(new \DateTime('13:00'));
            $availability->setEndTime(new \DateTime('21:00'));
            $availability->setTimezone('UTC');
            $availability->setIsActive(true);
            $this->entityManager->persist($availability);
        }
        $io->success("Set {$users[1]->getFullName()} availability: Mon-Fri 1pm-9pm UTC");

        // Manager 3 availability: Full week 8am-6pm CET
        if (count($users) > 2) {
            for ($day = 1; $day <= 7; $day++) {
                $availability = new AreaManagerAvailability();
                $availability->setAreaManager($manager3);
                $availability->setDayOfWeek($day);
                $availability->setStartTime(new \DateTime('08:00'));
                $availability->setEndTime(new \DateTime('18:00'));
                $availability->setTimezone('Europe/Vienna');
                $availability->setIsActive(true);
                $this->entityManager->persist($availability);
            }
            $io->success("Set {$users[2]->getFullName()} availability: Mon-Sun 8am-6pm CET");
        }

        $this->entityManager->flush();

        // Summary
        $io->section('Summary');
        $io->table(
            ['Entity', 'Count'],
            [
                ['Areas', 5],
                ['Area Managers', min(5, count($users))],
                ['Area Criteria', 5],
                ['Manager Availabilities', 5 + 5 + (count($users) > 2 ? 7 : 0)],
            ]
        );

        $io->success('Dummy data loaded successfully!');

        $io->section('Next Steps');
        $io->listing([
            'View areas: GET /api/v1/areas',
            'View area managers: GET /api/v1/area_managers',
            'View criteria: GET /api/v1/area_criterias',
            'Get available managers: GET /api/area-managers/available/' . $client->getId()->toRfc4122(),
            'Test auto-assignment: POST /api/area-managers/assign/inquiry/{inquiryId}',
        ]);

        return Command::SUCCESS;
    }
}
