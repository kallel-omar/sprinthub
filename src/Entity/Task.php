<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $priority = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\ManyToOne(inversedBy: 'assignedTasks')]
    private ?User $assignee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, TaskComment>
     */
    #[ORM\OneToMany(targetEntity: TaskComment::class, mappedBy: 'task', orphanRemoval: true)]
    private Collection $comments;

    /**
     * @var Collection<int, TaskAttachment>
     */
    #[ORM\OneToMany(targetEntity: TaskAttachment::class, mappedBy: 'task', orphanRemoval: true)]
    private Collection $attachments;

    /**
     * @var Collection<int, ActivityLog>
     */
    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'task')]
    private Collection $activityLogs;

    /**
     * @var Collection<int, Label>
     */
    #[ORM\ManyToMany(targetEntity: Label::class, inversedBy: 'tasks')]
    private Collection $labels;

    /**
     * @var Collection<int, TaskChecklistItem>
     */
    #[ORM\OneToMany(
        targetEntity: TaskChecklistItem::class,
        mappedBy: 'task',
        orphanRemoval: true
    )]
    private Collection $checklistItems;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'todo';
        $this->comments = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->checklistItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): static
    {
        $this->assignee = $assignee;
        return $this;
    }

    /**
     * @return Collection<int, TaskComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(TaskComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTask($this);
        }

        return $this;
    }

    public function removeComment(TaskComment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getTask() === $this) {
                $comment->setTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(TaskAttachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setTask($this);
        }

        return $this;
    }

    public function removeAttachment(TaskAttachment $attachment): static
    {
        if ($this->attachments->removeElement($attachment)) {
            if ($attachment->getTask() === $this) {
                $attachment->setTask(null);
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
            $activityLog->setTask($this);
        }

        return $this;
    }

    public function removeActivityLog(ActivityLog $activityLog): static
    {
        if ($this->activityLogs->removeElement($activityLog)) {
            if ($activityLog->getTask() === $this) {
                $activityLog->setTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Label>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(Label $label): static
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
            $label->addTask($this);
        }

        return $this;
    }

    public function removeLabel(Label $label): static
    {
        if ($this->labels->removeElement($label)) {
            $label->removeTask($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskChecklistItem>
     */
    public function getChecklistItems(): Collection
    {
        return $this->checklistItems;
    }

    public function addChecklistItem(TaskChecklistItem $checklistItem): static
    {
        if (!$this->checklistItems->contains($checklistItem)) {
            $this->checklistItems->add($checklistItem);
            $checklistItem->setTask($this);
        }

        return $this;
    }

    public function removeChecklistItem(TaskChecklistItem $checklistItem): static
    {
        if ($this->checklistItems->removeElement($checklistItem)) {
            if ($checklistItem->getTask() === $this) {
                $checklistItem->setTask(null);
            }
        }

        return $this;
    }
    public function getChecklistCompletedCount(): int
{
    $count = 0;

    foreach ($this->checklistItems as $item) {
        if ($item->isDone()) {
            $count++;
        }
    }

    return $count;
}

public function getChecklistTotalCount(): int
{
    return $this->checklistItems->count();
}

public function getChecklistProgress(): float
{
    $total = $this->getChecklistTotalCount();

    if ($total === 0) {
        return 0;
    }

    return round(($this->getChecklistCompletedCount() / $total) * 100, 1);
}

public function getCreatedBy(): ?User
{
    return $this->createdBy;
}

public function setCreatedBy(?User $createdBy): static
{
    $this->createdBy = $createdBy;

    return $this;
}
}