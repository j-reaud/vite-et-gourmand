<?php

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[UniqueEntity(fields: ['slug'], message: 'Cette adresse de menu est déjà utilisée.')]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le titre du menu est obligatoire.')]
    #[Assert\Length(max: 150)]
    private ?string $title = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L’adresse du menu est obligatoire.')]
    #[Assert\Length(max: 180)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        message: 'L’adresse doit contenir uniquement des minuscules, chiffres et tirets.',
    )]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description du menu est obligatoire.')]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L’image du menu est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix du menu est obligatoire.')]
    #[Assert\Positive(message: 'Le prix doit être supérieur à zéro.')]
    private ?string $price = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le nombre minimum de personnes est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre minimum de personnes doit être supérieur à zéro.')]
    private ?int $minimumPeople = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Le thème du menu est obligatoire.')]
    #[Assert\Length(max: 80)]
    private ?string $theme = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le régime alimentaire est obligatoire.')]
    #[Assert\Length(max: 50)]
    private ?string $diet = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private int $stock = 0;

    #[ORM\Column]
    private bool $isPublished = false;

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

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getMinimumPeople(): ?int
    {
        return $this->minimumPeople;
    }

    public function setMinimumPeople(int $minimumPeople): static
    {
        $this->minimumPeople = $minimumPeople;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getDiet(): ?string
    {
        return $this->diet;
    }

    public function setDiet(string $diet): static
    {
        $this->diet = $diet;

        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }
}
