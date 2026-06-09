<?php

namespace App\Controller;

use App\Entity\Notification;
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
}