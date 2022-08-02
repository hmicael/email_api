<?php

namespace App\Entity;

use App\Repository\DomainNameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=DomainNameRepository::class)
 * @UniqueEntity(fields="name", message="The domain name '{{ value }}' is already taken")
 * @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "domain_name_show",
 *          parameters = { "id" = "expr(object.getId())" }
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "domain_name_delete",
 *          parameters = { "id" = "expr(object.getId())" },
 *      )
 * )
 *
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "domain_name_edit",
 *          parameters = { "id" = "expr(object.getId())" },
 *      )
 * )
 */
class DomainName
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"list", "getDomainNames"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=20)
     * @Assert\NotBlank(message = "Name cannot be blank")
     * @Groups({"list", "getDomainNames"})
     */
    private $name;

    /**
     * @ORM\OneToMany(targetEntity=VirtualUser::class, mappedBy="domainName", orphanRemoval=true)
     * @Groups({"showDomainName"})
     */
    private $virtualUsers;

    /**
     * @ORM\OneToMany(targetEntity=VirtualAlias::class, mappedBy="domainName", orphanRemoval=true)
     * @Groups({"showDomainName"})
     */
    private $virtualAliases;

    /**
     * @ORM\OneToMany(targetEntity=VirtualForward::class, mappedBy="domainName", orphanRemoval=true)
     * @Groups({"showDomainName"})
     */
    private $virtualForwards;

    public function __construct()
    {
        $this->virtualUsers = new ArrayCollection();
        $this->virtualAliases = new ArrayCollection();
        $this->virtualForwards = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, VirtualUser>
     */
    public function getVirtualUsers(): Collection
    {
        return $this->virtualUsers;
    }

    public function addVirtualUser(VirtualUser $virtualUser): self
    {
        if (!$this->virtualUsers->contains($virtualUser)) {
            $this->virtualUsers[] = $virtualUser;
            $virtualUser->setDomainName($this);
        }

        return $this;
    }

    public function removeVirtualUser(VirtualUser $virtualUser): self
    {
        if ($this->virtualUsers->removeElement($virtualUser)) {
            // set the owning side to null (unless already changed)
            if ($virtualUser->getDomainName() === $this) {
                $virtualUser->setDomainName(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, VirtualAlias>
     */
    public function getVirtualAliases(): Collection
    {
        return $this->virtualAliases;
    }

    public function addVirtualAlias(VirtualAlias $virtualAlias): self
    {
        if (!$this->virtualAliases->contains($virtualAlias)) {
            $this->virtualAliases[] = $virtualAlias;
            $virtualAlias->setDomainName($this);
        }

        return $this;
    }

    public function removeVirtualAlias(VirtualAlias $virtualAlias): self
    {
        if ($this->virtualAliases->removeElement($virtualAlias)) {
            // set the owning side to null (unless already changed)
            if ($virtualAlias->getDomainName() === $this) {
                $virtualAlias->setDomainName(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, VirtualForward>
     */
    public function getVirtualForwards(): Collection
    {
        return $this->virtualForwards;
    }

    public function addVirtualForward(VirtualForward $virtualForward): self
    {
        if (!$this->virtualForwards->contains($virtualForward)) {
            $this->virtualForwards[] = $virtualForward;
            $virtualForward->setDomainName($this);
        }

        return $this;
    }

    public function removeVirtualForward(VirtualForward $virtualForward): self
    {
        if ($this->virtualForwards->removeElement($virtualForward)) {
            // set the owning side to null (unless already changed)
            if ($virtualForward->getDomainName() === $this) {
                $virtualForward->setDomainName(null);
            }
        }

        return $this;
    }
}
