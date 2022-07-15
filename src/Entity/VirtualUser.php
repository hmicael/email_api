<?php

namespace App\Entity;

use App\Repository\VirtualUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;


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
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=20)
     * @Assert\NotBlank(message = "Name cannot be blank")
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    private $firstname;

    /**
     * @ORM\Column(type="string", length=20)
     * @Assert\NotBlank(message = "Email cannot be blank")
     * @Assert\Email()
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $maildir;

    /**
     * @ORM\ManyToOne(targetEntity=DomainName::class, inversedBy="virtualUsers")
     * @ORM\JoinColumn(nullable=false)
     */
    private $domainName;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank(message = "Password cannot be blank")
     * @Assert\Length(
     *     min = 12,
     *     minMessage = "Password must be {{ limit }} characters"
     * )
     * @Assert\Regex(
     *     pattern="/^((?=.+[a-zA-Z])(?=.+[0-9])|(?=.+[,<>\\\+\?\)\(\-\/;\.!@#\$%\^&\*]))(?=.{12,})/",
     *     message="Password must contain atleast one uppercase and lowercase letters, one number, and one special character"
     * )
     * @Serializer\Exclude
     */
    private $password;

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
}
