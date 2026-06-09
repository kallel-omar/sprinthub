<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\TaskComment;
use App\Entity\TaskAttachment;
use App\Entity\WorkspaceMember;
use App\Entity\Notification;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'assignee')]
    private Collection $assignedTasks;
    /**
 * @var Collection<int, TaskComment>
 */
#[ORM\OneToMany(targetEntity: TaskComment::class, mappedBy: 'user')]
private Collection $comments;

/**
 * @var Collection<int, TaskAttachment>
 */
#[ORM\OneToMany(targetEntity: TaskAttachment::class, mappedBy: 'uploadedBy')]
private Collection $attachments;

/**
 * @var Collection<int, WorkspaceMember>
 */
#[ORM\OneToMany(targetEntity: WorkspaceMember::class, mappedBy: 'user')]
private Collection $workspaceMemberships;

/**
 * @var Collection<int, Workspace>
 */
#[ORM\OneToMany(targetEntity: Workspace::class, mappedBy: 'owner')]
private Collection $workspaces;

/**
 * @var Collection<int, Notification>
 */
#[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
private Collection $notifications;

/**
 * @var Collection<int, Project>
 */
#[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'members')]
private Collection $memberProjects;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->assignedTasks = new ArrayCollection();
         $this->comments = new ArrayCollection();
         $this->attachments = new ArrayCollection();
         $this->workspaceMemberships = new ArrayCollection();
         $this->workspaces = new ArrayCollection();
         $this->notifications = new ArrayCollection();
         $this->memberProjects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
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

    /**
     * @return Collection<int, Task>
     */
    public function getAssignedTasks(): Collection
    {
        return $this->assignedTasks;
    }

    public function addAssignedTask(Task $assignedTask): static
    {
        if (!$this->assignedTasks->contains($assignedTask)) {
            $this->assignedTasks->add($assignedTask);
            $assignedTask->setAssignee($this);
        }

        return $this;
    }

    public function removeAssignedTask(Task $assignedTask): static
    {
        if ($this->assignedTasks->removeElement($assignedTask)) {
            // set the owning side to null (unless already changed)
            if ($assignedTask->getAssignee() === $this) {
                $assignedTask->setAssignee(null);
            }
        }

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
        $comment->setUser($this);
    }

    return $this;
}

public function removeComment(TaskComment $comment): static
{
    if ($this->comments->removeElement($comment)) {
        if ($comment->getUser() === $this) {
            $comment->setUser(null);
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
        $attachment->setUploadedBy($this);
    }

    return $this;
}

public function removeAttachment(TaskAttachment $attachment): static
{
    if ($this->attachments->removeElement($attachment)) {
        // set the owning side to null (unless already changed)
        if ($attachment->getUploadedBy() === $this) {
            $attachment->setUploadedBy(null);
        }
    }

    return $this;
}

/**
 * @return Collection<int, WorkspaceMember>
 */
public function getWorkspaceMemberships(): Collection
{
    return $this->workspaceMemberships;
}

public function addWorkspaceMembership(WorkspaceMember $workspaceMembership): static
{
    if (!$this->workspaceMemberships->contains($workspaceMembership)) {
        $this->workspaceMemberships->add($workspaceMembership);
        $workspaceMembership->setUser($this);
    }

    return $this;
}

public function removeWorkspaceMembership(WorkspaceMember $workspaceMembership): static
{
    if ($this->workspaceMemberships->removeElement($workspaceMembership)) {
        // set the owning side to null (unless already changed)
        if ($workspaceMembership->getUser() === $this) {
            $workspaceMembership->setUser(null);
        }
    }

    return $this;
}

/**
 * @return Collection<int, Notification>
 */
public function getNotifications(): Collection
{
    return $this->notifications;
}

public function addNotification(Notification $notification): static
{
    if (!$this->notifications->contains($notification)) {
        $this->notifications->add($notification);
        $notification->setUser($this);
    }

    return $this;
}

public function removeNotification(Notification $notification): static
{
    if ($this->notifications->removeElement($notification)) {
        // set the owning side to null (unless already changed)
        if ($notification->getUser() === $this) {
            $notification->setUser(null);
        }
    }

    return $this;
}

/**
 * @return Collection<int, Workspace>
 */
public function getWorkspaces(): Collection
{
    return $this->workspaces;
}

public function addWorkspace(Workspace $workspace): static
{
    if (!$this->workspaces->contains($workspace)) {
        $this->workspaces->add($workspace);
        $workspace->setOwner($this);
    }

    return $this;
}

public function removeWorkspace(Workspace $workspace): static
{
    if ($this->workspaces->removeElement($workspace)) {
        if ($workspace->getOwner() === $this) {
            $workspace->setOwner(null);
        }
    }

    return $this;
}

/**
 * @return Collection<int, Project>
 */
public function getMemberProjects(): Collection
{
    return $this->memberProjects;
}

public function addMemberProject(Project $memberProject): static
{
    if (!$this->memberProjects->contains($memberProject)) {
        $this->memberProjects->add($memberProject);
        $memberProject->addMember($this);
    }

    return $this;
}

public function removeMemberProject(Project $memberProject): static
{
    if ($this->memberProjects->removeElement($memberProject)) {
        $memberProject->removeMember($this);
    }

    return $this;
}

}