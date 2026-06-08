<?php

namespace App\Controller;

use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Form\WorkspaceMemberType;
use App\Form\WorkspaceType;
use App\Repository\WorkspaceMemberRepository;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/workspaces')]
final class WorkspaceController extends AbstractController
{
    #[Route('', name: 'app_workspace_index')]
    public function index(WorkspaceRepository $workspaceRepository): Response
    {
        $workspaces = $workspaceRepository->findBy([
            'owner' => $this->getUser(),
        ]);

        return $this->render('workspace/index.html.twig', [
            'workspaces' => $workspaces,
        ]);
    }

    #[Route('/{id}/analytics', name: 'app_workspace_analytics')]
    public function analytics(Workspace $workspace): Response
    {
        $role = $this->getUserWorkspaceRole($workspace);

        if (!$role) {
            throw $this->createAccessDeniedException(
                'You are not a member of this workspace.'
            );
        }

        $totalProjects = count($workspace->getProjects());
        $totalTasks = 0;
        $todoTasks = 0;
        $inProgressTasks = 0;
        $doneTasks = 0;

        foreach ($workspace->getProjects() as $project) {
            foreach ($project->getTasks() as $task) {
                $totalTasks++;

                if ($task->getStatus() === 'todo') {
                    $todoTasks++;
                }

                if ($task->getStatus() === 'in_progress') {
                    $inProgressTasks++;
                }

                if ($task->getStatus() === 'done') {
                    $doneTasks++;
                }
            }
        }

        $completionRate = $totalTasks > 0
            ? round(($doneTasks / $totalTasks) * 100, 1)
            : 0;

        return $this->render('workspace/analytics.html.twig', [
            'workspace' => $workspace,
            'totalProjects' => $totalProjects,
            'totalTasks' => $totalTasks,
            'todoTasks' => $todoTasks,
            'inProgressTasks' => $inProgressTasks,
            'doneTasks' => $doneTasks,
            'completionRate' => $completionRate,
            'membersCount' => count($workspace->getMembers()),
        ]);
    }

    #[Route('/new', name: 'app_workspace_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $workspace = new Workspace();

        $form = $this->createForm(WorkspaceType::class, $workspace);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();

            $workspace->setOwner($user);
            $workspace->setSlug($this->slugify($workspace->getName()));

            $workspaceMember = new WorkspaceMember();
            $workspaceMember->setWorkspace($workspace);
            $workspaceMember->setUser($user);
            $workspaceMember->setRole('owner');

            $entityManager->persist($workspace);
            $entityManager->persist($workspaceMember);
            $entityManager->flush();

            return $this->redirectToRoute('app_workspace_index');
        }

        return $this->render('workspace/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/members', name: 'app_workspace_members')]
    public function members(
        Workspace $workspace,
        Request $request,
        EntityManagerInterface $entityManager,
        WorkspaceMemberRepository $workspaceMemberRepository
    ): Response {
        
        $role = $this->getUserWorkspaceRole($workspace);

                if (!in_array($role, ['owner', 'admin'])) {
                    throw $this->createAccessDeniedException('You are not allowed to manage members.');
                }


        $workspaceMember = new WorkspaceMember();

        $form = $this->createForm(WorkspaceMemberType::class, $workspaceMember);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingMember = $workspaceMemberRepository->findOneBy([
                'workspace' => $workspace,
                'user' => $workspaceMember->getUser(),
            ]);

            if ($existingMember) {
                $this->addFlash('danger', 'This user is already a member of this workspace.');

                return $this->redirectToRoute('app_workspace_members', [
                    'id' => $workspace->getId(),
                ]);
            }

            $workspaceMember->setWorkspace($workspace);

            $entityManager->persist($workspaceMember);
            $entityManager->flush();

            $this->addFlash('success', 'Member added successfully.');

            return $this->redirectToRoute('app_workspace_members', [
                'id' => $workspace->getId(),
            ]);
        }

        return $this->render('workspace/members.html.twig', [
            'workspace' => $workspace,
            'form' => $form->createView(),
        ]);
    }

   #[Route('/{id}', name: 'app_workspace_show')]
        public function show(Workspace $workspace): Response
        {
            $role = $this->getUserWorkspaceRole($workspace);

            if (!$role) {
                throw $this->createAccessDeniedException(
                    'You are not a member of this workspace.'
                );
            }

            return $this->render('workspace/show.html.twig', [
                'workspace' => $workspace,
            ]);
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
#[Route('/members/{id}/remove', name: 'app_workspace_member_remove')]
public function removeMember(
    WorkspaceMember $member,
    EntityManagerInterface $entityManager
): Response {

    $workspaceId = $member->getWorkspace()->getId();

    if ($member->getRole() === 'owner') {
        $this->addFlash('danger', 'Owner cannot be removed.');

        return $this->redirectToRoute('app_workspace_members', [
            'id' => $workspaceId,
        ]);
    }

    $entityManager->remove($member);
    $entityManager->flush();

    $this->addFlash('success', 'Member removed.');

    return $this->redirectToRoute('app_workspace_members', [
        'id' => $workspaceId,
    ]);
}
#[Route('/{id}/delete', name: 'app_workspace_delete')]
public function delete(
    Workspace $workspace,
    EntityManagerInterface $entityManager
): Response {

    if ($workspace->getOwner() !== $this->getUser()) {
        throw $this->createAccessDeniedException(
            'Only the owner can delete a workspace.'
        );
    }

    $entityManager->remove($workspace);
    $entityManager->flush();

    return $this->redirectToRoute('app_workspace_index');
}

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }
}