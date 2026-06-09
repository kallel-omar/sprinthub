<?php

namespace App\Controller;

use App\Entity\Workspace;
use App\Entity\WorkspaceInvitation;
use App\Entity\WorkspaceMember;
use App\Form\WorkspaceInvitationType;
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
public function index(): Response
{
    $user = $this->getUser();

    $workspaces = [];

    foreach ($user->getWorkspaceMemberships() as $membership) {
        $workspaces[] = $membership->getWorkspace();
    }

    return $this->render('workspace/index.html.twig', [
        'workspaces' => $workspaces,
    ]);
}

    #[Route('/new', name: 'app_workspace_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $workspace = new Workspace();

        $form = $this->createForm(WorkspaceType::class, $workspace);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

            $this->addFlash('success', 'Workspace created successfully.');

            return $this->redirectToRoute('app_workspace_index');
        }

        return $this->render('workspace/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/analytics', name: 'app_workspace_analytics')]
    public function analytics(Workspace $workspace): Response
    {
        $role = $this->getUserWorkspaceRole($workspace);

        if (!$role) {
            $this->addFlash('danger', 'You are not a member of this workspace.');

            return $this->redirectToRoute('app_workspace_index');
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

    #[Route('/{id}/invite', name: 'app_workspace_invite')]
    public function invite(
        Workspace $workspace,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $role = $this->getUserWorkspaceRole($workspace);

        if (!in_array($role, ['owner', 'admin'], true)) {
            $this->addFlash('danger', 'You are not allowed to invite members.');

            return $this->redirectToRoute('app_workspace_show', [
                'id' => $workspace->getId(),
            ]);
        }

        $invitation = new WorkspaceInvitation();

        $form = $this->createForm(WorkspaceInvitationType::class, $invitation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invitation->setWorkspace($workspace);
            $invitation->setToken(bin2hex(random_bytes(32)));
            $invitation->setStatus('pending');
            $invitation->setCreatedAt(new \DateTimeImmutable());
            $invitation->setExpiresAt(new \DateTimeImmutable('+7 days'));

            $entityManager->persist($invitation);
            $entityManager->flush();

            $this->addFlash('success', 'Invitation created successfully.');

            return $this->redirectToRoute('app_workspace_invite', [
                'id' => $workspace->getId(),
            ]);
        }

        return $this->render('workspace/invite.html.twig', [
            'workspace' => $workspace,
            'form' => $form->createView(),
            'invitations' => $workspace->getInvitations(),
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

        if (!in_array($role, ['owner', 'admin'], true)) {
            $this->addFlash('danger', 'You are not allowed to manage members.');

            return $this->redirectToRoute('app_workspace_show', [
                'id' => $workspace->getId(),
            ]);
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
   #[Route('/members/{id}/role', name: 'app_workspace_member_role', methods: ['POST'])]
public function updateMemberRole(
    WorkspaceMember $member,
    Request $request,
    EntityManagerInterface $entityManager
): Response {
    $workspace = $member->getWorkspace();
    $currentUserRole = $this->getUserWorkspaceRole($workspace);

    if ($currentUserRole !== 'owner') {
        $this->addFlash('danger', 'Only the owner can change member roles.');

        return $this->redirectToRoute('app_workspace_members', [
            'id' => $workspace->getId(),
        ]);
    }

    if ($member->getRole() === 'owner') {
        $this->addFlash('danger', 'Owner role cannot be changed.');

        return $this->redirectToRoute('app_workspace_members', [
            'id' => $workspace->getId(),
        ]);
    }

    $newRole = $request->request->get('role');

    if (!in_array($newRole, ['admin', 'member'], true)) {
        $this->addFlash('danger', 'Invalid role selected.');

        return $this->redirectToRoute('app_workspace_members', [
            'id' => $workspace->getId(),
        ]);
    }

    $member->setRole($newRole);
    $entityManager->flush();

    $this->addFlash('success', 'Member role updated successfully.');

    return $this->redirectToRoute('app_workspace_members', [
        'id' => $workspace->getId(),
    ]);
}

    #[Route('/members/{id}/remove', name: 'app_workspace_member_remove')]
public function removeMember(
    WorkspaceMember $member,
    EntityManagerInterface $entityManager
): Response {
    $workspace = $member->getWorkspace();
    $currentUserRole = $this->getUserWorkspaceRole($workspace);

    if (!in_array($currentUserRole, ['owner', 'admin'], true)) {
        $this->addFlash('danger', 'You are not allowed to remove members.');

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $workspace->getId(),
        ]);
    }

    if ($member->getRole() === 'owner') {
        $this->addFlash('danger', 'Owner cannot be removed.');

        return $this->redirectToRoute('app_workspace_members', [
            'id' => $workspace->getId(),
        ]);
    }

    if ($currentUserRole === 'admin' && $member->getRole() === 'admin') {
        $this->addFlash('danger', 'Admins cannot remove other admins.');

        return $this->redirectToRoute('app_workspace_members', [
            'id' => $workspace->getId(),
        ]);
    }

    $user = $member->getUser();

    foreach ($workspace->getProjects() as $project) {
        $project->removeMember($user);
    }

    $entityManager->remove($member);
    $entityManager->flush();

    $this->addFlash('success', 'Member removed from workspace and all projects.');

    return $this->redirectToRoute('app_workspace_members', [
        'id' => $workspace->getId(),
    ]);
}

    #[Route('/{id}/delete', name: 'app_workspace_delete')]
    public function delete(
        Workspace $workspace,
        EntityManagerInterface $entityManager
    ): Response {
        if ($workspace->getOwner() !== $this->getUser()) {
            $this->addFlash('danger', 'Only the owner can delete a workspace.');

            return $this->redirectToRoute('app_workspace_index');
        }

        if (count($workspace->getProjects()) > 0) {
            $this->addFlash('danger', 'Delete the projects first before deleting this workspace.');

            return $this->redirectToRoute('app_workspace_index');
        }

        foreach ($workspace->getInvitations() as $invitation) {
            $entityManager->remove($invitation);
        }

        foreach ($workspace->getMembers() as $member) {
            $entityManager->remove($member);
        }

        $entityManager->remove($workspace);
        $entityManager->flush();

        $this->addFlash('success', 'Workspace deleted successfully.');

        return $this->redirectToRoute('app_workspace_index');
    }

    #[Route('/{id}', name: 'app_workspace_show')]
    public function show(Workspace $workspace): Response
    {
        $role = $this->getUserWorkspaceRole($workspace);

        if (!$role) {
            $this->addFlash('danger', 'You are not a member of this workspace.');

            return $this->redirectToRoute('app_workspace_index');
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

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text ?: 'workspace';
    }
}