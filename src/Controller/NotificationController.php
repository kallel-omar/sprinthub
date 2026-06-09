<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\WorkspaceInvitation;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notification_index')]
    public function index(NotificationRepository $notificationRepository): Response
    {
        $notifications = $notificationRepository->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/count', name: 'app_notification_count')]
    public function count(NotificationRepository $notificationRepository): Response
    {
        $count = $notificationRepository->count([
            'user' => $this->getUser(),
            'isRead' => false,
        ]);

        return new Response((string) $count);
    }

    #[Route('/{id}/read', name: 'app_notification_read')]
    public function markAsRead(
        Notification $notification,
        EntityManagerInterface $entityManager
    ): Response {
        if ($notification->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'You are not allowed to access this notification.');

            return $this->redirectToRoute('app_notification_index');
        }

        $notification->setIsRead(true);
        $entityManager->flush();

        $this->addFlash('success', 'Notification marked as read.');

        return $this->redirectToRoute('app_notification_index');
    }

    #[Route('/{id}/decline', name: 'app_notification_decline')]
    public function decline(
        Notification $notification,
        EntityManagerInterface $entityManager
    ): Response {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($notification->getLink()) {
            $path = parse_url($notification->getLink(), PHP_URL_PATH);
            $token = basename($path);

            $invitation = $entityManager
                ->getRepository(WorkspaceInvitation::class)
                ->findOneBy([
                    'token' => $token,
                    'status' => 'pending',
                ]);

            if ($invitation) {
                $invitation->setStatus('declined');

                $invitedBy = $invitation->getInvitedBy();

                if ($invitedBy) {
                    $notificationToSender = new Notification();
                    $notificationToSender->setUser($invitedBy);
                    $notificationToSender->setMessage(
                        $notification->getUser()->getFullName() .
                        ' declined your invitation to workspace "' .
                        $invitation->getWorkspace()->getName() .
                        '".'
                    );

                    $entityManager->persist($notificationToSender);
                }
            }
        }

        $notification->setIsRead(true);

        $entityManager->flush();

        $this->addFlash('success', 'Invitation declined.');

        return $this->redirectToRoute('app_notification_index');
    }
    #[Route('/{id}/open', name: 'app_notification_open')]
public function open(
    Notification $notification,
    EntityManagerInterface $entityManager
): Response {
    if ($notification->getUser() !== $this->getUser()) {
        $this->addFlash('danger', 'You are not allowed to access this notification.');

        return $this->redirectToRoute('app_notification_index');
    }

    $link = $notification->getLink();

    $notification->setIsRead(true);
    $entityManager->flush();

    if ($link) {
        return $this->redirect($link);
    }

    return $this->redirectToRoute('app_notification_index');
}
}