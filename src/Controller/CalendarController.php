<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    public function index(TaskRepository $taskRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash('warning', 'Please login first.');

            return $this->redirectToRoute('app_login');
        }

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