<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Workspace $workspace = null;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(targetEntity: Task::class,  cascade: ['remove'], mappedBy: 'project')]
    private Collection $tasks;

    /**
     * @var Collection<int, ActivityLog>
     */
    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'project')]
    private Collection $activityLogs;


    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'memberProjects')]
    #[ORM\JoinTable(name: 'project_members')]
    private Collection $members;

    #[ORM\Column(length: 255)]
    private ?string $approvalStatus = 'pending';

    /**
     * @var Collection<int, ProjectJoinRequest>
     */
    #[ORM\OneToMany(targetEntity: ProjectJoinRequest::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $projectJoinRequests;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->tasks = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->members = new ArrayCollection();
        $this->projectJoinRequests = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): static
    {
        $this->workspace = $workspace;

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setProject($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            // set the owning side to null (unless already changed)
            if ($task->getProject() === $this) {
                $task->setProject(null);
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
            $activityLog->setProject($this);
        }

        return $this;
    }

    public function removeActivityLog(ActivityLog $activityLog): static
    {
        if ($this->activityLogs->removeElement($activityLog)) {
            // set the owning side to null (unless already changed)
            if ($activityLog->getProject() === $this) {
                $activityLog->setProject(null);
            }
        }

        return $this;
    }



    /**
 * @return Collection<int, User>
 */
        public function getMembers(): Collection
        {
            return $this->members;
        }

        public function addMember(User $member): static
        {
            if (!$this->members->contains($member)) {
                $this->members->add($member);
            }

            return $this;
        }

        public function removeMember(User $member): static
        {
            $this->members->removeElement($member);

            return $this;
        }

        public function getApprovalStatus(): ?string
        {
            return $this->approvalStatus;
        }

        public function setApprovalStatus(string $approvalStatus): static
        {
            $this->approvalStatus = $approvalStatus;

            return $this;
        }

        /**
         * @return Collection<int, ProjectJoinRequest>
         */
        public function getProjectJoinRequests(): Collection
        {
            return $this->projectJoinRequests;
        }

        public function addProjectJoinRequest(ProjectJoinRequest $projectJoinRequest): static
        {
            if (!$this->projectJoinRequests->contains($projectJoinRequest)) {
                $this->projectJoinRequests->add($projectJoinRequest);
                $projectJoinRequest->setProject($this);
            }

            return $this;
        }

        public function removeProjectJoinRequest(ProjectJoinRequest $projectJoinRequest): static
        {
            if ($this->projectJoinRequests->removeElement($projectJoinRequest)) {
                // set the owning side to null (unless already changed)
                if ($projectJoinRequest->getProject() === $this) {
                    $projectJoinRequest->setProject(null);
                }
            }

            return $this;
        }
}
