<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
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
        ActivityLogRepository $activityLogRepository
    ): Response {
        $totalWorkspaces = $workspaceRepository->count([]);
        $totalProjects = $projectRepository->count([]);
        $totalTasks = $taskRepository->count([]);

        $todoTasks = $taskRepository->count(['status' => 'todo']);
        $inProgressTasks = $taskRepository->count(['status' => 'in_progress']);
        $doneTasks = $taskRepository->count(['status' => 'done']);

        $completionRate = $totalTasks > 0
            ? round(($doneTasks / $totalTasks) * 100, 1)
            : 0;

        $recentActivities = $activityLogRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            10
        );

        return $this->render('dashboard/index.html.twig', [
            'totalWorkspaces' => $totalWorkspaces,
            'totalProjects' => $totalProjects,
            'totalTasks' => $totalTasks,
            'todoTasks' => $todoTasks,
            'inProgressTasks' => $inProgressTasks,
            'doneTasks' => $doneTasks,
            'completionRate' => $completionRate,
            'recentActivities' => $recentActivities,
        ]);
    }
}