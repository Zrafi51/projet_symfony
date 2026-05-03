<?php

namespace App\Controller\Admin;

use App\Entity\Music;
use App\Repository\MusicRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/music')]
#[IsGranted('ROLE_ADMIN')]
class MusicController extends AbstractController
{
    #[Route('', name: 'app_admin_music')]
    public function index(MusicRepository $musicRepo): Response
    {
        return $this->render('admin/music.html.twig', [
            'tracks' => $musicRepo->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_admin_music_new', methods: ['POST'])]
    public function upload(
        Request $request,
        MusicRepository $musicRepo,
        SluggerInterface $slugger
    ): Response {
        if (!$this->isCsrfTokenValid('music_upload', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_music');
        }

        $title = trim((string) $request->request->get('title', ''));
        $artist = trim((string) $request->request->get('artist', ''));
        /** @var UploadedFile|null $file */
        $file = $request->files->get('audio');

        $errors = [];
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($title) > 150) {
            $errors[] = 'Le titre ne peut pas dépasser 150 caractères.';
        }
        if ($artist !== '' && mb_strlen($artist) > 150) {
            $errors[] = 'Le nom de l\'artiste ne peut pas dépasser 150 caractères.';
        }
        if (!$file instanceof UploadedFile) {
            $errors[] = 'Veuillez sélectionner un fichier audio.';
        } else {
            $mime = $file->getMimeType();
            $allowed = ['audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/aac', 'audio/mp4'];
            if ($mime && !in_array($mime, $allowed, true)) {
                $errors[] = 'Format audio non supporté (MP3, OGG, WAV, AAC, M4A acceptés).';
            }
            if ($file->getSize() > 20 * 1024 * 1024) {
                $errors[] = 'Le fichier audio ne peut pas dépasser 20 MB.';
            }
        }

        if ($errors) {
            foreach ($errors as $msg) {
                $this->addFlash('error', $msg);
            }
            return $this->redirectToRoute('app_admin_music');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . ($file->guessExtension() ?: 'mp3');

        try {
            $file->move($this->getParameter('music_directory'), $newFilename);
        } catch (FileException $e) {
            $this->addFlash('error', 'Erreur lors du téléchargement : ' . $e->getMessage());
            return $this->redirectToRoute('app_admin_music');
        }

        $music = (new Music())
            ->setTitle($title)
            ->setArtist($artist !== '' ? $artist : null)
            ->setFilename($newFilename);

        $musicRepo->save($music);

        $this->addFlash('success', sprintf('« %s » ajouté à la playlist.', $music->getDisplayName()));
        return $this->redirectToRoute('app_admin_music');
    }

    #[Route('/{id}/delete', name: 'app_admin_music_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, MusicRepository $musicRepo): Response
    {
        $music = $musicRepo->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('music_delete' . $music->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_music');
        }

        $path = $this->getParameter('music_directory') . '/' . $music->getFilename();
        if ($music->getFilename() && file_exists($path)) {
            @unlink($path);
        }

        $musicRepo->remove($music);

        $this->addFlash('success', 'Piste supprimée.');
        return $this->redirectToRoute('app_admin_music');
    }
}
