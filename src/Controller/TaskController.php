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
    #[Route('/new/{id}', name: 'app_task_new')]
    public function new(Project $project, Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();

        $form = $this->createForm(TaskType::class, $task, [
             'project_members' => $project->getMembers()->toArray(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setProject($project);

            $entityManager->persist($task);

            $this->createActivityLog(
                $entityManager,
                'task_created',
                $this->getUserDisplayName() . ' created task "' . $task->getTitle() . '"',
                $task
            );

            if ($task->getAssignee()) {
                $notification = new Notification();
                $notification->setUser($task->getAssignee());
                $notification->setMessage(
                    $this->getUserDisplayName() . ' assigned you to task "' . $task->getTitle() . '"'
                );

                $entityManager->persist($notification);
            }

            $entityManager->flush();

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
        $task->setStatus('in_progress');

        $this->createActivityLog(
            $entityManager,
            'task_started',
            $this->getUserDisplayName() . ' started task "' . $task->getTitle() . '"',
            $task
        );

        $entityManager->flush();

        $this->addFlash('success', 'Task moved to In Progress.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $task->getProject()->getId(),
        ]);
    }

    #[Route('/{id}/done', name: 'app_task_done')]
    public function done(Task $task, EntityManagerInterface $entityManager): Response
    {
        $task->setStatus('done');

        $this->createActivityLog(
            $entityManager,
            'task_completed',
            $this->getUserDisplayName() . ' completed task "' . $task->getTitle() . '"',
            $task
        );

        $entityManager->flush();

        $this->addFlash('success', 'Task marked as done.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $task->getProject()->getId(),
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

    $form = $this->createForm(TaskType::class, $task, [
        'project_members' => $project->getMembers()->toArray(),
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $task->setUpdatedAt(new \DateTimeImmutable());

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
        $projectId = $task->getProject()->getId();

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
        $comment = new TaskComment();
        $commentForm = $this->createForm(TaskCommentType::class, $comment);
        $commentForm->handleRequest($request);

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

            $entityManager->flush();

            $this->addFlash('success', 'Comment added successfully.');

            return $this->redirectToRoute('app_task_show', [
                'id' => $task->getId(),
            ]);
        }

        $attachment = new TaskAttachment();
        $attachmentForm = $this->createForm(TaskAttachmentType::class, $attachment);
        $attachmentForm->handleRequest($request);

        $checklistItem = new TaskChecklistItem();
        $checklistForm = $this->createForm(TaskChecklistItemType::class, $checklistItem);
        $checklistForm->handleRequest($request);

        if ($checklistForm->isSubmitted() && $checklistForm->isValid()) {
            $checklistItem->setTask($task);

            $entityManager->persist($checklistItem);

            $this->createActivityLog(
                $entityManager,
                'checklist_added',
                $this->getUserDisplayName() . ' added checklist item "' . $checklistItem->getContent() . '" to task "' . $task->getTitle() . '"',
                $task
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
    public function deleteAttachment(
        TaskAttachment $attachment,
        EntityManagerInterface $entityManager
    ): Response {
        if ($attachment->getUploadedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You cannot delete this attachment.');
        }

        $task = $attachment->getTask();
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

        $entityManager->remove($attachment);
        $entityManager->flush();

        $this->addFlash('success', 'File deleted successfully.');

        return $this->redirectToRoute('app_task_show', [
            'id' => $taskId,
        ]);
    }

    #[Route('/{id}/status', name: 'app_task_update_status', methods: ['POST'])]
    public function updateStatus(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing status'], 400);
        }

        if (!in_array($data['status'], ['todo', 'in_progress', 'done'], true)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid status'], 400);
        }

        $task->setStatus($data['status']);

        $this->createActivityLog(
            $entityManager,
            'task_status_changed',
            $this->getUserDisplayName() . ' moved task "' . $task->getTitle() . '" to ' . $data['status'],
            $task
        );

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $task->getStatus(),
        ]);
    }

    #[Route('/checklist/{id}/toggle', name: 'app_checklist_toggle')]
    public function toggleChecklist(
        TaskChecklistItem $item,
        EntityManagerInterface $entityManager
    ): Response {
        $item->setIsDone(!$item->isDone());

        $this->createActivityLog(
            $entityManager,
            'checklist_toggled',
            $this->getUserDisplayName() . ' updated checklist item "' . $item->getContent() . '"',
            $item->getTask()
        );

        $entityManager->flush();

        $this->addFlash('success', 'Checklist item updated.');

        return $this->redirectToRoute('app_task_show', [
            'id' => $item->getTask()->getId(),
        ]);
    }

    #[Route('/checklist/{id}/delete', name: 'app_checklist_delete')]
    public function deleteChecklist(
        TaskChecklistItem $item,
        EntityManagerInterface $entityManager
    ): Response {
        $task = $item->getTask();
        $taskId = $task->getId();
        $content = $item->getContent();

        $this->createActivityLog(
            $entityManager,
            'checklist_deleted',
            $this->getUserDisplayName() . ' deleted checklist item "' . $content . '"',
            $task
        );

        $entityManager->remove($item);
        $entityManager->flush();

        $this->addFlash('success', 'Checklist item deleted successfully.');

        return $this->redirectToRoute('app_task_show', [
            'id' => $taskId,
        ]);
    }

    #[Route('/comment/{id}/delete', name: 'app_comment_delete')]
    public function deleteComment(
        TaskComment $comment,
        EntityManagerInterface $entityManager
    ): Response {
        if ($comment->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You cannot delete this comment.');
        }

        $task = $comment->getTask();
        $taskId = $task->getId();

        $this->createActivityLog(
            $entityManager,
            'comment_deleted',
            $this->getUserDisplayName() . ' deleted a comment from task "' . $task->getTitle() . '"',
            $task
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

    private function getUserDisplayName(): string
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return 'Someone';
        }

        return ucfirst($user->getFullName() ?? 'Someone');
    }
}