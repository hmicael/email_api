<?php

namespace App\Entity;

use App\Repository\VirtualAliasRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use JMS\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=VirtualAliasRepository::class)
 * @UniqueEntity(fields="source", message="The source '{{ value }}' is already taken")
 * @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "virtual_alias_show",
 *          parameters = { "id" = "expr(object.getId())" }
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "virtual_alias_delete",
 *          parameters = { "id" = "expr(object.getId())" },
 *      )
 * )
 *
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "virtual_alias_edit",
 *          parameters = { "id" = "expr(object.getId())" },
 *      )
 * )
 */
class VirtualAlias
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"list", "getAliases"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=30)
     * @Assert\Email()
     * @Groups({"list", "getAliases"})
     */
    private $source;

    /**
     * @ORM\ManyToOne(targetEntity=DomainName::class, inversedBy="virtualAliases")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"getDomainNames"})
     */
    private $domainName;

    /**
     * @ORM\ManyToMany(targetEntity=VirtualUser::class, inversedBy="virtualAliases")
     * @Groups({"getUsers"})
     */
    private $virtualUsers;

    public function __construct()
    {
        $this->virtualUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getDomainName(): ?DomainName
    {
        return $this->domainName;
    }

    public function setDomainName(?DomainName $domainName): self
    {
        $this->domainName = $domainName;

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
        }

        return $this;
    }

    public function removeVirtualUser(VirtualUser $virtualUser): self
    {
        $this->virtualUsers->removeElement($virtualUser);

        return $this;
    }
}
