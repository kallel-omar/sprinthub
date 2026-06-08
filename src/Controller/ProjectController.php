<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Workspace;
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
        $project = new Project();

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $project->setWorkspace($workspace);

            $project->setSlug(
                strtolower(
                    preg_replace('/[^a-z0-9]+/', '-', $project->getName())
                )
            );

            $entityManager->persist($project);
            $entityManager->flush();

            return $this->redirectToRoute('app_workspace_index');
        }

        return $this->render('project/new.html.twig', [
            'form' => $form,
            'workspace' => $workspace,
        ]);
    }
   
#[Route('/{id}/stats', name: 'app_project_stats')]
public function stats(Project $project): Response
{
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
    LabelRepository $labelRepository
): Response {

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
    ]);
}
#[Route('/{id}/delete', name: 'app_project_delete')]
public function delete(
    Project $project,
    EntityManagerInterface $entityManager
): Response {
    $workspaceId = $project->getWorkspace()->getId();

    $entityManager->remove($project);
    $entityManager->flush();

    return $this->redirectToRoute('app_workspace_show', [
        'id' => $workspaceId,
    ]);
}
}