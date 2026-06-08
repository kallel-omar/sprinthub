<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\NotificationRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\WorkspaceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        WorkspaceRepository $workspaceRepository,
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        ActivityLogRepository $activityLogRepository,
        NotificationRepository $notificationRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $myTasks = $taskRepository->findBy(
            ['assignee' => $user],
            ['createdAt' => 'DESC'],
            5
        );

        $myTodoTasks = $taskRepository->count([
            'assignee' => $user,
            'status' => 'todo',
        ]);

        $myInProgressTasks = $taskRepository->count([
            'assignee' => $user,
            'status' => 'in_progress',
        ]);

        $myDoneTasks = $taskRepository->count([
            'assignee' => $user,
            'status' => 'done',
        ]);

        $myTotalTasks = $myTodoTasks + $myInProgressTasks + $myDoneTasks;

        $myCompletionRate = $myTotalTasks > 0
            ? round(($myDoneTasks / $myTotalTasks) * 100, 1)
            : 0;

        $unreadNotifications = $notificationRepository->count([
            'user' => $user,
            'isRead' => false,
        ]);

        $recentActivities = $activityLogRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            8
        );

        return $this->render('dashboard/index.html.twig', [
            'totalWorkspaces' => $workspaceRepository->count([]),
            'totalProjects' => $projectRepository->count([]),
            'totalTasks' => $taskRepository->count([]),

            'myTasks' => $myTasks,
            'myTotalTasks' => $myTotalTasks,
            'myTodoTasks' => $myTodoTasks,
            'myInProgressTasks' => $myInProgressTasks,
            'myDoneTasks' => $myDoneTasks,
            'myCompletionRate' => $myCompletionRate,
            'unreadNotifications' => $unreadNotifications,
            'recentActivities' => $recentActivities,
        ]);
    }
}