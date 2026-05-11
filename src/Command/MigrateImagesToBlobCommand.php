<?php

namespace App\Command;

use App\Repository\PostRepository;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Service\ImageBlobService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-images-to-blob',
    description: 'Migre toutes les images existantes vers le stockage BLOB en base de données'
)]
class MigrateImagesToBlobCommand extends Command
{
    public function __construct(
        private PostRepository $postRepository,
        private ImageRepository $imageRepository,
        private UserRepository $userRepository,
        private ImageBlobService $blobService,
        private EntityManagerInterface $em,
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration des images vers BLOB');

        $uploadsDir = $this->projectDir . '/public/uploads';
        
        // Migrer les posts
        $io->section('Migration des images de posts');
        $posts = $this->postRepository->findAll();
        $postCount = 0;
        
        foreach ($posts as $post) {
            if ($post->getCheminPhoto() && !$post->hasPhotoBlob()) {
                $filePath = $uploadsDir . '/' . $post->getCheminPhoto();
                $blobData = $this->blobService->convertFileToBlob($filePath);
                
                if ($blobData) {
                    $post->setPhotoBlob($blobData['blob']);
                    $post->setPhotoMimeType($blobData['mimeType']);
                    $postCount++;
                }
            }
        }
        
        $this->em->flush();
        $io->success("$postCount images de posts migrées");

        // Migrer les images de galerie
        $io->section('Migration des images de galerie');
        $images = $this->imageRepository->findAll();
        $imageCount = 0;
        
        foreach ($images as $image) {
            if ($image->getFilename() && !$image->hasImageBlob()) {
                $filePath = $uploadsDir . '/' . $image->getFilename();
                $blobData = $this->blobService->convertFileToBlob($filePath);
                
                if ($blobData) {
                    $image->setImageBlob($blobData['blob']);
                    $image->setMimeType($blobData['mimeType']);
                    $imageCount++;
                }
            }
        }
        
        $this->em->flush();
        $io->success("$imageCount images de galerie migrées");

        // Migrer les photos de profil
        $io->section('Migration des photos de profil');
        $users = $this->userRepository->findAll();
        $userCount = 0;
        
        foreach ($users as $user) {
            if ($user->getProfilePhotoPath() && !$user->hasProfilePhotoBlob()) {
                $filePath = $uploadsDir . '/' . $user->getProfilePhotoPath();
                $blobData = $this->blobService->convertFileToBlob($filePath);
                
                if ($blobData) {
                    $user->setProfilePhotoBlob($blobData['blob']);
                    $user->setProfilePhotoMimeType($blobData['mimeType']);
                    $userCount++;
                }
            }
        }
        
        $this->em->flush();
        $io->success("$userCount photos de profil migrées");

        $io->success('Migration terminée avec succès !');
        
        return Command::SUCCESS;
    }
}
