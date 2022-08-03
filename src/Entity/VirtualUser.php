<?php

namespace App\Entity;

use App\Repository\VirtualUserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=VirtualUserRepository::class)
 * @UniqueEntity(fields="email", message="The email '{{ value }}' is already taken")
 * @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "virtual_user_show",
 *          parameters = { "id" = "expr(object.getId())" }
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "virtual_user_delete",
 *          parameters = { "id" = "expr(object.getId())" },
 *      )
 * )
 *
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "virtual_user_edit",
 *          parameters = { "id" = "expr(object.getId())" },
 *      )
 * )
 */
class VirtualUser
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"list", "getVirtualUsers"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=20)
     * @Assert\NotBlank(message = "Name cannot be blank")
     * @Assert\Type("string")
     * @Groups({"list", "getVirtualUsers"})
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     * @Assert\Type("string")
     * @Groups({"list", "getVirtualUsers"})
     */
    private $firstname;

    /**
     * @ORM\Column(type="string", length=30)
     * @Assert\NotBlank(message = "Email cannot be blank")
     * @Assert\Email()
     * @Groups({"list", "getVirtualUsers"})
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\Type("string")
     * @Groups({"list", "getVirtualUsers"})
     */
    private $maildir;

    /**
     * @ORM\ManyToOne(targetEntity=DomainName::class, inversedBy="virtualUsers")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"getDomainNames"})
     */
    private $domainName;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank(message = "Password cannot be blank")
     * @Assert\Length(
     *     min = 8,
     *     minMessage = "Password must be {{ limit }} characters"
     * )
     * @Assert\Regex(
     *     pattern="/^((?=.+[a-zA-Z])(?=.+[0-9])|(?=.+[,<>\\\+\?\)\(\-\/;\.!@#\$%\^&\*]))(?=.{8,})/",
     *     message="Password must contain atleast one uppercase and lowercase letters, one number, and one special character"
     * )
     */
    private $password;

    /**
     * @ORM\ManyToMany(targetEntity=VirtualAlias::class, mappedBy="virtualUsers")
     * @Groups({"getAliases"})
     */
    private $virtualAliases;

    /**
     * @ORM\ManyToMany(targetEntity=VirtualForward::class, mappedBy="virtualUsers")
     * @Groups({"getForwards"})
     */
    private $virtualForwards;

    public function __construct()
    {
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

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getMaildir(): ?string
    {
        return $this->maildir;
    }

    public function setMaildir(string $maildir): self
    {
        $this->maildir = $maildir;

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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

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
            $virtualAlias->addVirtualUser($this);
        }

        return $this;
    }

    public function removeVirtualAlias(VirtualAlias $virtualAlias): self
    {
        if ($this->virtualAliases->removeElement($virtualAlias)) {
            $virtualAlias->removeVirtualUser($this);
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
            $virtualForward->addVirtualUser($this);
        }

        return $this;
    }

    public function removeVirtualForward(VirtualForward $virtualForward): self
    {
        if ($this->virtualForwards->removeElement($virtualForward)) {
            $virtualForward->removeVirtualUser($this);
        }

        return $this;
    }
}
