<?php

namespace App\Entity;

use App\Repository\WorkspaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkspaceRepository::class)]
class Workspace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'workspaces')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(
    targetEntity: Project::class,
    mappedBy: 'workspace',
    cascade: ['remove'],
    orphanRemoval: true
)]
private Collection $projects;

    /**
     * @var Collection<int, WorkspaceMember>
     */
    #[ORM\OneToMany(
    targetEntity: WorkspaceMember::class,
    mappedBy: 'workspace',
    cascade: ['remove'],
    orphanRemoval: true
)]
private Collection $members;

    /**
     * @var Collection<int, ActivityLog>
     */
    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'workspace')]
    private Collection $activityLogs;

    /**
     * @var Collection<int, WorkspaceInvitation>
     */
    #[ORM\OneToMany(targetEntity: WorkspaceInvitation::class, mappedBy: 'workspace', orphanRemoval: true)]
    private Collection $invitations;
    
    public function __construct()
{
    $this->createdAt = new \DateTimeImmutable();
    $this->projects = new ArrayCollection();
    $this->members = new ArrayCollection();
    $this->activityLogs = new ArrayCollection();
    $this->invitations = new ArrayCollection();
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setWorkspace($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getWorkspace() === $this) {
                $project->setWorkspace(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WorkspaceMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(WorkspaceMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setWorkspace($this);
        }

        return $this;
    }

    public function removeMember(WorkspaceMember $member): static
    {
        if ($this->members->removeElement($member)) {
            // set the owning side to null (unless already changed)
            if ($member->getWorkspace() === $this) {
                $member->setWorkspace(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function getActivityLogs(): Collection
    {
        return $this->activityLogs;
    }

    public function addActivityLog(ActivityLog $activityLog): static
    {
        if (!$this->activityLogs->contains($activityLog)) {
            $this->activityLogs->add($activityLog);
            $activityLog->setWorkspace($this);
        }

        return $this;
    }

    public function removeActivityLog(ActivityLog $activityLog): static
    {
        if ($this->activityLogs->removeElement($activityLog)) {
            // set the owning side to null (unless already changed)
            if ($activityLog->getWorkspace() === $this) {
                $activityLog->setWorkspace(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WorkspaceInvitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(WorkspaceInvitation $invitation): static
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setWorkspace($this);
        }

        return $this;
    }

    public function removeInvitation(WorkspaceInvitation $invitation): static
    {
        if ($this->invitations->removeElement($invitation)) {
            // set the owning side to null (unless already changed)
            if ($invitation->getWorkspace() === $this) {
                $invitation->setWorkspace(null);
            }
        }

        return $this;
    }
}
