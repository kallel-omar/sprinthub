<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\WorkspaceInvitation;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

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

#[Route('/{id}/delete', name: 'app_notification_delete', methods: ['POST'])]
public function delete(
    Notification $notification,
    EntityManagerInterface $entityManager
): Response {
    if ($notification->getUser() !== $this->getUser()) {
        $this->addFlash('danger', 'You are not allowed to delete this notification.');

        return $this->redirectToRoute('app_notification_index');
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
            $this->addFlash('danger', 'You must accept or decline this invitation before deleting it.');

            return $this->redirectToRoute('app_notification_index');
        }
    }

    $entityManager->remove($notification);
    $entityManager->flush();

    $this->addFlash('success', 'Notification deleted successfully.');

    return $this->redirectToRoute('app_notification_index');
}
#[Route('/delete-normal', name: 'app_notification_delete_normal', methods: ['POST'])]
public function deleteNormal(
    NotificationRepository $notificationRepository,
    EntityManagerInterface $entityManager
): Response {
    $notifications = $notificationRepository->findBy([
        'user' => $this->getUser(),
    ]);

    foreach ($notifications as $notification) {
        $shouldKeep = false;

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
                $shouldKeep = true;
            }
        }

        if ($shouldKeep) {
            continue;
        }

        $entityManager->remove($notification);
    }

    $entityManager->flush();

    $this->addFlash('success', 'Normal notifications deleted successfully.');

    return $this->redirectToRoute('app_notification_index');
}

#[Route('/mark-all-read', name: 'app_notification_mark_all_read', methods: ['POST'])]
public function markAllAsRead(
    NotificationRepository $notificationRepository,
    EntityManagerInterface $entityManager
): Response {
    $notifications = $notificationRepository->findBy([
        'user' => $this->getUser(),
        'isRead' => false,
    ]);

    foreach ($notifications as $notification) {
        $notification->setIsRead(true);
    }

    $entityManager->flush();

    $this->addFlash('success', 'All notifications marked as read.');

    return $this->redirectToRoute('app_notification_index');
}
    #[Route('/latest', name: 'app_notification_latest')]
public function latest(
    NotificationRepository $notificationRepository
): Response {
    $notifications = $notificationRepository->findBy(
        ['user' => $this->getUser()],
        ['createdAt' => 'DESC'],
        5
    );

    return $this->render('notification/_dropdown.html.twig', [
        'notifications' => $notifications,
    ]);
}
#[Route('/mark-dropdown-read', name: 'app_notification_mark_dropdown_read', methods: ['POST'])]
public function markDropdownAsRead(
    NotificationRepository $notificationRepository,
    EntityManagerInterface $entityManager
): JsonResponse {
    $notifications = $notificationRepository->findBy([
        'user' => $this->getUser(),
        'isRead' => false,
    ]);

    foreach ($notifications as $notification) {
        if ($notification->getLink() && str_contains($notification->getLink(), 'invite/accept')) {
            continue;
        }

        $notification->setIsRead(true);
    }

    $entityManager->flush();

    return new JsonResponse([
        'success' => true,
    ]);
}
#[Route('/live-count', name: 'app_notification_live_count')]
public function liveCount(NotificationRepository $notificationRepository): JsonResponse
{
    $count = $notificationRepository->count([
        'user' => $this->getUser(),
        'isRead' => false,
    ]);

    return new JsonResponse([
        'count' => $count,
    ]);
}

}