<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskAttachment;
use App\Entity\TaskComment;
use App\Form\TaskAttachmentType;
use App\Form\TaskCommentType;
use App\Form\TaskType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Entity\ActivityLog;

#[Route('/tasks')]
final class TaskController extends AbstractController
{
    #[Route('/new/{id}', name: 'app_task_new')]
    public function new(Project $project, Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setProject($project);

            $entityManager->persist($task);
            $entityManager->flush();

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
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
        $entityManager->flush();

        return $this->redirectToRoute('app_project_show', ['id' => $task->getProject()->getId()]);
    }

    #[Route('/{id}/done', name: 'app_task_done')]
    public function done(Task $task, EntityManagerInterface $entityManager): Response
    {
        $task->setStatus('done');
        $entityManager->flush();

        return $this->redirectToRoute('app_project_show', ['id' => $task->getProject()->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_task_edit')]
    public function edit(Task $task, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            return $this->redirectToRoute('app_project_show', ['id' => $task->getProject()->getId()]);
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

        $entityManager->remove($task);
        $entityManager->flush();

        return $this->redirectToRoute('app_project_show', ['id' => $projectId]);
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

$log = new ActivityLog();
$log->setUser($this->getUser());
$log->setTask($task);
$log->setProject($task->getProject());
$log->setWorkspace($task->getProject()->getWorkspace());

$log->setType('comment');

$log->setMessage(
    $this->getUser()->getFullName()
    . ' commented on task "'
    . $task->getTitle()
    . '"'
);

$entityManager->persist($log);

$entityManager->flush();

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }

        $attachment = new TaskAttachment();
        $attachmentForm = $this->createForm(TaskAttachmentType::class, $attachment);
        $attachmentForm->handleRequest($request);

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
    $entityManager->flush();
}

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }

        return $this->render('task/show.html.twig', [
            'task' => $task,
            'commentForm' => $commentForm->createView(),
            'attachmentForm' => $attachmentForm->createView(),
        ]);
    }
    #[Route('/attachment/{id}/delete', name: 'app_attachment_delete')]
public function deleteAttachment(
    TaskAttachment $attachment,
    EntityManagerInterface $entityManager
): Response {

    $taskId = $attachment->getTask()->getId();

    $filePath = $this->getParameter('task_attachments_directory')
        . '/' . $attachment->getFileName();

    if (file_exists($filePath)) {
        unlink($filePath);
    }

    $entityManager->remove($attachment);
    $entityManager->flush();

    return $this->redirectToRoute('app_task_show', [
        'id' => $taskId,
    ]);
}
}