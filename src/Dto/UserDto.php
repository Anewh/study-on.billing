<?php

namespace App\Dto;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

class UserDto
{
    #[Serializer\Type('string')]
    #[Assert\Email(message: 'Wrong email {{ value }} .')]
    #[Assert\NotBlank(message: 'Email can not be null')]
    private ?string $username = null;

    #[Serializer\Type('string')]
    #[Assert\Length(min: 6, minMessage: 'Password must contains at least {{ limit }} symbols.')]
    private ?string $password = null;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
}