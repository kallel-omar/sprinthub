<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Workspace;
use App\Form\ProjectType;
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
    #[Route('/{id}', name: 'app_project_show')]
public function show(Project $project): Response
{
    return $this->render('project/show.html.twig', [
        'project' => $project,
    ]);
}
}