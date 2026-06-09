<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Entity\Workspace;
use App\Form\ProjectMemberType;
use App\Form\ProjectType;
use App\Repository\LabelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects')]
final class ProjectController extends AbstractController
{
    #[Route('/new/{id}', name: 'app_project_new')]
    public function new(
        Workspace $workspace,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $project = new Project();

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setWorkspace($workspace);
            $project->setSlug($this->slugify($project->getName()));
            $project->addMember($user);

            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Project created successfully.');

            return $this->redirectToRoute('app_workspace_show', [
                'id' => $workspace->getId(),
            ]);
        }

        return $this->render('project/new.html.twig', [
            'form' => $form,
            'workspace' => $workspace,
        ]);
    }

    #[Route('/{id}/stats', name: 'app_project_stats')]
    public function stats(Project $project): Response
    {
        $this->denyAccessUnlessProjectMember($project);

        $totalTasks = count($project->getTasks());
        $todoTasks = 0;
        $inProgressTasks = 0;
        $doneTasks = 0;
        $highPriority = 0;
        $mediumPriority = 0;
        $lowPriority = 0;

        foreach ($project->getTasks() as $task) {
            if ($task->getStatus() === 'todo') {
                $todoTasks++;
            }

            if ($task->getStatus() === 'in_progress') {
                $inProgressTasks++;
            }

            if ($task->getStatus() === 'done') {
                $doneTasks++;
            }

            if ($task->getPriority() === 'High') {
                $highPriority++;
            }

            if ($task->getPriority() === 'Medium') {
                $mediumPriority++;
            }

            if ($task->getPriority() === 'Low') {
                $lowPriority++;
            }
        }

        $completionRate = $totalTasks > 0
            ? round(($doneTasks / $totalTasks) * 100, 1)
            : 0;

        return $this->render('project/stats.html.twig', [
            'project' => $project,
            'totalTasks' => $totalTasks,
            'todoTasks' => $todoTasks,
            'inProgressTasks' => $inProgressTasks,
            'doneTasks' => $doneTasks,
            'highPriority' => $highPriority,
            'mediumPriority' => $mediumPriority,
            'lowPriority' => $lowPriority,
            'completionRate' => $completionRate,
        ]);
    }

    #[Route('/{id}', name: 'app_project_show')]
    public function show(
        Project $project,
        Request $request,
        LabelRepository $labelRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessProjectMember($project);

        $currentWorkspaceRole = $this->getUserWorkspaceRole($project->getWorkspace());
        $canManageProjectMembers = in_array($currentWorkspaceRole, ['owner', 'admin'], true);

        $workspaceUsers = [];

        foreach ($project->getWorkspace()->getMembers() as $workspaceMember) {
            $workspaceUsers[] = $workspaceMember->getUser();
        }

        $memberForm = $this->createForm(ProjectMemberType::class, null, [
            'workspace_users' => $workspaceUsers,
        ]);

        $memberForm->handleRequest($request);

        if ($memberForm->isSubmitted() && $memberForm->isValid()) {
            if (!$canManageProjectMembers) {
                throw $this->createAccessDeniedException('You cannot add project members.');
            }

            $userToAdd = $memberForm->get('user')->getData();

            if ($userToAdd instanceof User && !$project->getMembers()->contains($userToAdd)) {
                $project->addMember($userToAdd);
                $entityManager->flush();

                $this->addFlash('success', 'Member added to project.');
            } else {
                $this->addFlash('warning', 'User is already a project member.');
            }

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        $search = $request->query->get('search');
        $priority = $request->query->get('priority');
        $status = $request->query->get('status');
        $labelId = $request->query->get('label');

        $labels = $labelRepository->findAll();
        $tasks = $project->getTasks();

        $tasks = $tasks->filter(function ($task) use (
            $search,
            $priority,
            $status,
            $labelId
        ) {
            if ($search && stripos($task->getTitle(), $search) === false) {
                return false;
            }

            if ($priority && $task->getPriority() !== $priority) {
                return false;
            }

            if ($status && $task->getStatus() !== $status) {
                return false;
            }

            if ($labelId) {
                $hasLabel = false;

                foreach ($task->getLabels() as $label) {
                    if ($label->getId() == $labelId) {
                        $hasLabel = true;
                        break;
                    }
                }

                if (!$hasLabel) {
                    return false;
                }
            }

            return true;
        });

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'tasks' => $tasks,
            'labels' => $labels,
            'search' => $search,
            'priority' => $priority,
            'status' => $status,
            'selectedLabel' => $labelId,
            'memberForm' => $memberForm->createView(),
            'canManageProjectMembers' => $canManageProjectMembers,
        ]);
    }

    #[Route('/{project}/members/{user}/remove', name: 'app_project_member_remove')]
    public function removeProjectMember(
        Project $project,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        $workspaceRole = $this->getUserWorkspaceRole($project->getWorkspace());

        if (!in_array($workspaceRole, ['owner', 'admin'], true)) {
            throw $this->createAccessDeniedException('You cannot remove project members.');
        }

        if (!$project->getMembers()->contains($user)) {
            $this->addFlash('warning', 'This user is not a project member.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        if (count($project->getMembers()) <= 1) {
            $this->addFlash('danger', 'A project must have at least one member.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        $project->removeMember($user);
        $entityManager->flush();

        $this->addFlash('success', 'Project member removed successfully.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $project->getId(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_project_delete')]
    public function delete(
        Project $project,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessProjectMember($project);

        $workspaceRole = $this->getUserWorkspaceRole($project->getWorkspace());

        if (!in_array($workspaceRole, ['owner', 'admin'], true)) {
            throw $this->createAccessDeniedException('You cannot delete this project.');
        }

        $workspaceId = $project->getWorkspace()->getId();

        foreach ($project->getTasks() as $task) {
            foreach ($task->getActivityLogs() as $activityLog) {
                $entityManager->remove($activityLog);
            }

            foreach ($task->getLabels() as $label) {
                $task->removeLabel($label);
            }
        }

        foreach ($project->getActivityLogs() as $activityLog) {
            $entityManager->remove($activityLog);
        }

        foreach ($project->getMembers() as $member) {
            $project->removeMember($member);
        }

        $entityManager->remove($project);
        $entityManager->flush();

        $this->addFlash('success', 'Project deleted successfully.');

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $workspaceId,
        ]);
    }

    private function denyAccessUnlessProjectMember(Project $project): void
    {
        $user = $this->getUser();

        if (!$user instanceof User || !$project->getMembers()->contains($user)) {
            throw $this->createAccessDeniedException('You cannot access this project.');
        }
    }

    private function getUserWorkspaceRole(Workspace $workspace): ?string
    {
        foreach ($workspace->getMembers() as $member) {
            if ($member->getUser() === $this->getUser()) {
                return $member->getRole();
            }
        }

        return null;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text ?: 'project';
    }
}