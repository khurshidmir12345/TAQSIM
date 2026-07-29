<?php

namespace Tests\Unit;

use App\Support\DatabaseIntegrityException;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class DatabaseIntegrityExceptionTest extends TestCase
{
    public function test_detects_mysql_duplicate_entry_by_driver_code(): void
    {
        $exception = new QueryException(
            'mysql',
            'insert into users ...',
            [],
            new \Exception('Duplicate entry \'+998901111111\' for key \'users.users_phone_unique\''),
        );

        $exception = $this->withErrorInfo($exception, ['23000', 1062]);

        $this->assertTrue(DatabaseIntegrityException::isDuplicateKeyViolation($exception));
    }

    public function test_detects_mysql_duplicate_entry_by_message(): void
    {
        $exception = new QueryException(
            'mysql',
            'insert into users ...',
            [],
            new \Exception('Duplicate entry \'foo\' for key \'users.users_email_unique\''),
        );

        $this->assertTrue(DatabaseIntegrityException::isDuplicateKeyViolation($exception));
    }

    public function test_detects_sqlite_unique_constraint_failed(): void
    {
        $exception = new QueryException(
            'sqlite',
            'insert into users ...',
            [],
            new \Exception('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: users.phone'),
        );

        $this->assertTrue(DatabaseIntegrityException::isDuplicateKeyViolation($exception));
    }

    public function test_does_not_treat_foreign_key_violation_as_duplicate(): void
    {
        $exception = new QueryException(
            'mysql',
            'insert into user_devices ...',
            [],
            new \Exception('Cannot add or update a child row: a foreign key constraint fails'),
        );

        $exception = $this->withErrorInfo($exception, ['23000', 1452]);

        $this->assertFalse(DatabaseIntegrityException::isDuplicateKeyViolation($exception));
    }

    public function test_does_not_treat_generic_integrity_violation_as_duplicate(): void
    {
        $exception = new QueryException(
            'sqlite',
            'insert into users ...',
            [],
            new \Exception('SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: users.name'),
        );

        $this->assertFalse(DatabaseIntegrityException::isDuplicateKeyViolation($exception));
    }

    private function withErrorInfo(QueryException $exception, array $errorInfo): QueryException
    {
        $reflection = new \ReflectionClass($exception);
        $property = $reflection->getProperty('errorInfo');
        $property->setAccessible(true);
        $property->setValue($exception, $errorInfo);

        return $exception;
    }
}
