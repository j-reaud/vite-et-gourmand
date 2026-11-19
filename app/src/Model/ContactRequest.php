<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactRequest
{
    #[Assert\NotBlank(message: 'Veuillez saisir votre prénom.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.',
    )]
    private string $firstName = '';

    #[Assert\NotBlank(message: 'Veuillez saisir votre nom.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
    )]
    private string $lastName = '';

    #[Assert\NotBlank(message: 'Veuillez saisir votre adresse e-mail.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.')]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[Assert\NotBlank(message: 'Veuillez saisir votre numéro de téléphone.')]
    #[Assert\Regex(
        pattern: '/^\+?[0-9\s().-]{8,20}$/',
        message: 'Veuillez saisir un numéro de téléphone valide.',
    )]
    private string $phone = '';

    #[Assert\NotBlank(message: 'Veuillez saisir le sujet de votre demande.')]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le sujet ne peut pas dépasser {{ limit }} caractères.',
    )]
    private string $subject = '';

    #[Assert\NotBlank(message: 'Veuillez saisir votre message.')]
    #[Assert\Length(
        max: 240,
        maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.',
    )]
    private string $message = '';

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = trim($phone);

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = trim($message);

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = trim($subject);

        return $this;
    }
}
