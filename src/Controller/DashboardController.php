<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\NotificationRepository;
use App\Repository\TaskRepository;
use App\Repository\ProjectRepository;
use App\Repository\ProjectJoinRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
    TaskRepository $taskRepository,
    ActivityLogRepository $activityLogRepository,
    NotificationRepository $notificationRepository,
    ProjectRepository $projectRepository,
    ProjectJoinRequestRepository $projectJoinRequestRepository
): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash('warning', 'Please login first.');

            return $this->redirectToRoute('app_login');
        }

        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $endOfWeek = $today->modify('+7 days');

        $userWorkspaces = [];
        $userProjects = [];

        foreach ($user->getWorkspaceMemberships() as $membership) {
            $workspace = $membership->getWorkspace();
            $userWorkspaces[] = $workspace;

            foreach ($workspace->getProjects() as $project) {
                if ($project->getMembers()->contains($user)) {
                    $userProjects[] = $project;
                }
            }
        }

        $totalWorkspaces = count($userWorkspaces);
        $totalProjects = count($userProjects);

        $allMyTasks = $taskRepository->findBy([
            'assignee' => $user,
        ]);

        $overdueTasks = [];
        $dueTodayTasks = [];
        $dueThisWeekTasks = [];

        foreach ($allMyTasks as $task) {
            if (!$task->getDueDate() || $task->getStatus() === 'done') {
                continue;
            }

            if ($task->getDueDate() < $today) {
                $overdueTasks[] = $task;
            } elseif ($task->getDueDate() >= $today && $task->getDueDate() < $tomorrow) {
                $dueTodayTasks[] = $task;
            } elseif ($task->getDueDate() >= $tomorrow && $task->getDueDate() <= $endOfWeek) {
                $dueThisWeekTasks[] = $task;
            }
        }

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

        $ownerWorkspaces = [];
        $memberProjects = [];

        foreach ($user->getWorkspaceMemberships() as $membership) {
            if ($membership->getRole() === 'owner') {
                $ownerWorkspaces[] = $membership->getWorkspace();

                continue;
            }

            foreach ($membership->getWorkspace()->getProjects() as $project) {
                if ($project->getMembers()->contains($user)) {
                    $memberProjects[] = $project;
                }
            }
        }

        $recentActivities = [];

        $queryBuilder = $activityLogRepository
            ->createQueryBuilder('activity')
            ->orderBy('activity.createdAt', 'DESC')
            ->setMaxResults(8);

        if (!empty($ownerWorkspaces) && !empty($memberProjects)) {
            $queryBuilder
                ->where('activity.workspace IN (:ownerWorkspaces)')
                ->orWhere('activity.project IN (:memberProjects)')
                ->setParameter('ownerWorkspaces', $ownerWorkspaces)
                ->setParameter('memberProjects', $memberProjects);

            $recentActivities = $queryBuilder
                ->getQuery()
                ->getResult();
        } elseif (!empty($ownerWorkspaces)) {
            $queryBuilder
                ->where('activity.workspace IN (:ownerWorkspaces)')
                ->setParameter('ownerWorkspaces', $ownerWorkspaces);

            $recentActivities = $queryBuilder
                ->getQuery()
                ->getResult();
        } elseif (!empty($memberProjects)) {
            $queryBuilder
                ->where('activity.project IN (:memberProjects)')
                ->setParameter('memberProjects', $memberProjects);

            $recentActivities = $queryBuilder
                ->getQuery()
                ->getResult();
        }
        $pendingProjects = 0;
$pendingJoinRequests = 0;

foreach ($user->getWorkspaceMemberships() as $membership) {

    if ($membership->getRole() !== 'owner') {
        continue;
    }

    foreach ($membership->getWorkspace()->getProjects() as $project) {

        if ($project->getApprovalStatus() === 'pending') {
            $pendingProjects++;
        }

        $pendingJoinRequests += count(
            $projectJoinRequestRepository->findPendingByProject($project)
        );
    }
}

        return $this->render('dashboard/index.html.twig', [
            'totalWorkspaces' => $totalWorkspaces,
            'totalProjects' => $totalProjects,
            'totalTasks' => $myTotalTasks,

            'myTasks' => $myTasks,
            'myTotalTasks' => $myTotalTasks,
            'myTodoTasks' => $myTodoTasks,
            'myInProgressTasks' => $myInProgressTasks,
            'myDoneTasks' => $myDoneTasks,
            'myCompletionRate' => $myCompletionRate,

            'overdueTasks' => $overdueTasks,
            'dueTodayTasks' => $dueTodayTasks,
            'dueThisWeekTasks' => $dueThisWeekTasks,

            'unreadNotifications' => $unreadNotifications,
            'recentActivities' => $recentActivities,

            'pendingProjects' => $pendingProjects,
            'pendingJoinRequests' => $pendingJoinRequests,
        ]);
    }
}