<?php

namespace App\Controller;

use App\Form\AvatarType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(
        Request $request,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $form = $this->createForm(AvatarType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $avatarFile = $form->get('avatarFile')->getData();

            if ($avatarFile) {

                $originalFilename = pathinfo(
                    $avatarFile->getClientOriginalName(),
                    PATHINFO_FILENAME
                );

                $safeFilename = $slugger->slug($originalFilename);

                $newFilename =
                    $safeFilename
                    . '-'
                    . uniqid()
                    . '.'
                    . $avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('avatars_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Upload failed.');
                }

                $user->setAvatar($newFilename);

                $entityManager->flush();

                return $this->redirectToRoute('app_profile');
            }
        }

        $unreadNotifications = $notificationRepository->count([
            'user' => $user,
            'isRead' => false,
        ]);

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'unreadNotifications' => $unreadNotifications,
            'avatarForm' => $form->createView(),
        ]);
    }
}