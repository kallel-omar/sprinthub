<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskAttachment;
use App\Entity\TaskChecklistItem;
use App\Entity\TaskComment;
use App\Entity\User;
use App\Form\TaskAttachmentType;
use App\Form\TaskChecklistItemType;
use App\Form\TaskCommentType;
use App\Form\TaskType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/tasks')]
final class TaskController extends AbstractController
{
    #[Route('', name: 'app_task_index')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $status = $request->query->get('status');
        $deadline = $request->query->get('deadline');

        $tasks = $entityManager
            ->getRepository(Task::class)
            ->findBy(['assignee' => $user], ['createdAt' => 'DESC']);

        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $endOfWeek = $today->modify('+7 days');

        $tasks = array_filter($tasks, function (Task $task) use ($status, $deadline, $today, $tomorrow, $endOfWeek) {
            if ($status && $task->getStatus() !== $status) {
                return false;
            }

            if ($deadline === 'overdue') {
                return $task->getDueDate()
                    && $task->getDueDate() < $today
                    && $task->getStatus() !== 'done';
            }

            if ($deadline === 'today') {
                return $task->getDueDate()
                    && $task->getDueDate() >= $today
                    && $task->getDueDate() < $tomorrow
                    && $task->getStatus() !== 'done';
            }

            if ($deadline === 'week') {
                return $task->getDueDate()
                    && $task->getDueDate() >= $tomorrow
                    && $task->getDueDate() <= $endOfWeek
                    && $task->getStatus() !== 'done';
            }

            return true;
        });

        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
            'status' => $status,
            'deadline' => $deadline,
        ]);
    }

    #[Route('/new/{id}', name: 'app_task_new')]
    public function new(Project $project, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isProjectMember($project)) {
            $this->addFlash('danger', 'You cannot create tasks in this project.');

            return $this->redirectToRoute('app_project_index');
        }

        if (!$this->isWorkspaceManager($project)) {
            $this->addFlash('danger', 'Only workspace owner or admin can create tasks.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        $task = new Task();

        $form = $this->createForm(TaskType::class, $task, [
            'project_members' => $project->getMembers()->toArray(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $task->setProject($project);
            $task->setCreatedBy($this->getUser());

            $entityManager->persist($task);

            $this->createActivityLog(
                $entityManager,
                'task_created',
                $this->getUserDisplayName() . ' created task "' . $task->getTitle() . '"',
                $task
            );

            $entityManager->flush();

            if ($task->getAssignee()) {
                $notification = new Notification();
                $notification->setUser($task->getAssignee());
                $notification->setMessage(
                    $this->getUserDisplayName() . ' assigned you to task "' . $task->getTitle() . '".'
                );
                $notification->setLink(
                    $this->generateUrl('app_task_show', [
                        'id' => $task->getId(),
                    ])
                );

                $entityManager->persist($notification);
                $entityManager->flush();
            }

            $this->addFlash('success', 'Task created successfully.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        return $this->render('task/new.html.twig', [
            'form' => $form,
            'project' => $project,
        ]);
    }

    #[Route('/{id}/start', name: 'app_task_start')]
    public function start(Task $task, EntityManagerInterface $entityManager): Response
    {
        $project = $task->getProject();

        if (!$project || !$this->canManageTask($task)) {
            $this->addFlash('danger', 'You can only move tasks assigned to you.');

            return $this->redirectToRoute('app_project_index');
        }

        $oldStatus = $task->getStatus();
        $task->setStatus('in_progress');

        $this->createActivityLog(
            $entityManager,
            'task_started',
            $this->getUserDisplayName() . ' started task "' . $task->getTitle() . '"',
            $task
        );

        $this->notifyWorkspaceManagers(
            $entityManager,
            $task,
            $this->getUserDisplayName() . ' moved task "' . $task->getTitle() . '" from ' .
            ucfirst(str_replace('_', ' ', $oldStatus)) . ' to In progress.',
            $this->generateUrl('app_task_show', ['id' => $task->getId()])
        );

        $entityManager->flush();

        $this->addFlash('success', 'Task moved to In Progress.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $project->getId(),
        ]);
    }

    #[Route('/{id}/done', name: 'app_task_done')]
    public function done(Task $task, EntityManagerInterface $entityManager): Response
    {
        $project = $task->getProject();

        if (!$project || !$this->canManageTask($task)) {
            $this->addFlash('danger', 'You can only move tasks assigned to you.');

            return $this->redirectToRoute('app_project_index');
        }

        $oldStatus = $task->getStatus();
        $task->setStatus('done');

        $this->createActivityLog(
            $entityManager,
            'task_completed',
            $this->getUserDisplayName() . ' completed task "' . $task->getTitle() . '"',
            $task
        );

        $this->notifyWorkspaceManagers(
            $entityManager,
            $task,
            $this->getUserDisplayName() . ' moved task "' . $task->getTitle() . '" from ' .
            ucfirst(str_replace('_', ' ', $oldStatus)) . ' to Done.',
            $this->generateUrl('app_task_show', ['id' => $task->getId()])
        );

        $this->notifyTaskAssigneeCompleted($entityManager, $task);
        $this->notifyTaskCreatorCompleted($entityManager, $task);

        $entityManager->flush();

        $this->addFlash('success', 'Task marked as done.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $project->getId(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_task_edit')]
    public function edit(Task $task, Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = $task->getProject();

        if (!$project) {
            $this->addFlash('danger', 'This task is not linked to any project.');

            return $this->redirectToRoute('app_project_index');
        }

        if (!$this->canManageTask($task)) {
            $this->addFlash('danger', 'You can only edit tasks assigned to you.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        $oldAssignee = $task->getAssignee();

        $form = $this->createForm(TaskType::class, $task, [
            'project_members' => $project->getMembers()->toArray(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setUpdatedAt(new \DateTimeImmutable());

            $newAssignee = $task->getAssignee();

            if ($newAssignee && $newAssignee !== $oldAssignee) {
                $notification = new Notification();
                $notification->setUser($newAssignee);
                $notification->setMessage(
                    $this->getUserDisplayName() . ' assigned you to task "' . $task->getTitle() . '".'
                );
                $notification->setLink(
                    $this->generateUrl('app_task_show', [
                        'id' => $task->getId(),
                    ])
                );

                $entityManager->persist($notification);
            }

            $this->createActivityLog(
                $entityManager,
                'task_updated',
                $this->getUserDisplayName() . ' updated task "' . $task->getTitle() . '"',
                $task
            );

            $entityManager->flush();

            $this->addFlash('success', 'Task updated successfully.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        return $this->render('task/edit.html.twig', [
            'form' => $form,
            'task' => $task,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_task_delete')]
    public function delete(Task $task, EntityManagerInterface $entityManager): Response
    {
        $project = $task->getProject();

        if (!$project) {
            $this->addFlash('danger', 'This task is not linked to any project.');

            return $this->redirectToRoute('app_project_index');
        }

        if (!$this->isWorkspaceManager($project)) {
            $this->addFlash('danger', 'Only workspace owner or admin can delete tasks.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        $projectId = $project->getId();
        $taskTitle = $task->getTitle();

        $this->notifyWorkspaceManagers(
            $entityManager,
            $task,
            $this->getUserDisplayName() . ' deleted task "' . $taskTitle . '".'
        );

        foreach ($task->getActivityLogs() as $activityLog) {
            $entityManager->remove($activityLog);
        }

        foreach ($task->getLabels() as $label) {
            $task->removeLabel($label);
        }

        $entityManager->remove($task);
        $entityManager->flush();

        $this->addFlash('success', 'Task deleted successfully.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $projectId,
        ]);
    }

    #[Route('/{id}', name: 'app_task_show')]
    public function show(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $project = $task->getProject();

        if (!$project || !$this->canAccessTask($task)) {
            $this->addFlash('danger', 'You cannot access this task.');

            return $this->redirectToRoute('app_project_index');
        }

        $comment = new TaskComment();
        $commentForm = $this->createForm(TaskCommentType::class, $comment);

        $attachment = new TaskAttachment();
        $attachmentForm = $this->createForm(TaskAttachmentType::class, $attachment);

        $checklistItem = new TaskChecklistItem();
        $checklistForm = $this->createForm(TaskChecklistItemType::class, $checklistItem);

        $commentForm->handleRequest($request);
        $attachmentForm->handleRequest($request);
        $checklistForm->handleRequest($request);

        if (
            ($commentForm->isSubmitted() || $attachmentForm->isSubmitted() || $checklistForm->isSubmitted())
            && !$this->canManageTask($task)
        ) {
            $this->addFlash('danger', 'You can only update tasks assigned to you.');

            return $this->redirectToRoute('app_task_show', [
                'id' => $task->getId(),
            ]);
        }

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $comment->setTask($task);
            $comment->setUser($this->getUser());

            $entityManager->persist($comment);

            $this->createActivityLog(
                $entityManager,
                'comment_added',
                $this->getUserDisplayName() . ' commented on task "' . $task->getTitle() . '"',
                $task
            );

            $this->notifyProjectMembers(
                $entityManager,
                $task,
                $this->getUserDisplayName() . ' commented on task "' . $task->getTitle() . '".'
            );

            $entityManager->flush();

            $this->addFlash('success', 'Comment added successfully.');

            return $this->redirectToRoute('app_task_show', [
                'id' => $task->getId(),
            ]);
        }

        if ($checklistForm->isSubmitted() && $checklistForm->isValid()) {
            $checklistItem->setTask($task);

            $entityManager->persist($checklistItem);

            $this->createActivityLog(
                $entityManager,
                'checklist_added',
                $this->getUserDisplayName() . ' added checklist item "' . $checklistItem->getContent() . '" to task "' . $task->getTitle() . '"',
                $task
            );

            $this->notifyProjectMembers(
                $entityManager,
                $task,
                $this->getUserDisplayName() . ' added a checklist item to task "' . $task->getTitle() . '".'
            );

            $entityManager->flush();

            $this->addFlash('success', 'Checklist item added successfully.');

            return $this->redirectToRoute('app_task_show', [
                'id' => $task->getId(),
            ]);
        }

        if ($attachmentForm->isSubmitted() && $attachmentForm->isValid()) {
            $uploadedFile = $attachmentForm->get('file')->getData();

            if ($uploadedFile) {
                $originalName = $uploadedFile->getClientOriginalName();
                $mimeType = $uploadedFile->getMimeType();
                $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension();

                $originalFilename = pathinfo($originalName, PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                try {
                    $uploadedFile->move(
                        $this->getParameter('task_attachments_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('danger', 'File upload failed.');

                    return $this->redirectToRoute('app_task_show', [
                        'id' => $task->getId(),
                    ]);
                }

                $attachment->setFileName($newFilename);
                $attachment->setOriginalName($originalName);
                $attachment->setMimeType($mimeType);
                $attachment->setTask($task);
                $attachment->setUploadedBy($this->getUser());

                $entityManager->persist($attachment);

                $this->createActivityLog(
                    $entityManager,
                    'attachment_uploaded',
                    $this->getUserDisplayName() . ' uploaded file "' . $originalName . '" to task "' . $task->getTitle() . '"',
                    $task
                );

                $this->notifyProjectMembers(
                    $entityManager,
                    $task,
                    $this->getUserDisplayName() . ' uploaded a file to task "' . $task->getTitle() . '".'
                );

                $entityManager->flush();

                $this->addFlash('success', 'File uploaded successfully.');
            }

            return $this->redirectToRoute('app_task_show', [
                'id' => $task->getId(),
            ]);
        }

        return $this->render('task/show.html.twig', [
            'task' => $task,
            'commentForm' => $commentForm->createView(),
            'attachmentForm' => $attachmentForm->createView(),
            'checklistForm' => $checklistForm->createView(),
        ]);
    }

    #[Route('/attachment/{id}/delete', name: 'app_attachment_delete')]
    public function deleteAttachment(TaskAttachment $attachment, EntityManagerInterface $entityManager): Response
    {
        $task = $attachment->getTask();

        if (!$task || !$this->canManageTask($task)) {
            $this->addFlash('danger', 'You cannot delete this attachment.');

            return $this->redirectToRoute('app_project_index');
        }

        if ($attachment->getUploadedBy() !== $this->getUser() && !$this->isWorkspaceManager($task->getProject())) {
            $this->addFlash('danger', 'You can only delete your own attachments.');

            return $this->redirectToRoute('app_task_show', [
                'id' => $task->getId(),
            ]);
        }

        $taskId = $task->getId();
        $originalName = $attachment->getOriginalName();

        $filePath = $this->getParameter('task_attachments_directory') . '/' . $attachment->getFileName();

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->createActivityLog(
            $entityManager,
            'file_deleted',
            $this->getUserDisplayName() . ' deleted file "' . $originalName . '" from task "' . $task->getTitle() . '"',
            $task
        );

        $this->notifyProjectMembers(
            $entityManager,
            $task,
            $this->getUserDisplayName() . ' deleted a file from task "' . $task->getTitle() . '".'
        );

        $entityManager->remove($attachment);
        $entityManager->flush();

        $this->addFlash('success', 'File deleted successfully.');

        return $this->redirectToRoute('app_task_show', [
            'id' => $taskId,
        ]);
    }

    #[Route('/{id}/status', name: 'app_task_update_status', methods: ['POST'])]
    public function updateStatus(Task $task, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->canManageTask($task)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'You can only move tasks assigned to you.',
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing status'], 400);
        }

        if (!in_array($data['status'], ['todo', 'in_progress', 'done'], true)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid status'], 400);
        }

        $oldStatus = $task->getStatus();
        $newStatus = $data['status'];

        if ($oldStatus === $newStatus) {
            return new JsonResponse([
                'success' => true,
                'status' => $task->getStatus(),
            ]);
        }

        $task->setStatus($newStatus);

        $this->createActivityLog(
            $entityManager,
            'task_status_changed',
            $this->getUserDisplayName() . ' moved task "' . $task->getTitle() . '" to ' . $newStatus,
            $task
        );

        $this->notifyWorkspaceManagers(
            $entityManager,
            $task,
            $this->getUserDisplayName() .
            ' moved task "' .
            $task->getTitle() .
            '" from ' .
            ucfirst(str_replace('_', ' ', $oldStatus)) .
            ' to ' .
            ucfirst(str_replace('_', ' ', $newStatus)) .
            '.',
            $this->generateUrl('app_task_show', ['id' => $task->getId()])
        );

        if ($newStatus === 'done') {
            $this->notifyTaskAssigneeCompleted($entityManager, $task);
            $this->notifyTaskCreatorCompleted($entityManager, $task);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $task->getStatus(),
        ]);
    }

    #[Route('/checklist/{id}/toggle', name: 'app_checklist_toggle')]
    public function toggleChecklist(TaskChecklistItem $item, EntityManagerInterface $entityManager): Response
    {
        $task = $item->getTask();

        if (!$this->canManageTask($task)) {
            $this->addFlash('danger', 'You can only update checklist items on tasks assigned to you.');

            return $this->redirectToRoute('app_task_show', [
                'id' => $task->getId(),
            ]);
        }

        $item->setIsDone(!$item->isDone());

        $this->createActivityLog(
            $entityManager,
            'checklist_toggled',
            $this->getUserDisplayName() . ' updated checklist item "' . $item->getContent() . '"',
            $task
        );

        $this->notifyProjectMembers(
            $entityManager,
            $task,
            $this->getUserDisplayName() . ' updated a checklist item on task "' . $task->getTitle() . '".'
        );

        $entityManager->flush();

        $this->addFlash('success', 'Checklist item updated.');

        return $this->redirectToRoute('app_task_show', [
            'id' => $task->getId(),
        ]);
    }

    #[Route('/checklist/{id}/delete', name: 'app_checklist_delete')]
    public function deleteChecklist(TaskChecklistItem $item, EntityManagerInterface $entityManager): Response
    {
        $task = $item->getTask();

        if (!$this->canManageTask($task)) {
            $this->addFlash('danger', 'You can only delete checklist items on tasks assigned to you.');

            return $this->redirectToRoute('app_task_show', [
                'id' => $task->getId(),
            ]);
        }

        $taskId = $task->getId();
        $content = $item->getContent();

        $this->createActivityLog(
            $entityManager,
            'checklist_deleted',
            $this->getUserDisplayName() . ' deleted checklist item "' . $content . '"',
            $task
        );

        $this->notifyProjectMembers(
            $entityManager,
            $task,
            $this->getUserDisplayName() . ' deleted a checklist item from task "' . $task->getTitle() . '".'
        );

        $entityManager->remove($item);
        $entityManager->flush();

        $this->addFlash('success', 'Checklist item deleted successfully.');

        return $this->redirectToRoute('app_task_show', [
            'id' => $taskId,
        ]);
    }

    #[Route('/comment/{id}/delete', name: 'app_comment_delete')]
    public function deleteComment(TaskComment $comment, EntityManagerInterface $entityManager): Response
    {
        if ($comment->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You cannot delete this comment.');
        }

        $task = $comment->getTask();

        if (!$task || !$this->isProjectMember($task->getProject())) {
            $this->addFlash('danger', 'You cannot access this task.');

            return $this->redirectToRoute('app_project_index');
        }

        $taskId = $task->getId();

        $this->createActivityLog(
            $entityManager,
            'comment_deleted',
            $this->getUserDisplayName() . ' deleted a comment from task "' . $task->getTitle() . '"',
            $task
        );

        $this->notifyProjectMembers(
            $entityManager,
            $task,
            $this->getUserDisplayName() . ' deleted a comment from task "' . $task->getTitle() . '".'
        );

        $entityManager->remove($comment);
        $entityManager->flush();

        $this->addFlash('success', 'Comment deleted successfully.');

        return $this->redirectToRoute('app_task_show', [
            'id' => $taskId,
        ]);
    }

    private function createActivityLog(
        EntityManagerInterface $entityManager,
        string $type,
        string $message,
        Task $task
    ): void {
        $log = new ActivityLog();

        $log->setUser($this->getUser());
        $log->setType($type);
        $log->setMessage($message);
        $log->setTask($task);
        $log->setProject($task->getProject());
        $log->setWorkspace($task->getProject()->getWorkspace());

        $entityManager->persist($log);
    }

    private function notifyWorkspaceManagers(
        EntityManagerInterface $entityManager,
        Task $task,
        string $message,
        ?string $link = null
    ): void {
        $currentUser = $this->getUser();

        foreach ($task->getProject()->getWorkspace()->getMembers() as $workspaceMember) {
            if (!in_array($workspaceMember->getRole(), ['owner', 'admin'], true)) {
                continue;
            }

            $user = $workspaceMember->getUser();

            if (!$user instanceof User) {
                continue;
            }

            if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
                continue;
            }

            $notification = new Notification();
            $notification->setUser($user);
            $notification->setMessage($message);
            $notification->setLink($link);

            $entityManager->persist($notification);
        }
    }

    private function notifyProjectMembers(
        EntityManagerInterface $entityManager,
        Task $task,
        string $message
    ): void {
        $currentUser = $this->getUser();

        foreach ($task->getProject()->getMembers() as $projectMember) {
            if (!$projectMember instanceof User) {
                continue;
            }

            if ($currentUser instanceof User && $projectMember->getId() === $currentUser->getId()) {
                continue;
            }

            $notification = new Notification();
            $notification->setUser($projectMember);
            $notification->setMessage($message);
            $notification->setLink(
                $this->generateUrl('app_task_show', [
                    'id' => $task->getId(),
                ])
            );

            $entityManager->persist($notification);
        }
    }

    private function notifyTaskAssigneeCompleted(EntityManagerInterface $entityManager, Task $task): void
    {
        $assignee = $task->getAssignee();
        $currentUser = $this->getUser();

        if (!$assignee instanceof User) {
            return;
        }

        if ($currentUser instanceof User && $assignee->getId() === $currentUser->getId()) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($assignee);
        $notification->setMessage(
            $this->getUserDisplayName() . ' completed your assigned task "' . $task->getTitle() . '".'
        );
        $notification->setLink(
            $this->generateUrl('app_task_show', [
                'id' => $task->getId(),
            ])
        );

        $entityManager->persist($notification);
    } 
    private function notifyTaskCreatorCompleted(EntityManagerInterface $entityManager, Task $task): void
{
    $creator = $task->getCreatedBy();
    $currentUser = $this->getUser();

    if (!$creator instanceof User) {
        return;
    }

    if ($currentUser instanceof User && $creator->getId() === $currentUser->getId()) {
        return;
    }

    if ($task->getAssignee() instanceof User && $creator->getId() === $task->getAssignee()->getId()) {
        return;
    }

    $notification = new Notification();
    $notification->setUser($creator);
    $notification->setMessage(
        $this->getUserDisplayName() . ' completed the task you created "' . $task->getTitle() . '".'
    );
    $notification->setLink(
        $this->generateUrl('app_task_show', [
            'id' => $task->getId(),
        ])
    );

    $entityManager->persist($notification);
}

    private function getUserDisplayName(): string
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return 'Someone';
        }

        return ucfirst($user->getFullName() ?? 'Someone');
    }

    private function isProjectMember(Project $project): bool
    {
        $user = $this->getUser();

        return $user instanceof User && $project->getMembers()->contains($user);
    }

    private function isWorkspaceManager(Project $project): bool
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return false;
        }

        foreach ($project->getWorkspace()->getMembers() as $workspaceMember) {
            if (
                $workspaceMember->getUser()->getId() === $user->getId()
                && in_array($workspaceMember->getRole(), ['owner', 'admin'], true)
            ) {
                return true;
            }
        }

        return false;
    }

    private function canManageTask(Task $task): bool
{
    return $this->canAccessTask($task);
}
    private function canAccessTask(Task $task): bool
{
    $user = $this->getUser();

    if (!$user instanceof User) {
        return false;
    }

    $project = $task->getProject();

    if (!$project || !$this->isProjectMember($project)) {
        return false;
    }

    if ($this->isWorkspaceManager($project)) {
        return true;
    }

    return $task->getAssignee() instanceof User
        && $task->getAssignee()->getId() === $user->getId();
}
}