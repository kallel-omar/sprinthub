<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\Workspace;
use App\Form\ProjectMemberType;
use App\Form\ProjectType;
use App\Repository\LabelRepository;
use App\Entity\ProjectJoinRequest;
use App\Repository\ProjectJoinRequestRepository;
use App\Repository\ActivityLogRepository;
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

    $workspaceRole = $this->getUserWorkspaceRole($workspace);

    if (!in_array($workspaceRole, ['owner', 'admin'], true)) {
        $this->addFlash('danger', 'Only workspace owner or admin can create projects.');

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $workspace->getId(),
        ]);
    }

    $project = new Project();

    $form = $this->createForm(ProjectType::class, $project);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $project->setWorkspace($workspace);
        $project->setSlug($this->slugify($project->getName()));
        $project->addMember($user);

        if ($workspaceRole === 'owner') {
            $project->setApprovalStatus('approved');
        } else {
            $project->setApprovalStatus('pending');
        }

        $entityManager->persist($project);
        $entityManager->flush();

        if ($project->getApprovalStatus() === 'approved') {
            foreach ($workspace->getMembers() as $workspaceMember) {
                $memberUser = $workspaceMember->getUser();

                if (!$memberUser instanceof User) {
                    continue;
                }

                if ($memberUser->getId() === $user->getId()) {
                    continue;
                }

                $notification = new Notification();
                $notification->setUser($memberUser);
                $notification->setMessage(
                    $user->getFullName() . ' created project "' . $project->getName() . '".'
                );
                $notification->setLink(
                    $this->generateUrl('app_project_show', [
                        'id' => $project->getId(),
                    ])
                );

                $entityManager->persist($notification);
            }

            $this->addFlash('success', 'Project created successfully.');
        } else {
    foreach ($workspace->getMembers() as $workspaceMember) {
        if ($workspaceMember->getRole() !== 'owner') {
            continue;
        }

        $owner = $workspaceMember->getUser();

        if (!$owner instanceof User) {
            continue;
        }

        $notification = new Notification();
        $notification->setUser($owner);
        $notification->setMessage(
            $user->getFullName() . ' created project "' . $project->getName() . '" and is waiting for your approval.'
        );
        $notification->setLink(
            $this->generateUrl('app_project_approve', [
                'id' => $project->getId(),
            ])
        );

        $entityManager->persist($notification);
    }

    $this->addFlash('success', 'Project created and waiting for owner approval.');
}

        $entityManager->flush();

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
            if (!$this->isProjectMember($project)) {
        $this->addFlash('danger', 'You no longer have access to this project.');

        return $this->redirectToRoute('app_dashboard');
    }

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
    
    #[Route('', name: 'app_project_index')]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $projects = [];

        foreach ($user->getWorkspaceMemberships() as $membership) {
            $workspace = $membership->getWorkspace();

            foreach ($workspace->getProjects() as $project) {
                if ($project->getMembers()->contains($user)) {
                    $projects[] = $project;
                }
            }
        }

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
        ]);
    }



    #[Route('/join-requests', name: 'app_project_join_requests')]
public function joinRequests(
    ProjectJoinRequestRepository $projectJoinRequestRepository
): Response {
    $user = $this->getUser();

    if (!$user instanceof User) {
        return $this->redirectToRoute('app_login');
    }

    $joinRequests = [];

    foreach ($user->getWorkspaceMemberships() as $membership) {
        if (!in_array($membership->getRole(), ['owner', 'admin'], true)) {
            continue;
        }

        foreach ($membership->getWorkspace()->getProjects() as $project) {
            foreach ($projectJoinRequestRepository->findPendingByProject($project) as $joinRequest) {
                $joinRequests[] = $joinRequest;
            }
        }
    }

    return $this->render('project/join_requests.html.twig', [
        'joinRequests' => $joinRequests,
    ]);
}

   #[Route('/{id}', name: 'app_project_show')]
public function show(
    Project $project,
    Request $request,
    LabelRepository $labelRepository,
    ProjectJoinRequestRepository $projectJoinRequestRepository,
    EntityManagerInterface $entityManager
): Response {
    $currentUser = $this->getUser();

    if (!$currentUser instanceof User) {
        return $this->redirectToRoute('app_login');
    }

    $existingJoinRequest = $entityManager
        ->getRepository(ProjectJoinRequest::class)
        ->findOneBy([
            'project' => $project,
            'user' => $currentUser,
            'status' => 'pending',
        ]);

    if (!$this->isProjectMember($project)) {
        return $this->render('project/request_access.html.twig', [
            'project' => $project,
            'existingJoinRequest' => $existingJoinRequest,
        ]);
    }

    $currentWorkspaceRole = $this->getUserWorkspaceRole($project->getWorkspace());

    $canManageProjectMembers = in_array($currentWorkspaceRole, ['owner', 'admin'], true);
    $canManageAllTasks = in_array($currentWorkspaceRole, ['owner', 'admin'], true);

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

            $addedNotification = new Notification();
            $addedNotification->setUser($userToAdd);
            $addedNotification->setMessage(
                'You have been added to project "' . $project->getName() . '".'
            );
            $addedNotification->setLink(
                $this->generateUrl('app_project_show', [
                    'id' => $project->getId(),
                ])
            );

            $entityManager->persist($addedNotification);

            $this->notifyProjectMembers(
                $entityManager,
                $project,
                $this->getUserDisplayName() . ' added ' .
                $userToAdd->getFullName() .
                ' to project "' . $project->getName() . '".',
                $userToAdd
            );

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

    $pendingJoinRequests = [];

    if ($canManageProjectMembers) {
        $pendingJoinRequests = $projectJoinRequestRepository->findPendingByProject($project);
    }

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
        'currentWorkspaceRole' => $currentWorkspaceRole,
        'currentUser' => $currentUser,
        'canManageAllTasks' => $canManageAllTasks,
        'pendingJoinRequests' => $pendingJoinRequests,
    ]);
}
#[Route('/join-request/{id}/approve', name: 'app_project_join_request_approve')]
public function approveJoinRequest(
    ProjectJoinRequest $joinRequest,
    EntityManagerInterface $entityManager
): Response {
    $project = $joinRequest->getProject();

    if (!$project) {
        return $this->redirectToRoute('app_dashboard');
    }

    if (!in_array($this->getUserWorkspaceRole($project->getWorkspace()), ['owner', 'admin'], true)) {
        throw $this->createAccessDeniedException();
    }

    $joinRequest->setStatus('approved');

    $user = $joinRequest->getUser();

    if ($user instanceof User && !$project->getMembers()->contains($user)) {
        $project->addMember($user);
    }

    $notification = new Notification();
    $notification->setUser($user);
    $notification->setMessage(
        'Your request to join project "' . $project->getName() . '" was approved.'
    );
    $notification->setLink(
        $this->generateUrl('app_project_show', ['id' => $project->getId()])
    );

    $entityManager->persist($notification);
    $entityManager->flush();

    $this->addFlash('success', 'Access request approved.');

    return $this->redirectToRoute('app_project_show', [
        'id' => $project->getId(),
    ]);
}

#[Route('/join-request/{id}/reject', name: 'app_project_join_request_reject')]
public function rejectJoinRequest(
    ProjectJoinRequest $joinRequest,
    EntityManagerInterface $entityManager
): Response {
    $project = $joinRequest->getProject();

    if (!$project) {
        return $this->redirectToRoute('app_dashboard');
    }

    if (!in_array($this->getUserWorkspaceRole($project->getWorkspace()), ['owner', 'admin'], true)) {
        throw $this->createAccessDeniedException();
    }

    $joinRequest->setStatus('rejected');

    $notification = new Notification();
    $notification->setUser($joinRequest->getUser());
    $notification->setMessage(
        'Your request to join project "' . $project->getName() . '" was rejected.'
    );

    $entityManager->persist($notification);
    $entityManager->flush();

    $this->addFlash('success', 'Access request rejected.');

    return $this->redirectToRoute('app_project_show', [
        'id' => $project->getId(),
    ]);
}




        #[Route('/{project}/members/{user}/remove', name: 'app_project_member_remove')]
        public function removeProjectMember(
            Project $project,
            User $user,
            EntityManagerInterface $entityManager
        ): Response {
            $currentUser = $this->getUser();
            $workspaceRole = $this->getUserWorkspaceRole($project->getWorkspace());

            if (!in_array($workspaceRole, ['owner', 'admin'], true)) {
                $this->addFlash('danger', 'You cannot remove project members.');

                return $this->redirectToRoute('app_project_show', [
                    'id' => $project->getId(),
                ]);
            }

            if (!$project->getMembers()->contains($user)) {
                $this->addFlash('warning', 'This user is not a project member.');

                return $this->redirectToRoute('app_project_show', [
                    'id' => $project->getId(),
                ]);
            }

            if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
                $this->addFlash('danger', 'You cannot remove yourself from the project.');

                return $this->redirectToRoute('app_project_show', [
                    'id' => $project->getId(),
                ]);
            }

            $userWorkspaceRole = $this->getWorkspaceRoleForUser($project->getWorkspace(), $user);

            if ($userWorkspaceRole === 'owner') {
                $this->addFlash('danger', 'Workspace owner cannot be removed from a project.');

                return $this->redirectToRoute('app_project_show', [
                    'id' => $project->getId(),
                ]);
            }

            if ($workspaceRole === 'admin' && $userWorkspaceRole === 'admin') {
                $this->addFlash('danger', 'Admins cannot remove other admins from a project.');

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

            $this->notifyProjectMembers(
                $entityManager,
                $project,
                $this->getUserDisplayName() . ' removed ' .
                $user->getFullName() .
                ' from project "' . $project->getName() . '".',
                $user
            );

            $removedNotification = new Notification();
            $removedNotification->setUser($user);
            $removedNotification->setMessage(
                'You have been removed from project "' . $project->getName() . '".'
            );

            $entityManager->persist($removedNotification);

            $project->removeMember($user);
            $entityManager->flush();

            $this->addFlash('success', 'Project member removed successfully.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }
        #[Route('/{id}/approve', name: 'app_project_approve')]
public function approve(
    Project $project,
    EntityManagerInterface $entityManager
): Response {
    $workspaceRole = $this->getUserWorkspaceRole($project->getWorkspace());

    if ($workspaceRole !== 'owner') {
        $this->addFlash('danger', 'Only workspace owner can approve projects.');

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $project->getWorkspace()->getId(),
        ]);
    }

    if ($project->getApprovalStatus() === 'approved') {
        $this->addFlash('warning', 'Project is already approved.');

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $project->getWorkspace()->getId(),
        ]);
    }

    $project->setApprovalStatus('approved');
    foreach ($project->getWorkspace()->getMembers() as $workspaceMember) {
    if ($workspaceMember->getRole() !== 'owner') {
        continue;
    }

    $owner = $workspaceMember->getUser();

    if ($owner instanceof User && !$project->getMembers()->contains($owner)) {
        $project->addMember($owner);
    }
}

    foreach ($project->getMembers() as $member) {
        $notification = new Notification();
        $notification->setUser($member);
        $notification->setMessage('Project "' . $project->getName() . '" has been approved.');
        $notification->setLink(
            $this->generateUrl('app_project_show', [
                'id' => $project->getId(),
            ])
        );

        $entityManager->persist($notification);
    }

    $entityManager->flush();

    $this->addFlash('success', 'Project approved successfully.');

    return $this->redirectToRoute('app_workspace_show', [
        'id' => $project->getWorkspace()->getId(),
    ]);
} 

#[Route('/{id}/reject', name: 'app_project_reject')]
public function reject(
    Project $project,
    EntityManagerInterface $entityManager
): Response {
    $workspaceRole = $this->getUserWorkspaceRole($project->getWorkspace());

    if ($workspaceRole !== 'owner') {
        $this->addFlash('danger', 'Only workspace owner can reject projects.');

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $project->getWorkspace()->getId(),
        ]);
    }

    if ($project->getApprovalStatus() === 'rejected') {
        $this->addFlash('warning', 'Project is already rejected.');

        return $this->redirectToRoute('app_workspace_show', [
            'id' => $project->getWorkspace()->getId(),
        ]);
    }

    $project->setApprovalStatus('rejected');

    foreach ($project->getMembers() as $member) {
        if (!$member instanceof User) {
            continue;
        }

        $notification = new Notification();
        $notification->setUser($member);
        $notification->setMessage(
            'Project "' . $project->getName() . '" has been rejected.'
        );

        $entityManager->persist($notification);
    }

    $entityManager->flush();

    $this->addFlash('success', 'Project rejected successfully.');

    return $this->redirectToRoute('app_workspace_show', [
        'id' => $project->getWorkspace()->getId(),
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
        $projectName = $project->getName();

        $this->notifyWorkspaceMembers(
            $entityManager,
            $project->getWorkspace(),
            $this->getUserDisplayName() . ' deleted project "' . $projectName . '".'
        );

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

    private function notifyProjectMembers(
        EntityManagerInterface $entityManager,
        Project $project,
        string $message,
        ?User $excludedUser = null
    ): void {
        $currentUser = $this->getUser();

        foreach ($project->getMembers() as $member) {
            if (!$member instanceof User) {
                continue;
            }

            if ($currentUser instanceof User && $member->getId() === $currentUser->getId()) {
                continue;
            }

            if ($excludedUser instanceof User && $member->getId() === $excludedUser->getId()) {
                continue;
            }

            $notification = new Notification();
            $notification->setUser($member);
            $notification->setMessage($message);
            $notification->setLink(
                $this->generateUrl('app_project_show', [
                    'id' => $project->getId(),
                ])
            );

            $entityManager->persist($notification);
        }
    }

    private function notifyWorkspaceManagers(
        EntityManagerInterface $entityManager,
        Workspace $workspace,
        string $message
    ): void {
        $currentUser = $this->getUser();

        foreach ($workspace->getMembers() as $workspaceMember) {
            if (!in_array($workspaceMember->getRole(), ['owner', 'admin'], true)) {
                continue;
            }

            $manager = $workspaceMember->getUser();

            if (!$manager instanceof User) {
                continue;
            }

            if ($currentUser instanceof User && $manager->getId() === $currentUser->getId()) {
                continue;
            }

            $notification = new Notification();
            $notification->setUser($manager);
            $notification->setMessage($message);

            $entityManager->persist($notification);
        }
    }

    private function denyAccessUnlessProjectMember(Project $project): void
    {
        $user = $this->getUser();

        if (!$user instanceof User || !$project->getMembers()->contains($user)) {
            $this->addFlash('danger', 'You no longer have access to this project.');

            throw $this->createAccessDeniedException('REDIRECT_TO_DASHBOARD');
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

    private function getUserDisplayName(): string
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return 'Someone';
        }

        return ucfirst($user->getFullName() ?? 'Someone');
    }

    private function getWorkspaceRoleForUser(Workspace $workspace, User $user): ?string
    {
        foreach ($workspace->getMembers() as $member) {
            if ($member->getUser()->getId() === $user->getId()) {
                return $member->getRole();
            }
        }

        return null;
    }

    private function isProjectMember(Project $project): bool
    {
        $user = $this->getUser();

        return $user instanceof User && $project->getMembers()->contains($user);
    }

    private function notifyWorkspaceMembers(
        EntityManagerInterface $entityManager,
        Workspace $workspace,
        string $message
    ): void {
        $currentUser = $this->getUser();

        foreach ($workspace->getMembers() as $workspaceMember) {
            $member = $workspaceMember->getUser();

            if (!$member instanceof User) {
                continue;
            }

            if ($currentUser instanceof User && $member->getId() === $currentUser->getId()) {
                continue;
            }

            $notification = new Notification();
            $notification->setUser($member);
            $notification->setMessage($message);

            $entityManager->persist($notification);
        }
    }
        #[Route('/{id}/request-access', name: 'app_project_request_access')]
public function requestAccess(
    Project $project,
    EntityManagerInterface $entityManager
): Response {
    $user = $this->getUser();

    if (!$user instanceof User) {
        return $this->redirectToRoute('app_login');
    }

    if ($project->getMembers()->contains($user)) {
        $this->addFlash('warning', 'You are already a member of this project.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $project->getId(),
        ]);
    }

    $existingRequest = $entityManager
        ->getRepository(ProjectJoinRequest::class)
        ->findOneBy([
            'project' => $project,
            'user' => $user,
            'status' => 'pending',
        ]);

    if ($existingRequest) {
        $this->addFlash('warning', 'You already requested access to this project.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $project->getId(),
        ]);
    }

    $joinRequest = new ProjectJoinRequest();
    $joinRequest->setProject($project);
    $joinRequest->setUser($user);

    $entityManager->persist($joinRequest);

    foreach ($project->getMembers() as $member) {
        if (!$member instanceof User) {
            continue;
        }

        $role = $this->getWorkspaceRoleForUser($project->getWorkspace(), $member);

        if (!in_array($role, ['owner', 'admin'], true)) {
            continue;
        }

        $notification = new Notification();
        $notification->setUser($member);
        $notification->setMessage(
            $user->getFullName() . ' requested access to project "' . $project->getName() . '".'
        );
        $notification->setLink(
            $this->generateUrl('app_project_show', [
                'id' => $project->getId(),
            ])
        );

        $entityManager->persist($notification);
    }

    $entityManager->flush();

    $this->addFlash('success', 'Access request sent successfully.');

    return $this->redirectToRoute('app_project_show', [
        'id' => $project->getId(),
    ]);
}

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text ?: 'project';
    }
    #[Route('/{id}/activity', name: 'app_project_activity')]
public function activity(
    Project $project,
    Request $request,
    ActivityLogRepository $activityLogRepository
): Response {
    if (!$this->isProjectMember($project)) {
        $this->addFlash('danger', 'You no longer have access to this project.');

        return $this->redirectToRoute('app_dashboard');
    }

    $type = $request->query->get('type');

    $logs = $activityLogRepository->findByProjectAndType(
        $project,
        $type
    );

    return $this->render('project/activity.html.twig', [
        'project' => $project,
        'logs' => $logs,
        'selectedType' => $type,
    ]);
}

}