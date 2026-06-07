<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Form\TaskType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tasks')]
final class TaskController extends AbstractController
{
    #[Route('/new/{id}', name: 'app_task_new')]
    public function new(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {

        $task = new Task();

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $task->setProject($project);

            $entityManager->persist($task);
            $entityManager->flush();

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId()
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

    $entityManager->flush();

    return $this->redirectToRoute('app_project_show', [
        'id' => $task->getProject()->getId(),
    ]);
}

#[Route('/{id}/done', name: 'app_task_done')]
public function done(Task $task, EntityManagerInterface $entityManager): Response
{
    $task->setStatus('done');

    $entityManager->flush();

    return $this->redirectToRoute('app_project_show', [
        'id' => $task->getProject()->getId(),
    ]);
}
}