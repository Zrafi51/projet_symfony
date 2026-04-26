<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:fixtures:load-users',
    description: 'Loads randomly generated fake users into the database.',
)]
class LoadUserFixturesCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('count', InputArgument::OPTIONAL, 'Number of users to generate', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->userRepository->isDatabaseAvailable()) {
            $io->error('Database is not available. Please check your connection.');
            return Command::FAILURE;
        }

        $countToGenerate = (int) $input->getArgument('count');
        $io->title(sprintf('Generating %d Random User Fixtures', $countToGenerate));

        $firstNames = ['Jean', 'Sophie', 'Luc', 'Alice', 'Pierre', 'Marie', 'Paul', 'Julie', 'Thomas', 'Emma', 'Nicolas', 'Chloe', 'Antoine', 'Camille', 'Julien', 'Sarah'];
        $lastNames = ['Dupont', 'Martin', 'Lefevre', 'Moreau', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Roux', 'Simon', 'Laurent', 'Michel'];
        $cities = ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice', 'Nantes', 'Strasbourg', 'Montpellier', 'Bordeaux', 'Lille'];

        $count = 0;
        for ($i = 0; $i < $countToGenerate; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $randomString = substr(md5(uniqid('', true)), 0, 5);
            $email = strtolower($firstName . '.' . $lastName . '_' . $randomString . '@example.com');
            
            $gender = in_array($firstName, ['Sophie', 'Alice', 'Marie', 'Julie', 'Emma', 'Chloe', 'Camille', 'Sarah']) ? 'women' : 'men';
            $photoId = rand(1, 99);
            $photoSource = "https://randomuser.me/api/portraits/{$gender}/{$photoId}.jpg";

            $userData = [
                'nom' => $lastName,
                'prenom' => $firstName,
                'email' => $email,
                'password' => 'password123',
                'telephone' => '+33' . rand(600000000, 799999999),
                'adresse' => rand(1, 150) . ' Rue de ' . $cities[array_rand($cities)],
                'date_naissance' => date('Y-m-d', rand(strtotime('1960-01-01'), strtotime('2005-12-31'))),
                'role' => (rand(1, 10) > 9) ? 'ADMIN' : 'USER',
                'is_active' => (rand(1, 10) > 1),
                'is_validated' => (rand(1, 10) > 2),
            ];

            $userData['photo_url'] = $this->downloadAvatar($photoSource, $userData['email']);

            if ($this->userRepository->register($userData)) {
                $count++;
                $io->text(sprintf('Created random user: %s (%s)', $userData['email'], $userData['role']));
            } else {
                $io->error(sprintf('Failed to create random user: %s', $userData['email']));
            }
        }

        $io->success(sprintf('Successfully loaded %d random user fixtures!', $count));

        return Command::SUCCESS;
    }

    private function downloadAvatar(string $sourceUrl, string $email): string
    {
        $dir = $this->projectDir . '/public/uploads/profile-photos';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'fixture_' . md5($email) . '.jpg';
        $path = $dir . '/' . $filename;

        // Download the real photo and save it locally
        $imageContent = @file_get_contents($sourceUrl);
        if ($imageContent !== false) {
            file_put_contents($path, $imageContent);
        }

        return '/uploads/profile-photos/' . $filename;
    }
}
