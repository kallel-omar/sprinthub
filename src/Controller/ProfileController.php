<?php

namespace App\Controller;

use App\Entity\User;
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
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash('danger', 'You must be logged in to access your profile.');

            return $this->redirectToRoute('app_login');
        }

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

                $newFilename = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('avatars_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Avatar upload failed.');

                    return $this->redirectToRoute('app_profile');
                }

                $user->setAvatar($newFilename);
                $user->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->flush();

                $this->addFlash('success', 'Avatar updated successfully.');

                return $this->redirectToRoute('app_profile');
            }

            $this->addFlash('warning', 'Please choose an image before uploading.');

            return $this->redirectToRoute('app_profile');
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