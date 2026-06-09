<?php

namespace App\Controller;

use App\Entity\Workspace;
use App\Entity\User;
use App\Entity\WorkspaceInvitation;
use App\Entity\WorkspaceMember;
use App\Form\WorkspaceInvitationType;
use App\Form\WorkspaceMemberType;
use App\Entity\Notification;
use App\Form\WorkspaceType;
use App\Repository\WorkspaceMemberRepository;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/workspaces')]
final class WorkspaceController extends AbstractController
{
   #[Route('', name: 'app_workspace_index')]
public function index(): Response
{
    $user = $this->getUser();

    if (!$user instanceof User) {
        return $this->redirectToRoute('app_login');
    }

    $ownedWorkspaces = [];
    $invitedWorkspaces = [];

    foreach ($user->getWorkspaceMemberships() as $membership) {
        $workspace = $membership->getWorkspace();

        if ($membership->getRole() === 'owner') {
            $ownedWorkspaces[] = $workspace;
        } else {
            $invitedWorkspaces[] = $workspace;
        }
    }

    return $this->render('workspace/index.html.twig', [
        'ownedWorkspaces' => $ownedWorkspaces,
        'invitedWorkspaces' => $invitedWorkspaces,
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

    $inviteLink = null;

    if ($request->isMethod('POST')) {
        $email = strtolower(trim((string) $request->request->get('email')));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Please enter a valid email address.');

            return $this->redirectToRoute('app_workspace_members', [
                'id' => $workspace->getId(),
            ]);
        }

        foreach ($workspace->getMembers() as $member) {
            if (strtolower($member->getUser()->getEmail()) === $email) {
                $this->addFlash('danger', 'This user is already a member of this workspace.');

                return $this->redirectToRoute('app_workspace_members', [
                    'id' => $workspace->getId(),
                ]);
            }
        }

        foreach ($workspace->getInvitations() as $existingInvitation) {
            if (
                strtolower($existingInvitation->getEmail()) === $email
                && $existingInvitation->getStatus() === 'pending'
            ) {
                $this->addFlash('danger', 'A pending invitation already exists for this email.');

                return $this->redirectToRoute('app_workspace_members', [
                    'id' => $workspace->getId(),
                ]);
            }
        }

        $invitation = new WorkspaceInvitation();
        $invitation->setWorkspace($workspace);
        $invitation->setEmail($email);
        $currentUser = $this->getUser();

        if ($currentUser instanceof User) {
            $invitation->setInvitedBy($currentUser);
        }
        $invitation->setToken(bin2hex(random_bytes(32)));
        $invitation->setStatus('pending');
        $invitation->setCreatedAt(new \DateTimeImmutable());
        $invitation->setExpiresAt(new \DateTimeImmutable('+7 days'));

        $entityManager->persist($invitation);

        $inviteLink = $this->generateUrl('app_invitation_accept', [
            'token' => $invitation->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $invitedUser = $entityManager->getRepository(User::class)->findOneBy([
            'email' => $email,
        ]);

        if ($invitedUser) {
            $notification = new Notification();
            $notification->setUser($invitedUser);
            $user = $this->getUser();

        $senderName = $user instanceof User
            ? $user->getFullName()
            : 'Someone';

        $notification->setMessage(
            $senderName . ' invited you to join workspace "' . $workspace->getName() . '".'
        );
        $notification->setLink(
            $this->generateUrl('app_invitation_accept', [
                'token' => $invitation->getToken(),
            ])
        );

            $entityManager->persist($notification);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Invitation link generated successfully.');
    }

    return $this->render('workspace/members.html.twig', [
        'workspace' => $workspace,
        'inviteLink' => $inviteLink,
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
    #[Route('/members/{id}/role', name: 'app_workspace_member_role', methods: ['POST'])]
    public function changeMemberRole(
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

        if ($member->getRole() === $newRole) {
            $this->addFlash('info', 'Role is already the same.');

            return $this->redirectToRoute('app_workspace_members', [
                'id' => $workspace->getId(),
            ]);
        }

        $oldRole = $member->getRole();

$member->setRole($newRole);

$notification = new Notification();
$notification->setUser($member->getUser());
$notification->setMessage(
    'Your role in workspace "' .
    $workspace->getName() .
    '" has been changed from ' .
    ucfirst($oldRole) .
    ' to ' .
    ucfirst($newRole) .
    '.'
);

$entityManager->persist($notification);
        $entityManager->flush();

        $this->addFlash('success', 'Member role updated successfully.');

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
    #[Route('/invitation/{id}/delete', name: 'app_workspace_invitation_delete')]
public function deleteInvitation(
    WorkspaceInvitation $invitation,
    EntityManagerInterface $entityManager
): Response {
    $workspace = $invitation->getWorkspace();

    $entityManager->remove($invitation);
    $entityManager->flush();

    $this->addFlash('success', 'Invitation deleted successfully.');

    return $this->redirectToRoute('app_workspace_members', [
        'id' => $workspace->getId(),
    ]);
}
}