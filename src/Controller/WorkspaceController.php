<?php

namespace App\Controller;

use App\Entity\Workspace;
use App\Form\WorkspaceType;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/workspaces')]
final class WorkspaceController extends AbstractController
{
    #[Route('', name: 'app_workspace_index')]
    public function index(WorkspaceRepository $workspaceRepository): Response
    {
        $workspaces = $workspaceRepository->findBy([
            'owner' => $this->getUser(),
        ]);

        return $this->render('workspace/index.html.twig', [
            'workspaces' => $workspaces,
        ]);
    }

    #[Route('/new', name: 'app_workspace_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $workspace = new Workspace();

        $form = $this->createForm(WorkspaceType::class, $workspace);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();

            $workspace->setOwner($user);
            $workspace->setSlug($this->slugify($workspace->getName()));

            $entityManager->persist($workspace);
            $entityManager->flush();

            return $this->redirectToRoute('app_workspace_index');
        }

        return $this->render('workspace/new.html.twig', [
            'form' => $form,
        ]);
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
    #[Route('/{id}', name: 'app_workspace_show')]
public function show(Workspace $workspace): Response
{
    return $this->render('workspace/show.html.twig', [
        'workspace' => $workspace,
    ]);
}
}