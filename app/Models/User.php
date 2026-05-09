<?php

declare(strict_types=1);

namespace App\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;

/**
 * Represents an application user stored in the `users` table.
 *
 * This entity uses Cycle ORM PHP 8 attributes for schema definition.
 * All timestamps are stored as immutable date-time objects.
 */
#[Entity(table: 'users')]
#[Index(columns: ['email'], unique: true)]
class User
{
    /**
     * Auto-incremented primary key.
     */
    #[Column(type: 'primary')]
    private int $id;

    /**
     * Full display name of the user.
     */
    #[Column(type: 'string', nullable: false)]
    private string $name;

    /**
     * Unique email address used for authentication.
     */
    #[Column(type: 'string', nullable: false)]
    private string $email;

    /**
     * Bcrypt / Argon2 hashed password — never store plain text.
     */
    #[Column(type: 'string', nullable: false)]
    private string $password;

    /**
     * Timestamp set once when the record is first created.
     */
    #[Column(type: 'datetime', nullable: false)]
    private \DateTimeImmutable $createdAt;

    /**
     * Timestamp updated every time the record is saved.
     */
    #[Column(type: 'datetime', nullable: false)]
    private \DateTimeImmutable $updatedAt;

    /**
     * Initialises timestamps to the current moment.
     * All other fields must be set via setters before persisting.
     */
    public function __construct()
    {
        $now             = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    /**
     * Returns the primary key of the user.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Returns the user's full name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the user's email address.
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Returns the hashed password string.
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Returns the creation timestamp.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Returns the last-updated timestamp.
     */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // -------------------------------------------------------------------------
    // Setters
    // -------------------------------------------------------------------------

    /**
     * Sets the user's full name.
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Sets the user's email address.
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Stores a pre-hashed password.
     *
     * Always hash passwords before calling this setter:
     * ```php
     * $user->setPassword(password_hash($plain, PASSWORD_BCRYPT));
     * ```
     */
    public function setPassword(string $hashedPassword): self
    {
        $this->password = $hashedPassword;

        return $this;
    }

    /**
     * Overrides the creation timestamp (useful for data imports).
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Updates the last-modified timestamp.
     * Call this method before persisting an updated entity.
     */
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Convenience method: refresh the updatedAt timestamp to now.
     */
    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
