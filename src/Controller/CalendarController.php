<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    public function index(TaskRepository $taskRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $tasks = $taskRepository->findBy([
            'assignee' => $user,
        ]);

        $calendarTasks = [];

        foreach ($tasks as $task) {
            if ($task->getDueDate()) {
                $calendarTasks[] = $task;
            }
        }

        return $this->render('calendar/index.html.twig', [
            'tasks' => $calendarTasks,
        ]);
    }
}