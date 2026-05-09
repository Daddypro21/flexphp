<?php

declare(strict_types=1);

namespace FlexPHP\Database;

use Cycle\ORM\ORM;
use Cycle\ORM\Select;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\EntityManager;

/**
 * BaseRepository provides a reusable data-access layer for Cycle ORM entities.
 *
 * Concrete repositories must extend this class and set the $entityClass
 * property so that all query helpers know which entity to operate on.
 *
 * @template T of object
 */
abstract class BaseRepository
{
    /**
     * Fully-qualified class name of the managed entity.
     * Must be overridden in every concrete repository.
     *
     * @var class-string<T>
     */
    protected string $entityClass;

    /** @var EntityManagerInterface Cycle entity manager for write operations. */
    private EntityManagerInterface $entityManager;

    /**
     * @param ORM $orm Cycle ORM instance injected via the DI container.
     */
    public function __construct(protected ORM $orm)
    {
        $this->entityManager = new EntityManager($orm);
    }

    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Finds a single entity by its primary key.
     *
     * @param int|string $id Primary key value.
     * @return T|null
     */
    public function findById(int|string $id): ?object
    {
        return $this->select()->wherePK($id)->fetchOne();
    }

    /**
     * Returns all entities in the table (no filtering).
     *
     * @return list<T>
     */
    public function findAll(): array
    {
        return $this->select()->fetchAll();
    }

    /**
     * Finds entities matching a set of column-value conditions.
     *
     * Each entry in $criteria is treated as an equality condition.
     * Example: ['status' => 'active', 'role' => 'admin']
     *
     * @param array<string, mixed> $criteria Column → value equality conditions.
     * @return list<T>
     */
    public function findBy(array $criteria): array
    {
        $select = $this->select();

        foreach ($criteria as $field => $value) {
            $select = $select->where($field, $value);
        }

        return $select->fetchAll();
    }

    /**
     * Finds the first entity matching the given criteria, or null.
     *
     * @param array<string, mixed> $criteria Column → value equality conditions.
     * @return T|null
     */
    public function findOneBy(array $criteria): ?object
    {
        $select = $this->select();

        foreach ($criteria as $field => $value) {
            $select = $select->where($field, $value);
        }

        return $select->fetchOne();
    }

    /**
     * Counts entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column → value equality conditions.
     */
    public function count(array $criteria = []): int
    {
        $select = $this->select();

        foreach ($criteria as $field => $value) {
            $select = $select->where($field, $value);
        }

        return $select->count();
    }

    /**
     * Returns a paginated result set.
     *
     * @param int                  $page     Current page number (1-based).
     * @param int                  $perPage  Number of records per page.
     * @param array<string, mixed> $criteria Optional equality filters.
     *
     * @return array{
     *   data: list<T>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   last_page: int
     * }
     */
    public function paginate(int $page, int $perPage = 15, array $criteria = []): array
    {
        $select = $this->select();

        foreach ($criteria as $field => $value) {
            $select = $select->where($field, $value);
        }

        $total    = $select->count();
        $lastPage = (int) max(1, ceil($total / $perPage));
        $offset   = ($page - 1) * $perPage;

        $data = (clone $select)
            ->limit($perPage)
            ->offset($offset)
            ->fetchAll();

        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
        ];
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Persists a new or existing entity and flushes the entity manager.
     *
     * @param T $entity The entity to persist.
     */
    public function save(object $entity): void
    {
        $this->entityManager->persist($entity)->run();
    }

    /**
     * Deletes an entity and flushes the entity manager.
     *
     * @param T $entity The entity to delete.
     */
    public function delete(object $entity): void
    {
        $this->entityManager->delete($entity)->run();
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Returns a fresh Select query builder scoped to the managed entity.
     *
     * @return Select<T>
     */
    protected function select(): Select
    {
        /** @var Select<T> $select */
        $select = new Select($this->orm, $this->entityClass);

        return $select;
    }
}
